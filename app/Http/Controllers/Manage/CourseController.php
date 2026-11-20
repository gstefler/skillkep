<?php

namespace App\Http\Controllers\Manage;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\FlashesGeneratedPassword;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Course;
use App\Models\Material;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Course management. Superadmins see and administer every course; admins see
 * the courses available to their company and enrol its learners.
 */
class CourseController extends Controller
{
    use FlashesGeneratedPassword;

    public function index(Request $request): Response
    {
        $actor = $request->user();

        $courses = Course::query()
            ->manageableBy($actor)
            ->withCount(['modules', 'companies'])
            ->withCount(['learners' => fn (Builder $query) => $actor->isSuperAdmin()
                ? $query
                : $query->where('users.company_id', $actor->company_id)])
            ->latest()
            ->paginate(20)
            ->through(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'is_published' => $course->is_published,
                'modules_count' => $course->modules_count,
                'companies_count' => $course->companies_count,
                'learners_count' => $course->learners_count,
                'created_at' => $course->created_at?->format('Y. m. d.'),
            ]);

        return Inertia::render('manage/courses/Index', [
            'courses' => $courses,
            'canUpload' => $actor->isSuperAdmin(),
        ]);
    }

    public function show(Request $request, Course $course): Response
    {
        Gate::authorize('manage', $course);

        $actor = $request->user();
        $course->load(['modules.materials', 'modules.test' => fn ($q) => $q->withCount('questions'), 'modules.prerequisite:id,title']);
        $enrolledIds = $course->learners()->pluck('users.id')->all();

        $learners = $course->enrollableLearnersFor($actor)
            ->with('company:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $learner) => [
                'id' => $learner->id,
                'name' => $learner->name,
                'email' => $learner->email,
                'company' => $learner->company?->only(['id', 'name']),
                'enrolled' => in_array($learner->id, $enrolledIds, true),
            ]);

        return Inertia::render('manage/courses/Show', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'description' => $course->description,
                'is_published' => $course->is_published,
                'sequential_modules' => $course->sequential_modules,
                'created_at' => $course->created_at?->format('Y. m. d.'),
            ],
            'modules' => $course->modules->map(fn (Module $module) => [
                'id' => $module->id,
                'title' => $module->title,
                'prerequisite' => $module->prerequisite?->title,
                'test' => $module->test ? [
                    'questions_count' => $module->test->questions_count,
                    'pass_score' => $module->test->pass_score,
                ] : null,
                'materials' => $module->materials->map(fn (Material $material) => [
                    'id' => $material->id,
                    'title' => $material->title,
                    'type' => $material->type->value,
                    'size' => $material->size,
                    'video_status' => $material->video_status?->value,
                    'video_status_label' => $material->video_status?->label(),
                    'video_error' => $material->video_error,
                    'duration_seconds' => $material->duration_seconds,
                ]),
            ]),
            'companies' => $actor->isSuperAdmin() ? $this->companyOptions($course) : [],
            'learners' => $learners,
            'canAdminister' => $actor->isSuperAdmin(),
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('administer', $course);

        $course->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]));

        $this->flashToast('A kurzus adatai frissültek.');

        return back();
    }

    public function destroy(Course $course): RedirectResponse
    {
        Gate::authorize('administer', $course);

        $course->deleteFiles();
        $course->delete();

        $this->flashToast("{$course->title} törölve.");

        return to_route('manage.courses.index');
    }

    public function publish(Course $course): RedirectResponse
    {
        Gate::authorize('administer', $course);

        $course->update(['is_published' => ! $course->is_published]);

        $this->flashToast($course->is_published
            ? 'A kurzus közzétéve: a beiratkozott tanulók már látják.'
            : 'A kurzus visszavonva: a tanulók nem látják.');

        return back();
    }

    /** Chain the modules (each needs the previous module's test) or unlock them all. */
    public function sequentialModules(Course $course): RedirectResponse
    {
        Gate::authorize('administer', $course);

        $enable = ! $course->sequential_modules;

        DB::transaction(function () use ($course, $enable): void {
            $course->update(['sequential_modules' => $enable]);

            $previousId = null;

            foreach ($course->modules()->get() as $module) {
                $module->update(['prerequisite_module_id' => $enable ? $previousId : null]);
                $previousId = $module->id;
            }
        });

        $this->flashToast($enable
            ? 'A modulok mostantól sorrendben nyílnak meg.'
            : 'A modulok mostantól tetszőleges sorrendben elérhetők.');

        return back();
    }

    /**
     * @return Collection<int, array{id: int, name: string, learners_count: int, enrolled_count: int, assigned: bool}>
     */
    private function companyOptions(Course $course): Collection
    {
        $assignedIds = $course->companies()->pluck('companies.id')->all();
        $enrolledPerCompany = $course->learners()
            ->selectRaw('users.company_id, count(*) as aggregate')
            ->groupBy('users.company_id')
            ->pluck('aggregate', 'users.company_id');

        return Company::query()
            ->withCount(['users as learners_count' => fn (Builder $q) => $q->where('role', UserRole::Learner)])
            ->orderBy('name')
            ->get()
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'learners_count' => $company->learners_count,
                'enrolled_count' => (int) ($enrolledPerCompany[$company->id] ?? 0),
                'assigned' => in_array($company->id, $assignedIds, true),
            ]);
    }
}
