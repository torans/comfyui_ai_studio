<?php

namespace App\Jobs;

use App\Models\GenerationJob;
use App\Models\GenerationJobEvent;
use App\Services\ComfyUi\ComfyUiClient;
use App\Services\Workflow\WorkflowResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Jobs\PollComfyUiJobStatus;

class DispatchGenerationJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * 任务超时时间（秒），防止 ComfyUI 响应过慢导致任务失败
     * @var int
     */
    public $timeout = 600; // 增加到 10 分钟，给 ComfyUI 足够时间响应


    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $jobId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(WorkflowResolver $resolver, ComfyUiClient $client): void
    {
        $generationJob = GenerationJob::with('workflowTemplate')->find($this->jobId);

        if (! $generationJob) {
            Log::error("Generation job not found: {$this->jobId}");
            return;
        }

        try {
            // 记录 queued 事件
            $generationJob->events()->create([
                'status' => 'queued',
                'progress' => 0,
                'message' => '任务已加入队列',
            ]);

            $generationJob->markAsQueued();

            $resolvedWorkflow = $resolver->resolve(
                $generationJob->workflowTemplate,
                $generationJob->input_json
            );

            // 采用独立文件存储，防止日志系统截断或乱码，确保 100% 是合法的 JSON
            file_put_contents(
                storage_path('logs/last_workflow.json'),
                json_encode($resolvedWorkflow, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            
            Log::info("Resolved Workflow saved to storage/logs/last_workflow.json (Job {$this->jobId})");

            $response = $client->queuePrompt($resolvedWorkflow, (string) $generationJob->id);

            $generationJob->markAsRunning(
                $response['prompt_id'] ?? '',
                $resolvedWorkflow
            );

            // 记录 running 事件
            $generationJob->events()->create([
                'status' => 'running',
                'progress' => 0,
                'message' => '正在执行任务',
                'payload_json' => ['prompt_id' => $response['prompt_id'] ?? ''],
            ]);

            // 启动轮询任务检查 ComfyUI 执行状态
            PollComfyUiJobStatus::dispatch($generationJob->id);
            
            // 立即广播一次 Running 状态
            event(new \App\Events\GenerationJobStatusChanged($generationJob, 0, '正在执行任务'));
        } catch (\Exception $e) {
            $generationJob->markAsFailed($e->getMessage());

            // 记录 failed 事件
            $generationJob->events()->create([
                'status' => 'failed',
                'progress' => 0,
                'message' => '任务执行失败: ' . $e->getMessage(),
            ]);

            // 必须通知前端任务已失败，否则前端会一直转圈
            event(new \App\Events\GenerationJobStatusChanged($generationJob, 0, '任务执行失败: ' . $e->getMessage()));

            Log::error("Failed to dispatch generation job {$this->jobId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $generationJob = GenerationJob::find($this->jobId);

        if ($generationJob) {
            $generationJob->markAsFailed($exception->getMessage());

            // 记录 failed 事件
            $generationJob->events()->create([
                'status' => 'failed',
                'progress' => 0,
                'message' => '任务彻底失败: ' . $exception->getMessage(),
            ]);

            // 必须通知前端任务已彻底失败
            event(new \App\Events\GenerationJobStatusChanged($generationJob, 0, '任务彻底失败: ' . $exception->getMessage()));
        }
    }
}
