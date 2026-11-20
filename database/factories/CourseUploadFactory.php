<?php

namespace Database\Factories;

use App\Enums\CourseUploadStatus;
use App\Models\CourseUpload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseUpload>
 */
class CourseUploadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->superAdmin(),
            'fingerprint' => hash('sha256', fake()->uuid()),
            'filename' => 'kurzus.zip',
            'size' => 1024,
            'chunk_size' => CourseUpload::CHUNK_SIZE,
            'total_chunks' => 1,
            'pass_score' => 60,
            'lock_modules' => false,
            'status' => CourseUploadStatus::Uploading,
        ];
    }
}
