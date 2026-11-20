<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'description' => null,
            'is_published' => true,
            'sequential_modules' => false,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    /** Make the course available to the company. */
    public function availableTo(Company $company): static
    {
        return $this->afterCreating(fn (Course $course) => $course->companies()->attach($company));
    }

    /** Make the course available to the learner's company and enrol the learner. */
    public function enrolling(User $learner): static
    {
        return $this->afterCreating(function (Course $course) use ($learner): void {
            if ($learner->company_id !== null) {
                $course->companies()->syncWithoutDetaching([$learner->company_id]);
            }

            $course->learners()->attach($learner);
        });
    }
}
