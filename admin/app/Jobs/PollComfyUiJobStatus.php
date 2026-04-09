<?php

namespace App\Jobs;

use App\Events\GenerationJobStatusChanged;
use App\Models\GenerationJob;
use App\Services\ComfyUi\ComfyUiClient;
use App\Services\ComfyUi\ErrorHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * 轮询 ComfyUI 任务状态
 * 由于 ComfyUI 没有 webhook，需要定期查询 /history/{prompt_id} 获取任务状态
 */
class PollComfyUiJobStatus implements ShouldQueue
{
    use Queueable;

    /**
     * 任务最大尝试次数
     */
    public int $tries = 600; // 最多轮询 600 次，支持长达 30-60 分钟的任务

    /**
     * 每次轮询间隔秒数
     */
    public int $backoff = 1; // 极速轮询，1s一次，拒绝“自嗨”进度

    /**
     * 任务超时时间
     * 增加超时时间，避免远程 ComfyUI 响应慢导致任务失败
     */
    public int $timeout = 300; // 增加到 5 分钟

    public function __construct(
        public int $jobId
    ) {
    }

    public function handle(ComfyUiClient $client, ErrorHandler $errorHandler): void
    {
        $generationJob = GenerationJob::find($this->jobId);

        if (! $generationJob) {
            Log::error("Generation job not found: {$this->jobId}");
            return;
        }

        // 检查任务是否应该继续轮询
        if (!$this->shouldContinuePolling($generationJob)) {
            return;
        }

        $promptId = $generationJob->comfy_prompt_id;

        if (empty($promptId)) {
            Log::error("Generation job {$this->jobId} has no comfy_prompt_id");
            $generationJob->markAsFailed('Missing prompt ID');
            return;
        }

        try {
            // 使用带错误处理的请求
            $history = $client->fetchHistory($promptId);

            // 检查任务是否完成
            if (isset($history[$promptId])) {
                $this->handleJobCompletion($generationJob, $history[$promptId]);
                return;
            }

            // 检查任务是否在队列中
            if (!$this->isJobInQueue($client, $promptId)) {
                $this->handleJobDisappeared($generationJob);
                return;
            }

            // 任务仍在运行，更新进度并继续轮询
            $this->updateJobProgress($generationJob);
            $this->scheduleNextPoll();

        } catch (\Exception $e) {
            $this->handlePollingException($e, $generationJob, $errorHandler);
        }
    }

    /**
     * 检查是否应该继续轮询
     */
    private function shouldContinuePolling(GenerationJob $job): bool
    {
        // 如果任务已经不是运行状态，停止轮询
        if (!in_array($job->status, ['running', 'queued'])) {
            Log::info("Generation job {$job->id} status is {$job->status}, stopping poll");
            return false;
        }

        // 检查是否超过最大轮询时间（例如2小时）
        $maxPollingMinutes = 120;
        $createdAt = $job->created_at ?? now();
        $minutesElapsed = now()->diffInMinutes($createdAt);
        
        if ($minutesElapsed > $maxPollingMinutes) {
            Log::warning("Generation job {$job->id} exceeded max polling time ({$maxPollingMinutes} minutes)");
            $job->markAsFailed('任务执行超时（超过' . $maxPollingMinutes . '分钟）');
            return false;
        }

        // 检查连续错误次数
        $errorKey = "polling_errors_{$job->id}";
        $errorCount = Cache::get($errorKey, 0);
        
        if ($errorCount > 20) { // 连续错误超过20次
            Log::error("Generation job {$job->id} has too many consecutive errors ({$errorCount})");
            $job->markAsFailed('连续轮询失败次数过多，可能服务器不可用');
            return false;
        }

        return true;
    }

    /**
     * 处理任务完成
     */
    private function handleJobCompletion(GenerationJob $job, array $status): void
    {
        // 1. 只要有 outputs，或者 status_str 为 success，都算成功
        $isSuccess = isset($status['outputs']) || 
                     (isset($status['status']['status_str']) && $status['status']['status_str'] === 'success');

        if ($isSuccess) {
            // 2. 参考 Python 示例思路：提取所有图片输出
            $assets = [];
            $baseUrl = config('services.comfyui.base_url');
            
            if (isset($status['outputs'])) {
                foreach ($status['outputs'] as $nodeId => $nodeOutput) {
                    if (isset($nodeOutput['images'])) {
                        foreach ($nodeOutput['images'] as $img) {
                            // 拼接 ComfyUI 的 /view 接口地址
                            $params = http_build_query([
                                'filename' => $img['filename'],
                                'subfolder' => $img['subfolder'] ?? '',
                                'type' => $img['type'] ?? 'output'
                            ]);
                            $imageUrl = "{$baseUrl}/view?{$params}";
                            
                            // 保存到资产表 (assets 关系)
                            $assets[] = $job->assets()->create([
                                'type' => 'output',
                                'url' => $imageUrl,
                                'filename' => $img['filename'],
                                'metadata_json' => [
                                    'node_id' => $nodeId,
                                    'subfolder' => $img['subfolder'] ?? '',
                                    'comfy_type' => $img['type'] ?? 'output'
                                ]
                            ]);
                        }
                    }
                }
            }

            // 3. 更新任务状态
            $job->markAsSucceeded();
            Log::info("Generation job {$job->id} completed with " . count($assets) . " images");

            $job->events()->create([
                'status' => 'completed',
                'progress' => 100,
                'message' => '任务执行完成，生成了 ' . count($assets) . ' 张图片',
            ]);

            // 4. 发送广播（确保加载了最新的 assets 关系，这样前端才能立刻拿到 URL）
            $job->load('assets');
            event(new GenerationJobStatusChanged($job, 100, '完成'));
            
            Cache::forget("polling_errors_{$job->id}");
            return;
        }

        // 检查失败情况
        $isFailed = (isset($status['status']['status_str']) && $status['status']['status_str'] === 'failed') ||
                    (isset($status['status']) && $status['status'] === 'failed');

        if ($isFailed) {
            $errorMsg = $status['status']['messages'][0] ?? $status['error'] ?? 'ComfyUI 内部执行失败';
            $job->markAsFailed($errorMsg);
            Log::error("Generation job {$job->id} failed: {$errorMsg}");

            $job->events()->create([
                'status' => 'failed',
                'progress' => 0,
                'message' => '任务执行失败: ' . $errorMsg,
            ]);

            event(new GenerationJobStatusChanged($job, 0, '任务执行失败: ' . $errorMsg));
            
            // 清除错误计数
            Cache::forget("polling_errors_{$job->id}");
            return;
        }
    }

    /**
     * 检查任务是否在队列中
     */
    private function isJobInQueue(ComfyUiClient $client, string $promptId): bool
    {
        try {
            $queue = $client->fetchQueue();
            
            foreach (['queue_running', 'queue_pending'] as $key) {
                if (isset($queue[$key])) {
                    foreach ($queue[$key] as $item) {
                        if (($item[1] ?? '') === $promptId) {
                            return true;
                        }
                    }
                }
            }
            
            return false;
        } catch (\Exception $e) {
            // 获取队列失败，假设任务还在队列中
            Log::warning("Failed to fetch queue, assuming job is still in queue: " . $e->getMessage());
            return true;
        }
    }

    /**
     * 处理任务消失（不在历史也不在队列）
     */
    private function handleJobDisappeared(GenerationJob $job): void
    {
        $errorMsg = '任务在 ComfyUI 中异常中断或未被记录';
        $job->markAsFailed($errorMsg);
        Log::warning("Generation job {$job->id} disappeared from ComfyUI queue and history");

        $job->events()->create([
            'status' => 'failed',
            'progress' => 0,
            'message' => $errorMsg,
        ]);

        event(new GenerationJobStatusChanged($job, 0, $errorMsg));
        
        // 清除错误计数
        Cache::forget("polling_errors_{$job->id}");
    }

    /**
     * 更新任务进度
     */
    private function updateJobProgress(GenerationJob $job): void
    {
        $latestEvent = $job->events()->orderByDesc('id')->first();
        $currentProgress = $latestEvent ? intval($latestEvent->progress) : 0;
        
        // 智能进度更新：根据任务类型和时间调整进度增量
        $progressIncrement = $this->calculateProgressIncrement($job, $currentProgress);
        $newProgress = min(95, $currentProgress + $progressIncrement);

        if ($newProgress > $currentProgress || !$latestEvent || $latestEvent->status !== 'progress') {
            $job->events()->create([
                'status' => 'progress',
                'progress' => $newProgress,
                'message' => $this->getProgressMessage($newProgress),
            ]);
            event(new GenerationJobStatusChanged($job, $newProgress, $this->getProgressMessage($newProgress)));
        }

        Log::debug("Generation job {$job->id} progress updated to {$newProgress}%");
    }

    /**
     * 计算进度增量
     */
    private function calculateProgressIncrement(GenerationJob $job, int $currentProgress): int
    {
        // 基础增量
        $increment = 5;
        
        // 根据任务类型调整
        $type = $job->type ?? 'unknown';
        if (str_contains(strtolower($type), 'video')) {
            $increment = 2; // 视频生成较慢
        } elseif (str_contains(strtolower($type), 'image')) {
            $increment = 5; // 图片生成中等
        }
        
        // 根据当前进度调整：越接近完成，增量越小
        if ($currentProgress > 80) {
            $increment = max(1, $increment - 3);
        }
        
        return $increment;
    }

    /**
     * 获取进度消息
     */
    private function getProgressMessage(int $progress): string
    {
        $messages = [
            0 => '任务已开始处理',
            20 => '正在加载模型...',
            40 => '模型推理中...',
            60 => '生成内容中...',
            80 => '即将完成...',
            95 => '最终处理中...',
        ];
        
        // 找到最接近的进度消息
        $closestKey = 0;
        foreach ($messages as $key => $message) {
            if ($progress >= $key && $key > $closestKey) {
                $closestKey = $key;
            }
        }
        
        return $messages[$closestKey] ?? '处理中...';
    }

    /**
     * 安排下一次轮询
     */
    private function scheduleNextPoll(): void
    {
        // 动态调整轮询间隔：根据错误次数增加间隔
        $errorKey = "polling_errors_{$this->jobId}";
        $errorCount = Cache::get($errorKey, 0);
        
        $delay = $this->backoff;
        if ($errorCount > 0) {
            $delay = min($this->backoff * pow(2, $errorCount), 60); // 指数退避，最大60秒
        }
        
        Log::debug("Generation job {$this->jobId} will retry in {$delay}s");
        $this->release($delay);
    }

    /**
     * 处理轮询异常
     */
    private function handlePollingException(\Exception $e, GenerationJob $job, ErrorHandler $errorHandler): void
    {
        // 使用错误处理器分析错误
        $errorHandling = $errorHandler->handleException($e, 'fetch_history', $job->id);
        
        // 记录错误计数
        $errorKey = "polling_errors_{$job->id}";
        $errorCount = Cache::get($errorKey, 0);
        Cache::put($errorKey, $errorCount + 1, now()->addMinutes(30));
        
        Log::warning("Polling ComfyUI failed for job {$job->id}: {$errorHandling['message']}", [
            'error_type' => $errorHandling['error_type'],
            'consecutive_errors' => $errorCount + 1,
        ]);
        
        // 根据错误处理决定是否继续
        if ($errorHandling['should_retry']) {
            $this->release($errorHandling['delay_seconds']);
        } else {
            // 不重试，标记任务失败
            $job->markAsFailed($errorHandling['message']);
            $job->events()->create([
                'status' => 'failed',
                'progress' => 0,
                'message' => $errorHandling['message'],
            ]);
            event(new GenerationJobStatusChanged($job, 0, $errorHandling['message']));
            
            // 清除错误计数
            Cache::forget($errorKey);
        }
    }

    /**
     * 任务失败处理
     */
    public function failed(\Throwable $exception): void
    {
        $generationJob = GenerationJob::find($this->jobId);

        if ($generationJob) {
            $generationJob->markAsFailed($exception->getMessage());

            // 记录失败事件
            $generationJob->events()->create([
                'status' => 'failed',
                'progress' => 0,
                'message' => '任务轮询失败: ' . $exception->getMessage(),
            ]);

            event(new GenerationJobStatusChanged($generationJob));
        }
    }
}
