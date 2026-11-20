<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Course;
use App\Models\Module;
use App\Services\CourseProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The learner's side: the courses they are enrolled in, and a course's modules.
 */
class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $courses = $user->accessibleCourses()
            ->withCount('modules')
            ->orderByPivot('created_at', 'desc')
            ->get()
            ->map(function (Course $course) use ($user) {
                $progress = CourseProgress::for($course, $user);

                return [
                    'id' => $course->id,
                    'slug' => $course->slug,
                    'title' => $course->title,
                    'description' => $course->description,
                    'modules_count' => $course->modules_count,
                    'tests_count' => $progress->testCount(),
                    'passed_count' => $progress->passedCount(),
                    'percent' => $progress->percent(),
                    'completed' => $progress->isCompleted(),
                    'enrolled_at' => $course->pivot?->created_at?->format('Y. m. d.'),
                ];
            });

        return Inertia::render('courses/Index', [
            'courses' => $courses,
        ]);
    }

    public function show(Request $request, Course $course): Response
    {
        Gate::authorize('view', $course);

        $user = $request->user();
        $course->load(['modules' => fn ($query) => $query->withCount('materials')]);
        $progress = CourseProgress::for($course, $user);

        $bestScores = Attempt::query()
            ->whereBelongsTo($user)
            ->whereIn('test_id', $course->modules->pluck('test.id')->filter())
            ->whereNotNull('submitted_at')
            ->selectRaw('test_id, max(score) as best')
            ->groupBy('test_id')
            ->pluck('best', 'test_id');

        return Inertia::render('courses/Show', [
            'course' => $course->only(['id', 'slug', 'title', 'description', 'sequential_modules']),
            'progress' => [
                'percent' => $progress->percent(),
                'passed' => $progress->passedCount(),
                'tests' => $progress->testCount(),
                'completed' => $progress->isCompleted(),
            ],
            'modules' => $course->modules->map(fn (Module $module) => [
                'id' => $module->id,
                'title' => $module->title,
                'materials_count' => $module->materials_count,
                'has_test' => $module->test !== null,
                'passed' => $progress->isPassed($module),
                'best_score' => $module->test ? $bestScores[$module->test->id] ?? null : null,
                'locked' => $progress->isLocked($module),
                'prerequisite' => $module->prerequisite?->title,
            ]),
        ]);
    }
}
