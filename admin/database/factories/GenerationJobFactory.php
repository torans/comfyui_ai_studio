<?php

namespace Database\Factories;

use App\Models\GenerationJob;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenerationJob>
 */
class GenerationJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workflow_template_id' => WorkflowTemplate::factory(),
            'type' => 't2i',
            'status' => 'pending',
            'input_json' => [
                'prompt' => fake()->sentence(),
            ],
            'resolved_workflow_json' => null,
            'comfy_prompt_id' => null,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    /**
     * Indicate that the job is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the job is queued.
     */
    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate that the job is running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'started_at' => now(),
            'comfy_prompt_id' => 'prompt-' . fake()->uuid(),
        ]);
    }

    /**
     * Indicate that the job succeeded.
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'succeeded',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
            'comfy_prompt_id' => 'prompt-' . fake()->uuid(),
        ]);
    }

    /**
     * Indicate that the job failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
            'error_message' => 'Generation failed: ' . fake()->sentence(),
        ]);
    }
}
