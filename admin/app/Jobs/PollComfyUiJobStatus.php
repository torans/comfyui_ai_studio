<?php

namespace App\Jobs;

use App\Events\GenerationJobStatusChanged;
use App\Models\GenerationJob;
use App\Services\ComfyUi\ComfyUiClient;
use App\Services\ComfyUi\ErrorHandler;
use App\Services\ComfyUi\WebSocketClient;
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
     * 注意：这个超时时间必须小于 queue:work 的 --timeout 参数
     */
    public int $timeout = 55; // 保持低于 queue:work 默认 60 秒，避免开发环境被子进程超时杀掉

    public function __construct(
        public int $jobId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(ComfyUiClient $client): void
    {
        $generationJob = GenerationJob::find($this->jobId);
        if (!$generationJob || $generationJob->status === 'succeeded' || $generationJob->status === 'failed') {
            return;
        }

        $promptId = $generationJob->comfy_prompt_id;
        if (!$promptId) {
            // 如果还没有 prompt_id，可能是初始提交阶段，释放并重试
            $this->release(1);
            return;
        }

        Log::info("Generation job {$this->jobId} starting WebSocket listener for prompt: {$promptId}");

        if (!$this->shouldUseWebSocket()) {
            Log::info("任务 {$this->jobId} WebSocket 处于冷却期，直接使用轮询");
            $this->performSinglePoll($client, $generationJob);
            return;
        }

        $ws = new WebSocketClient(config('services.comfyui.base_url'));
        if (!$ws->connect()) {
            // 如果 WS 连不上，退回到原来的轮询逻辑（这部分逻辑我保留在原来的 handle 里，但现在首选 WS）
            $this->temporarilyDisableWebSocket(120, 'connect_failed');
            $this->performSinglePoll($client, $generationJob);
            return;
        }

        $startTime = time();
        $lastHistoryCheck = 0;
        $lastProgressTime = $startTime;
        $maxWsTime = 30; // WebSocket 最多监听30秒，然后回退到轮询

        try {
            while (time() - $startTime < $this->timeout) {
                // 如果 WebSocket 监听超过30秒，回退到轮询模式
                if (time() - $startTime > $maxWsTime) {
                    Log::info("WebSocket 监听超时 ({$maxWsTime}秒)，回退到轮询模式");
                    $this->temporarilyDisableWebSocket(120, 'listen_timeout');
                    $ws->close();
                    break;
                }
                
                // 1. 读取 WebSocket 消息（非阻塞）
                $msg = $ws->receive();
                
                if ($msg) {
                    if (($msg['type'] ?? '') === 'progress' && ($msg['data']['prompt_id'] ?? '') === $promptId) {
                        $value = $msg['data']['value'] ?? 0;
                        $max = $msg['data']['max'] ?? 100;
                        $progress = $max > 0 ? floor(($value / $max) * 100) : 0;
                        
                        Log::debug("WS Progress for job {$this->jobId}: {$progress}%");
                        event(new GenerationJobStatusChanged($generationJob, $progress, "正在生成 ({$progress}%)"));
                        $lastProgressTime = time();
                    }
                }

                // 2. 每隔几秒强制检查一次 History，防止遗漏
                if (time() - $lastHistoryCheck >= 5) { // 从2秒增加到5秒，减少请求频率
                    try {
                        $history = $client->fetchHistory($promptId);
                        if (isset($history[$promptId])) {
                            $this->handleJobCompletion($generationJob, $history[$promptId]);
                            $ws->close();
                            return;
                        }
                    } catch (\Exception $e) {
                        Log::warning("检查历史记录失败: " . $e->getMessage());
                    }
                    $lastHistoryCheck = time();
                }

                // 3. 如果超过10秒没有进度更新，检查队列状态
                if (time() - $lastProgressTime > 10) {
                    try {
                        $queueStatus = $this->isJobInQueue($client, $promptId);
                        if ($queueStatus === false) {
                            Log::warning("任务 {$promptId} 从队列中消失");
                            $this->handleJobDisappeared($generationJob);
                            $ws->close();
                            return;
                        }
                    } catch (\Exception $e) {
                        // 队列检查失败，继续监听
                        Log::debug("队列检查失败: " . $e->getMessage());
                    }
                }

                // 小睡一会儿，避免 CPU 占用过高
                usleep(100000); // 100ms
            }
        } catch (\Exception $e) {
            Log::error("WS Listener error for job {$this->jobId}: " . $e->getMessage());
            $this->temporarilyDisableWebSocket(180, 'listener_exception');
        } finally {
            $ws->close();
        }

        // WebSocket 监听结束，回退到传统的轮询模式
        Log::info("任务 {$this->jobId} 回退到轮询模式");
        $this->performSinglePoll($client, $generationJob);
    }

    private function shouldUseWebSocket(): bool
    {
        return !Cache::has($this->webSocketCooldownKey());
    }

    private function temporarilyDisableWebSocket(int $seconds, string $reason): void
    {
        Cache::put($this->webSocketCooldownKey(), [
            'reason' => $reason,
            'disabled_at' => now()->toISOString(),
        ], now()->addSeconds($seconds));

        Log::warning("任务 {$this->jobId} 暂停 WebSocket {$seconds} 秒", [
            'reason' => $reason,
        ]);
    }

    private function webSocketCooldownKey(): string
    {
        return "poll_ws_disabled_{$this->jobId}";
    }

    /**
     * 原有的单次轮询逻辑，作为备选
     */
    private function performSinglePoll(ComfyUiClient $client, GenerationJob $generationJob): void
    {
        $promptId = $generationJob->comfy_prompt_id;
        
        try {
            $history = $client->fetchHistory($promptId);
            if (isset($history[$promptId])) {
                $this->handleJobCompletion($generationJob, $history[$promptId]);
                return;
            }

            $queueStatus = $this->isJobInQueue($client, $promptId);
            if ($queueStatus === false) {
                $this->handleJobDisappeared($generationJob);
                return;
            }

            $this->updateJobProgress($generationJob);
            $this->release(1);
        } catch (\Exception $e) {
            $this->handlePollingError($e);
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
            // 2. 提取所有图片/视频输出
            $assets = [];
            $baseUrl = config('services.comfyui.base_url');
            
            if (isset($status['outputs'])) {
                foreach ($status['outputs'] as $nodeId => $nodeOutput) {
                    foreach ($this->extractOutputFiles($nodeOutput) as $file) {
                        $params = http_build_query([
                            'filename' => $file['filename'],
                            'subfolder' => $file['subfolder'] ?? '',
                            'type' => $file['type'] ?? 'output'
                        ]);
                        $remoteUrl = "{$baseUrl}/view?{$params}";

                        $assets[] = $job->assets()->create([
                            'type' => 'output',
                            'user_id' => $job->user_id,
                            'filename' => $file['filename'],
                            'metadata_json' => [
                                'node_id' => $nodeId,
                                'remote_url' => $remoteUrl,
                                'subfolder' => $file['subfolder'] ?? '',
                                'comfy_type' => $file['type'] ?? 'output',
                                'media_kind' => $file['media_kind'],
                                'source_bucket' => $file['source_bucket'] ?? null,
                            ]
                        ]);
                    }
                }
            }

            // 3. 更新任务状态
            $job->markAsSucceeded();
            Log::info("Generation job {$job->id} completed with " . count($assets) . " assets");

            $job->events()->create([
                'status' => 'completed',
                'progress' => 100,
                'message' => '任务执行完成，生成了 ' . count($assets) . ' 个结果',
            ]);

            // 4. 发送广播（确保加载了最新的 assets 关系，这样前端才能立刻拿到 URL）
            $job->load('assets');
            event(new GenerationJobStatusChanged($job, 100, '完成'));
            
            Cache::forget("polling_errors_{$job->id}");
            Cache::forget("queue_disappeared_{$job->id}");
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
            Cache::forget("queue_disappeared_{$job->id}");
            return;
        }
    }

    /**
     * 检查任务是否在队列中
     */
    private function isJobInQueue(ComfyUiClient $client, string $promptId): ?bool
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
            // 获取队列失败时返回未知状态，让上层继续依赖 history 和下一轮轮询。
            Log::warning("Failed to fetch queue, queue status is unknown: " . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array<int, array{filename:string, subfolder?:string, type?:string, media_kind:string, source_bucket?:string}>
     */
    private function extractOutputFiles(array $nodeOutput): array
    {
        $files = [];

        foreach (['images', 'gifs', 'videos'] as $bucket) {
            $items = $nodeOutput[$bucket] ?? null;
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_array($item) || !isset($item['filename']) || !is_string($item['filename'])) {
                    continue;
                }

                $files[] = [
                    'filename' => $item['filename'],
                    'subfolder' => $item['subfolder'] ?? '',
                    'type' => $item['type'] ?? 'output',
                    'media_kind' => $bucket === 'images' ? 'image' : 'video',
                    'source_bucket' => $bucket,
                ];
            }
        }

        return $files;
    }

    /**
     * 处理任务消失（不在历史也不在队列）
     */
    private function handleJobDisappeared(GenerationJob $job): void
    {
        $missKey = "queue_disappeared_{$job->id}";
        $missCount = Cache::increment($missKey);
        Cache::put($missKey, $missCount, now()->addMinutes(10));

        // history 和 queue 之间存在短暂延迟，连续多次确认消失后再判失败。
        if ($missCount < 3) {
            Log::warning("Generation job {$job->id} missing from queue/history check ({$missCount}/3), will retry");
            $this->release(3);
            return;
        }

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
        Cache::forget($missKey);
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
