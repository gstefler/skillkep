<?php

namespace Database\Factories;

use App\Models\Module;
use App\Models\Test;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Test>
 */
class TestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'title' => 'Modulteszt',
            'pass_score' => 60,
        ];
    }
}
