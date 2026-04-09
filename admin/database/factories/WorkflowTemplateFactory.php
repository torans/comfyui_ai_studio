<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTemplate>
 */
class WorkflowTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'code' => fake()->unique()->slug(3),
            'type' => fake()->randomElement(['t2i', 'i2i', 'i2v']),
            'version' => '1.0.0',
            'definition_json' => [
                '69' => [
                    'inputs' => [
                        'prompt' => 'A placeholder prompt',
                    ],
                ],
            ],
            'parameter_schema_json' => [
                'prompt' => ['node' => '69', 'field' => 'prompt'],
            ],
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set the template type to text-to-image.
     */
    public function t2i(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 't2i',
            'code' => 't2i_' . fake()->unique()->slug(2),
            'name' => 'Text to Image Template',
        ]);
    }

    /**
     * Set the template type to image-to-image.
     */
    public function i2i(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'i2i',
            'code' => 'i2i_' . fake()->unique()->slug(2),
            'name' => 'Image to Image Template',
        ]);
    }

    /**
     * Set the template type to image-to-video.
     */
    public function i2v(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'i2v',
            'code' => 'i2v_' . fake()->unique()->slug(2),
            'name' => 'Image to Video Template',
        ]);
    }
}
