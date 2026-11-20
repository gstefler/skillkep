<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\FlashesGeneratedPassword;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\UserProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    use FlashesGeneratedPassword;

    public function index(): Response
    {
        $admins = User::query()
            ->whereIn('role', [UserRole::Admin, UserRole::SuperAdmin])
            ->with('company:id,name')
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (User $admin) => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => ['value' => $admin->role->value, 'label' => $admin->role->label()],
                'company' => $admin->company?->only(['id', 'name']),
                'created_at' => $admin->created_at?->format('Y. m. d.'),
            ]);

        return Inertia::render('admins/Index', ['admins' => $admins]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admins/Create', [
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'selectedCompanyId' => $request->integer('company') ?: null,
        ]);
    }

    public function store(StoreAdminRequest $request, UserProvisioningService $provisioner): RedirectResponse
    {
        ['user' => $user, 'password' => $password] = $provisioner->create(
            name: $request->validated('name'),
            email: $request->validated('email'),
            role: UserRole::Admin,
            company: Company::query()->whereKey($request->validated('company_id'))->firstOrFail(),
            creator: $request->user(),
        );

        $this->flashGeneratedPassword($user, $password);
        $this->flashToast("{$user->name} adminisztrátor létrehozva.");

        return to_route('admins.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('manage', $user);
        abort_unless($user->isAdmin(), 404);

        $user->delete();

        $this->flashToast("{$user->name} törölve.");

        return to_route('admins.index');
    }
}
