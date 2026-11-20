<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A learner's standing in a course: which module tests are passed and which
 * modules are still locked behind an unpassed prerequisite test.
 */
class CourseProgress
{
    /** @var Collection<int, int> */
    private Collection $passedTestIds;

    public function __construct(private readonly Course $course, private readonly User $user)
    {
        $course->loadMissing(['modules.test:id,module_id,title,pass_score', 'modules.prerequisite.test:id,module_id']);

        $testIds = $course->modules->pluck('test.id')->filter()->values();

        $this->passedTestIds = $testIds->isEmpty()
            ? collect()
            : $user->attempts()
                ->whereIn('test_id', $testIds)
                ->where('is_passed', true)
                ->distinct()
                ->pluck('test_id');
    }

    public static function for(Course $course, User $user): self
    {
        return new self($course, $user);
    }

    public function isPassed(Module $module): bool
    {
        return $module->test !== null && $this->passedTestIds->contains($module->test->id);
    }

    /** A module is locked until the test of its prerequisite module is passed. */
    public function isLocked(Module $module): bool
    {
        $prerequisiteTest = $module->prerequisite?->test;

        return $prerequisiteTest !== null && ! $this->passedTestIds->contains($prerequisiteTest->id);
    }

    public function testCount(): int
    {
        return $this->course->modules->filter(fn (Module $module) => $module->test !== null)->count();
    }

    public function passedCount(): int
    {
        return $this->course->modules->filter(fn (Module $module) => $this->isPassed($module))->count();
    }

    /** Share of the module tests passed, 0–100. A course without tests reports 0. */
    public function percent(): float
    {
        $total = $this->testCount();

        return $total > 0 ? round($this->passedCount() / $total * 100, 1) : 0.0;
    }

    public function isCompleted(): bool
    {
        return $this->testCount() > 0 && $this->passedCount() === $this->testCount();
    }
}
