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
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workflow_template_id' => WorkflowTemplate::factory(),
            'type' => 't2i',
            'status' => 'pending',
            'input_json' => ['prompt' => fake()->sentence()],
        ];
    }
}
