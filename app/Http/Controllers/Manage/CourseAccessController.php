<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Concerns\FlashesGeneratedPassword;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Course access (ADR-0007): which companies a course is available to, and
 * which of their learners are enrolled. Availability never enrols anyone.
 */
class CourseAccessController extends Controller
{
    use FlashesGeneratedPassword;

    /**
     * Set the companies the course is available to (superadmin). Learners of
     * a company that loses the course lose their enrolment too.
     */
    public function companies(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('administer', $course);

        $companyIds = array_map('intval', $request->validate([
            'company_ids' => ['present', 'array'],
            'company_ids.*' => ['integer', 'distinct', 'exists:companies,id'],
        ])['company_ids']);

        $removed = DB::transaction(function () use ($course, $companyIds): int {
            $detached = $course->companies()->sync($companyIds)['detached'];

            if ($detached === []) {
                return 0;
            }

            $orphanedLearnerIds = $course->learners()->whereIn('users.company_id', $detached)->pluck('users.id');

            return $course->learners()->detach($orphanedLearnerIds);
        });

        $this->flashToast($removed > 0
            ? "A cégek frissültek. {$removed} tanuló hozzáférése megszűnt."
            : 'A cégek frissültek.');

        return back();
    }

    /**
     * Enrol exactly the given learners, within the actor's reach: the learners
     * of the companies the course is available to (an admin: own company only).
     * Learners outside that reach are left as they are.
     */
    public function learners(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('manage', $course);

        $actor = $request->user();
        $learnerIds = array_map('intval', $request->validate([
            'learner_ids' => ['present', 'array'],
            'learner_ids.*' => ['integer', 'distinct'],
        ])['learner_ids']);

        $reachableIds = $course->enrollableLearnersFor($actor)->pluck('id')->all();

        if (array_diff($learnerIds, $reachableIds) !== []) {
            throw ValidationException::withMessages([
                'learner_ids' => 'Csak olyan tanuló iratkoztatható be, akinek a cégéhez a kurzus hozzá van rendelve.',
            ]);
        }

        $enrolledIds = $course->learners()->whereIn('users.id', $reachableIds)->pluck('users.id')->all();
        $toAttach = array_values(array_diff($learnerIds, $enrolledIds));
        $toDetach = array_values(array_diff($enrolledIds, $learnerIds));

        DB::transaction(function () use ($course, $actor, $toAttach, $toDetach): void {
            $course->learners()->detach($toDetach);
            $course->learners()->attach(
                collect($toAttach)->mapWithKeys(fn (int $id) => [$id => ['enrolled_by_user_id' => $actor->id]])->all(),
            );
        });

        $this->flashToast(match (true) {
            $toAttach === [] && $toDetach === [] => 'Nem változott a hozzáférés.',
            default => sprintf('Hozzáférés frissítve: %d új, %d megszüntetve.', count($toAttach), count($toDetach)),
        });

        return back();
    }

    /** Enrol one learner (from the learner's page). */
    public function enrol(Request $request, Course $course, User $user): RedirectResponse
    {
        Gate::authorize('manage', $course);

        abort_unless($course->enrollableLearnersFor($request->user())->whereKey($user->id)->exists(), 422,
            'A tanuló cégéhez ez a kurzus nincs hozzárendelve.');

        $course->learners()->syncWithoutDetaching([$user->id => ['enrolled_by_user_id' => $request->user()->id]]);

        $this->flashToast("{$user->name} hozzáférést kapott: {$course->title}.");

        return back();
    }

    /** Remove one learner's access (from the learner's page). */
    public function unenrol(Request $request, Course $course, User $user): RedirectResponse
    {
        Gate::authorize('manage', $course);
        Gate::authorize('manage', $user);

        $course->learners()->detach($user->id);

        $this->flashToast("{$user->name} hozzáférése megszűnt: {$course->title}.");

        return back();
    }
}
