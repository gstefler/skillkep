<?php

namespace Database\Factories;

use App\Models\Attempt;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attempt>
 */
class AttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'test_id' => Test::factory(),
            'started_at' => now(),
        ];
    }

    public function passed(int $score = 100): static
    {
        return $this->state(['submitted_at' => now(), 'score' => $score, 'is_passed' => true]);
    }
}
