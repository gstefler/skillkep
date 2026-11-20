<?php

namespace Database\Factories;

use App\Enums\MaterialType;
use App\Enums\VideoStatus;
use App\Models\Material;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'title' => fake()->words(3, true),
            'type' => MaterialType::Pdf,
            'path' => 'courses/0/materials/'.fake()->uuid().'/document.pdf',
            'size' => 1024,
            'order' => 1,
        ];
    }

    public function video(VideoStatus $status = VideoStatus::Ready): static
    {
        return $this->state(fn () => [
            'type' => MaterialType::Video,
            'path' => 'courses/0/materials/'.fake()->uuid().'/source.mp4',
            'video_status' => $status,
            'hls_directory' => $status === VideoStatus::Ready ? fake()->regexify('[a-z0-9]{40}') : null,
            'duration_seconds' => $status === VideoStatus::Ready ? 120 : null,
        ]);
    }
}
