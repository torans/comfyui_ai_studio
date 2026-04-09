<?php

namespace Database\Factories;

use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTemplate>
 */
class WorkflowTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'code' => fake()->unique()->slug(3),
            'type' => fake()->randomElement(['t2i', 'i2i', 'i2v']),
            'version' => '1.0.0',
            'definition_json' => ['69' => ['inputs' => ['prompt' => 'placeholder']]],
            'parameter_schema_json' => null,
            'is_active' => true,
        ];
    }
}
