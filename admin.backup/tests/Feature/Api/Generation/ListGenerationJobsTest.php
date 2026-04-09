<?php

namespace Tests\Feature\Api\Generation;

use App\Models\GenerationJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListGenerationJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_only_sees_own_jobs(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $other = User::factory()->create(['role' => 'employee']);

        GenerationJob::factory()->create(['user_id' => $user->id]);
        GenerationJob::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/generation-jobs');

        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
