<?php

namespace Tests\Feature\Api\Generation;

use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateGenerationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_generation_job(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $workflow = WorkflowTemplate::factory()->create([
            'code' => 't2i_default',
            'type' => 't2i',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/generation-jobs', [
            'type' => 't2i',
            'workflow_code' => 't2i_default',
            'inputs' => [
                'prompt' => '一只蓝色机械猫',
                'aspect_ratio' => '1:1',
            ],
        ]);

        $response->assertCreated()->assertJsonFragment(['status' => 'pending']);
    }
}
