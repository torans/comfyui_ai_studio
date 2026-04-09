<?php

namespace App\Services\ComfyUi;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\GenerationJob;

/**
 * ComfyUI 错误处理器
 * 智能处理网络错误、超时、服务器不可用等情况
 */
class ErrorHandler
{
    /**
     * 错误类型常量
     */
    const ERROR_NETWORK = 'network';
    const ERROR_TIMEOUT = 'timeout';
    const ERROR_SERVER = 'server';
    const ERROR_VALIDATION = 'validation';
    const ERROR_UNKNOWN = 'unknown';

    /**
     * 处理 ComfyUI 请求异常
     *
     * @param \Exception $exception 异常对象
     * @param string $operation 操作名称
     * @param int $jobId 任务ID（可选）
     * @return array 处理结果 [shouldRetry: bool, delay: int, message: string]
     */
    public function handleException(\Exception $exception, string $operation, ?int $jobId = null): array
    {
        $errorType = $this->classifyError($exception);
        $errorMessage = $exception->getMessage();
        
        Log::warning("ComfyUI {$operation} 错误 [{$errorType}]: {$errorMessage}", [
            'job_id' => $jobId,
            'operation' => $operation,
            'error_type' => $errorType,
        ]);

        // 记录错误统计
        $this->recordErrorStat($errorType, $operation);

        // 根据错误类型决定是否重试
        $handling = $this->getErrorHandling($errorType, $operation, $jobId);

        // 如果有关联的任务，更新任务状态
        if ($jobId && $handling['update_job']) {
            $this->updateJobStatus($jobId, $handling['job_status'], $errorMessage);
        }

        return [
            'should_retry' => $handling['should_retry'],
            'delay_seconds' => $handling['delay_seconds'],
            'error_type' => $errorType,
            'message' => $handling['message'],
        ];
    }

    /**
     * 分类错误类型
     */
    private function classifyError(\Exception $exception): string
    {
        $message = strtolower($exception->getMessage());
        $code = $exception->getCode();

        // 网络相关错误
        if (str_contains($message, 'connection') ||
            str_contains($message, 'network') ||
            str_contains($message, 'resolve') ||
            $code === 0) {
            return self::ERROR_NETWORK;
        }

        // 超时错误
        if (str_contains($message, 'timeout') ||
            str_contains($message, 'timed out')) {
            return self::ERROR_TIMEOUT;
        }

        // 服务器错误 (5xx)
        if ($code >= 500 && $code < 600) {
            return self::ERROR_SERVER;
        }

        // 验证错误 (4xx)
        if ($code >= 400 && $code < 500) {
            return self::ERROR_VALIDATION;
        }

        return self::ERROR_UNKNOWN;
    }

    /**
     * 获取错误处理策略
     */
    private function getErrorHandling(string $errorType, string $operation, ?int $jobId): array
    {
        $baseConfig = [
            'should_retry' => true,
            'delay_seconds' => 5,
            'update_job' => true,
            'job_status' => 'warning',
            'message' => '连接异常，正在重试...',
        ];

        // 根据错误类型调整策略
        switch ($errorType) {
            case self::ERROR_NETWORK:
                // 网络错误：重试但增加延迟
                return array_merge($baseConfig, [
                    'delay_seconds' => 10,
                    'message' => '网络连接失败，正在重试...',
                ]);

            case self::ERROR_TIMEOUT:
                // 超时错误：减少重试次数，增加延迟
                $consecutiveErrors = $this->getConsecutiveErrors($jobId);
                $delay = min(30, 5 * pow(2, $consecutiveErrors)); // 指数退避，最大30秒
                
                return array_merge($baseConfig, [
                    'delay_seconds' => $delay,
                    'message' => '服务器响应超时，正在重试...',
                ]);

            case self::ERROR_SERVER:
                // 服务器错误：短暂等待后重试
                return array_merge($baseConfig, [
                    'delay_seconds' => 15,
                    'message' => '服务器内部错误，等待恢复...',
                ]);

            case self::ERROR_VALIDATION:
                // 验证错误：通常不重试（参数错误）
                return [
                    'should_retry' => false,
                    'delay_seconds' => 0,
                    'update_job' => true,
                    'job_status' => 'failed',
                    'message' => '请求参数错误，请检查配置',
                ];

            case self::ERROR_UNKNOWN:
            default:
                // 未知错误：保守重试
                return array_merge($baseConfig, [
                    'delay_seconds' => 8,
                    'message' => '未知错误，正在重试...',
                ]);
        }
    }

    /**
     * 获取连续错误次数
     */
    private function getConsecutiveErrors(?int $jobId): int
    {
        if (!$jobId) {
            return 0;
        }

        $key = "comfyui_error_count_{$jobId}";
        return Cache::get($key, 0);
    }

    /**
     * 记录错误统计
     */
    private function recordErrorStat(string $errorType, string $operation): void
    {
        $key = "comfyui_error_stats_{$errorType}_{$operation}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHours(1));

        // 全局错误统计
        $globalKey = "comfyui_error_total";
        $total = Cache::get($globalKey, 0);
        Cache::put($globalKey, $total + 1, now()->addHours(1));

        // 如果错误率过高，触发告警
        $this->checkErrorRate($errorType, $operation);
    }

    /**
     * 检查错误率
     */
    private function checkErrorRate(string $errorType, string $operation): void
    {
        $errorKey = "comfyui_error_stats_{$errorType}_{$operation}";
        $requestKey = "comfyui_request_stats_{$operation}";
        
        $errorCount = Cache::get($errorKey, 0);
        $requestCount = Cache::get($requestKey, 0);
        
        if ($requestCount > 10) {
            $errorRate = ($errorCount / $requestCount) * 100;
            
            if ($errorRate > 50) { // 错误率超过50%
                Log::error("ComfyUI {$operation} 错误率过高: {$errorRate}%", [
                    'error_type' => $errorType,
                    'error_count' => $errorCount,
                    'request_count' => $requestCount,
                ]);
                
                // 可以在这里发送告警通知
            }
        }
    }

    /**
     * 更新任务状态
     */
    private function updateJobStatus(int $jobId, string $status, string $message): void
    {
        try {
            $job = GenerationJob::find($jobId);
            
            if ($job) {
                // 记录错误事件
                $job->events()->create([
                    'status' => $status,
                    'progress' => $job->events()->orderByDesc('id')->first()->progress ?? 0,
                    'message' => $message,
                ]);

                // 更新连续错误计数
                $errorKey = "comfyui_error_count_{$jobId}";
                if ($status === 'warning') {
                    $count = Cache::get($errorKey, 0);
                    Cache::put($errorKey, $count + 1, now()->addMinutes(30));
                } else {
                    Cache::forget($errorKey);
                }
            }
        } catch (\Exception $e) {
            Log::error("更新任务状态失败: {$e->getMessage()}");
        }
    }

    /**
     * 记录请求统计（成功时调用）
     */
    public function recordSuccess(string $operation): void
    {
        $key = "comfyui_request_stats_{$operation}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHours(1));
    }

    /**
     * 获取系统健康状态
     */
    public function getHealthStatus(): array
    {
        $totalErrors = Cache::get('comfyui_error_total', 0);
        $totalRequests = 0;
        
        // 计算总请求数
        $operations = ['queue_prompt', 'fetch_history', 'fetch_queue', 'upload_image'];
        foreach ($operations as $op) {
            $totalRequests += Cache::get("comfyui_request_stats_{$op}", 0);
        }
        
        $errorRate = $totalRequests > 0 ? ($totalErrors / $totalRequests) * 100 : 0;
        
        return [
            'total_requests' => $totalRequests,
            'total_errors' => $totalErrors,
            'error_rate' => round($errorRate, 2),
            'status' => $errorRate < 10 ? 'healthy' : ($errorRate < 30 ? 'degraded' : 'unhealthy'),
            'last_check' => now()->toDateTimeString(),
        ];
    }
}