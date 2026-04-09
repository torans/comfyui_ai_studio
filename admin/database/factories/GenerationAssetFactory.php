<?php

namespace Database\Factories;

use App\Models\GenerationAsset;
use App\Models\GenerationJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenerationAsset>
 */
class GenerationAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generation_job_id' => GenerationJob::factory(),
            'user_id' => User::factory(),
            'type' => 'image',
            'filename' => fake()->uuid() . '.png',
            'subfolder' => 'images',
            'storage_disk' => 'local',
            'storage_path' => null,
            'preview_path' => null,
            'metadata_json' => [
                'width' => 1024,
                'height' => 1024,
            ],
        ];
    }

    /**
     * Set the asset type to image.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'image',
            'filename' => fake()->uuid() . '.png',
        ]);
    }

    /**
     * Set the asset type to video.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'video',
            'filename' => fake()->uuid() . '.mp4',
        ]);
    }
}
