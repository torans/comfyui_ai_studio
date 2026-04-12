<?php

namespace App\Services\ComfyUi;

use App\Models\GenerationJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ComfyUI 错误处理器
 * 智能处理网络错误、超时、服务器不可用等情况
 */
class ErrorHandler
{
    const ERROR_NETWORK = 'network';
    const ERROR_TIMEOUT = 'timeout';
    const ERROR_SERVER = 'server';
    const ERROR_VALIDATION = 'validation';
    const ERROR_UNKNOWN = 'unknown';

    public function handleException(\Exception $exception, string $operation, ?int $jobId = null): array
    {
        $errorType = $this->classifyError($exception);
        $errorMessage = $exception->getMessage();

        Log::warning("ComfyUI {$operation} 错误 [{$errorType}]: {$errorMessage}", [
            'job_id' => $jobId,
            'operation' => $operation,
            'error_type' => $errorType,
        ]);

        $this->recordErrorStat($errorType, $operation);
        $handling = $this->getErrorHandling($errorType, $operation, $jobId);

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

    public function recordSuccess(string $operation): void
    {
        $key = "comfyui_request_stats_{$operation}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHours(1));
    }

    public function getHealthStatus(): array
    {
        $totalErrors = Cache::get('comfyui_error_total', 0);
        $totalRequests = 0;

        foreach (['queue_prompt', 'fetch_history', 'fetch_queue', 'upload_image'] as $op) {
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

    private function classifyError(\Exception $exception): string
    {
        $message = strtolower($exception->getMessage());
        $code = $exception->getCode();

        if (str_contains($message, 'connection') ||
            str_contains($message, 'network') ||
            str_contains($message, 'resolve') ||
            $code === 0) {
            return self::ERROR_NETWORK;
        }

        if (str_contains($message, 'timeout') ||
            str_contains($message, 'timed out')) {
            return self::ERROR_TIMEOUT;
        }

        if ($code >= 500 && $code < 600) {
            return self::ERROR_SERVER;
        }

        if ($code >= 400 && $code < 500) {
            return self::ERROR_VALIDATION;
        }

        return self::ERROR_UNKNOWN;
    }

    private function getErrorHandling(string $errorType, string $operation, ?int $jobId): array
    {
        $baseConfig = [
            'should_retry' => true,
            'delay_seconds' => 5,
            'update_job' => true,
            'job_status' => 'warning',
            'message' => '连接异常，正在重试...',
        ];

        switch ($errorType) {
            case self::ERROR_NETWORK:
                return array_merge($baseConfig, [
                    'delay_seconds' => 10,
                    'message' => '网络连接失败，正在重试...',
                ]);

            case self::ERROR_TIMEOUT:
                $consecutiveErrors = $this->getConsecutiveErrors($jobId);
                $delay = min(30, 5 * pow(2, $consecutiveErrors));

                return array_merge($baseConfig, [
                    'delay_seconds' => $delay,
                    'message' => '服务器响应超时，正在重试...',
                ]);

            case self::ERROR_SERVER:
                return array_merge($baseConfig, [
                    'delay_seconds' => 15,
                    'message' => '服务器内部错误，等待恢复...',
                ]);

            case self::ERROR_VALIDATION:
                return [
                    'should_retry' => false,
                    'delay_seconds' => 0,
                    'update_job' => true,
                    'job_status' => 'failed',
                    'message' => '请求参数错误，请检查配置',
                ];

            case self::ERROR_UNKNOWN:
            default:
                return array_merge($baseConfig, [
                    'delay_seconds' => 8,
                    'message' => '未知错误，正在重试...',
                ]);
        }
    }

    private function getConsecutiveErrors(?int $jobId): int
    {
        if (! $jobId) {
            return 0;
        }

        return Cache::get("comfyui_error_count_{$jobId}", 0);
    }

    private function recordErrorStat(string $errorType, string $operation): void
    {
        $key = "comfyui_error_stats_{$errorType}_{$operation}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHours(1));

        $globalKey = 'comfyui_error_total';
        $total = Cache::get($globalKey, 0);
        Cache::put($globalKey, $total + 1, now()->addHours(1));

        $this->checkErrorRate($errorType, $operation);
    }

    private function checkErrorRate(string $errorType, string $operation): void
    {
        $errorCount = Cache::get("comfyui_error_stats_{$errorType}_{$operation}", 0);
        $requestCount = Cache::get("comfyui_request_stats_{$operation}", 0);

        if ($requestCount > 10) {
            $errorRate = ($errorCount / $requestCount) * 100;

            if ($errorRate > 50) {
                Log::error("ComfyUI {$operation} 错误率过高: {$errorRate}%", [
                    'error_type' => $errorType,
                    'error_count' => $errorCount,
                    'request_count' => $requestCount,
                ]);
            }
        }
    }

    private function updateJobStatus(int $jobId, string $status, string $message): void
    {
        try {
            $job = GenerationJob::find($jobId);

            if ($job) {
                $job->events()->create([
                    'status' => $status,
                    'progress' => $job->events()->orderByDesc('id')->first()->progress ?? 0,
                    'message' => $message,
                ]);

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
}
