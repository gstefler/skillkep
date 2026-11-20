<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\FlashesGeneratedPassword;
use App\Http\Requests\Admin\StoreLearnerRequest;
use App\Models\Company;
use App\Models\Course;
use App\Models\User;
use App\Services\CourseProgress;
use App\Services\UserProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LearnerController extends Controller
{
    use FlashesGeneratedPassword;

    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));

        $learners = User::query()
            ->manageableLearnersFor($request->user())
            ->with('company:id,name')
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $query->where(fn ($q) => $q->whereLike('name', $like)->orWhereLike('email', $like));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $learner) => [
                'id' => $learner->id,
                'name' => $learner->name,
                'email' => $learner->email,
                'company' => $learner->company?->only(['id', 'name']),
                'created_at' => $learner->created_at?->format('Y. m. d.'),
            ]);

        return Inertia::render('learners/Index', [
            'learners' => $learners,
            'filters' => ['q' => $term],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('learners/Create', [
            'companies' => $user->isSuperAdmin()
                ? Company::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'ownCompany' => $user->company?->name,
            'selectedCompanyId' => $user->isSuperAdmin() && $request->integer('company') > 0
                ? $request->integer('company')
                : null,
        ]);
    }

    public function store(StoreLearnerRequest $request, UserProvisioningService $provisioner): RedirectResponse
    {
        $actor = $request->user();

        $company = $actor->isSuperAdmin()
            ? Company::query()->whereKey($request->validated('company_id'))->firstOrFail()
            : $actor->company;

        abort_if($company === null, 403, 'Az adminisztrátorhoz nem tartozik cég.');

        ['user' => $user, 'password' => $password] = $provisioner->create(
            name: $request->validated('name'),
            email: $request->validated('email'),
            role: UserRole::Learner,
            company: $company,
            creator: $actor,
        );

        $this->flashGeneratedPassword($user, $password);
        $this->flashToast("{$user->name} tanuló létrehozva.");

        return to_route('learners.index');
    }

    public function show(Request $request, User $user): Response
    {
        Gate::authorize('manage', $user);
        abort_unless($user->isLearner(), 404);

        $user->load(['company:id,name', 'createdBy:id,name']);

        // Courses available to the learner's company; access is still per learner (ADR-0007).
        $enrolledIds = $user->courses()->pluck('courses.id')->all();
        $courses = Course::query()
            ->manageableBy($request->user())
            ->whereHas('companies', fn ($query) => $query->whereKey($user->company_id ?? 0))
            ->orderBy('title')
            ->get(['id', 'title', 'is_published'])
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'is_published' => $course->is_published,
                'enrolled' => in_array($course->id, $enrolledIds, true),
                'progress' => in_array($course->id, $enrolledIds, true)
                    ? CourseProgress::for($course, $user)->percent()
                    : null,
            ]);

        return Inertia::render('learners/Show', [
            'learner' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company' => $user->company?->only(['id', 'name']),
                'created_by' => $user->createdBy?->name,
                'created_at' => $user->created_at?->format('Y. m. d.'),
            ],
            'courses' => $courses,
        ]);
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('manage', $user);
        abort_unless($user->isLearner(), 404);

        $user->delete();

        $this->flashToast("{$user->name} törölve.");

        return to_route('learners.index');
    }
}
