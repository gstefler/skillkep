<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Test;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_id' => Test::factory(),
            'type' => QuestionType::TrueFalse,
            'prompt' => fake()->sentence().'?',
            'order' => 1,
            'points' => 1,
            'correct_answer' => 'true',
        ];
    }
}
