<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Learners only have their (empty) course list.
        if ($user->isLearner()) {
            return to_route('courses.index');
        }

        $learners = User::query()->manageableLearnersFor($user);

        return Inertia::render('Dashboard', [
            'stats' => [
                'learners' => (clone $learners)->count(),
                'admins' => $user->isSuperAdmin() ? User::query()->where('role', UserRole::Admin)->count() : null,
                'companies' => $user->isSuperAdmin() ? Company::query()->count() : null,
            ],
            'company' => $user->company?->name,
            'recentLearners' => $learners->with('company:id,name')->latest()->take(5)->get()
                ->map(fn (User $learner) => [
                    'id' => $learner->id,
                    'name' => $learner->name,
                    'email' => $learner->email,
                    'company' => $learner->company?->name,
                    'created_at' => $learner->created_at?->format('Y. m. d.'),
                ]),
        ]);
    }
}
