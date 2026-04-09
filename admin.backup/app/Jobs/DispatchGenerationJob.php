<?php

namespace App\Jobs;

use App\Models\GenerationJob;
use App\Services\ComfyUi\ComfyUiClient;
use App\Services\Workflow\WorkflowResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public int $jobId
    ) {}

    public function handle(WorkflowResolver $resolver, ComfyUiClient $client): void
    {
        $generationJob = GenerationJob::findOrFail($this->jobId);

        $generationJob->update([
            'status' => 'queued',
            'started_at' => now(),
        ]);

        $resolvedWorkflow = $resolver->resolve($generationJob->workflowTemplate, $generationJob->input_json);

        $response = $client->queuePrompt($resolvedWorkflow, (string) $generationJob->id);

        $generationJob->update([
            'status' => 'running',
            'resolved_workflow_json' => $resolvedWorkflow,
            'comfy_prompt_id' => $response['prompt_id'],
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $generationJob = GenerationJob::findOrFail($this->jobId);

        $generationJob->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
