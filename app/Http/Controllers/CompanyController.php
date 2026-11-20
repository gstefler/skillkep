<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\FlashesGeneratedPassword;
use App\Http\Requests\Admin\CompanyRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    use FlashesGeneratedPassword;

    public function index(): Response
    {
        $companies = Company::query()
            ->withCount(['learners', 'admins'])
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'email' => $company->email,
                'vat_id' => $company->vat_id,
                'phone' => $company->phone,
                'learners_count' => $company->learners_count,
                'admins_count' => $company->admins_count,
            ]);

        return Inertia::render('companies/Index', ['companies' => $companies]);
    }

    public function create(): Response
    {
        return Inertia::render('companies/Create');
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = Company::query()->create($request->validated());

        $this->flashToast("{$company->name} létrehozva. Most felvehet hozzá tanulókat.");

        return to_route('companies.show', $company);
    }

    public function show(Company $company): Response
    {
        $members = fn (UserRole $role) => $company->users()
            ->where('role', $role)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->format('Y. m. d.'),
            ]);

        return Inertia::render('companies/Show', [
            'company' => $company->only(['id', 'name', 'email', 'vat_id', 'phone']),
            'learners' => $members(UserRole::Learner),
            'admins' => $members(UserRole::Admin),
        ]);
    }

    public function edit(Company $company): Response
    {
        return Inertia::render('companies/Edit', [
            'company' => $company->only(['id', 'name', 'email', 'vat_id', 'phone']),
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        $this->flashToast("{$company->name} frissítve.");

        return to_route('companies.show', $company);
    }

    public function destroy(Company $company): RedirectResponse
    {
        if ($company->users()->exists()) {
            $this->flashToast('A cég nem törölhető, amíg felhasználók tartoznak hozzá.', 'error');

            return back();
        }

        $company->delete();

        $this->flashToast("{$company->name} törölve.");

        return to_route('companies.index');
    }
}
