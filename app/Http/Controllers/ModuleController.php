<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Course;
use App\Models\Material;
use App\Models\Module;
use App\Services\CourseProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function show(Request $request, Course $course, Module $module): Response
    {
        Gate::authorize('view', $course);
        abort_unless($module->course_id === $course->id, 404);

        $user = $request->user();
        $progress = CourseProgress::for($course, $user);
        $module = $course->modules->firstWhere('id', $module->id) ?? $module;

        abort_if($progress->isLocked($module), 403, 'Ez a modul az előző modul tesztjének teljesítése után nyílik meg.');

        $module->load(['materials', 'test' => fn ($query) => $query->withCount('questions')]);

        $attempts = $module->test
            ? $user->attempts()->where('test_id', $module->test->id)->latest('started_at')->get()
            : collect();
        $active = $attempts->first(fn (Attempt $attempt) => $attempt->isInProgress());

        $index = $course->modules->search(fn (Module $m) => $m->id === $module->id);
        $next = $index === false ? null : $course->modules->get($index + 1);

        return Inertia::render('courses/Module', [
            'course' => $course->only(['id', 'slug', 'title']),
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'passed' => $progress->isPassed($module),
            ],
            'materials' => $module->materials->map(fn (Material $material) => [
                'id' => $material->id,
                'title' => $material->title,
                'type' => $material->type->value,
                'playable' => $material->isPlayable(),
                'video_status' => $material->video_status?->value,
                'duration_seconds' => $material->duration_seconds,
            ]),
            'test' => $module->test ? [
                'id' => $module->test->id,
                'title' => $module->test->title,
                'questions_count' => $module->test->questions_count,
                'pass_score' => $module->test->pass_score,
                'time_limit_minutes' => $module->test->time_limit_minutes,
                'active_attempt_id' => $active?->id,
                'attempts' => $attempts->whereNotNull('submitted_at')->values()->map(fn (Attempt $attempt) => [
                    'id' => $attempt->id,
                    'score' => $attempt->score,
                    'is_passed' => $attempt->is_passed,
                    'submitted_at' => $attempt->submitted_at?->format('Y. m. d. H:i'),
                ]),
            ] : null,
            'next' => $next ? [
                'id' => $next->id,
                'title' => $next->title,
                'locked' => $progress->isLocked($next),
            ] : null,
        ]);
    }
}
