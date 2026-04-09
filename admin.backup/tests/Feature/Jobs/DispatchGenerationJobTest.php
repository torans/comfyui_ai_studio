<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DispatchGenerationJob;
use App\Models\GenerationJob;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DispatchGenerationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_job_queues_prompt_and_marks_job_running(): void
    {
        Http::fake([
            '*/prompt' => Http::response(['prompt_id' => 'prompt-123'], 200),
        ]);

        $user = User::factory()->create();
        $workflow = WorkflowTemplate::factory()->create([
            'type' => 't2i',
            'definition_json' => ['69' => ['inputs' => ['prompt' => 'placeholder']]],
        ]);

        $job = GenerationJob::create([
            'user_id' => $user->id,
            'workflow_template_id' => $workflow->id,
            'type' => 't2i',
            'status' => 'pending',
            'input_json' => ['prompt' => '一只蓝色机械猫'],
        ]);

        app(DispatchGenerationJob::class, ['jobId' => $job->id])->handle(
            app(\App\Services\Workflow\WorkflowResolver::class),
            app(\App\Services\ComfyUi\ComfyUiClient::class),
        );

        $this->assertDatabaseHas('generation_jobs', [
            'id' => $job->id,
            'status' => 'running',
            'comfy_prompt_id' => 'prompt-123',
        ]);
    }
}
