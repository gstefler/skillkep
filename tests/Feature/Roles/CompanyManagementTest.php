<?php

namespace Tests\Feature\Roles;

use App\Models\Company;
use App\Models\User;
use App\Notifications\WelcomeWithPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_cannot_manage_companies_or_admins(): void
    {
        $admin = User::factory()->admin()->create(['company_id' => Company::factory()]);

        $this->actingAs($admin)->get(route('companies.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('companies.store'), [])->assertForbidden();
        $this->actingAs($admin)->get(route('admins.index'))->assertForbidden();
    }

    public function test_superadmin_creates_updates_and_deletes_a_company(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->post(route('companies.store'), [
            'name' => 'Nagy Kft.',
            'email' => 'iroda@nagy.example',
            'vat_id' => '12345678-2-41',
        ])->assertRedirect();

        $company = Company::query()->where('name', 'Nagy Kft.')->firstOrFail();

        $this->actingAs($superAdmin)
            ->put(route('companies.update', $company), ['name' => 'Nagy Zrt.', 'email' => 'iroda@nagy.example'])
            ->assertRedirect(route('companies.show', $company));
        $this->assertSame('Nagy Zrt.', $company->fresh()->name);

        $this->actingAs($superAdmin)->delete(route('companies.destroy', $company))
            ->assertRedirect(route('companies.index'));
        $this->assertModelMissing($company);
    }

    public function test_company_with_users_cannot_be_deleted(): void
    {
        $company = Company::factory()->create();
        User::factory()->create(['company_id' => $company->id]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('companies.destroy', $company))
            ->assertRedirect();

        $this->assertModelExists($company);
    }

    public function test_company_page_lists_its_learners_and_admins(): void
    {
        $company = Company::factory()->create();
        User::factory()->count(2)->create(['company_id' => $company->id]);
        User::factory()->admin()->create(['company_id' => $company->id]);
        User::factory()->create(['company_id' => Company::factory()]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('companies.show', $company))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('companies/Show')
                ->has('learners', 2)
                ->has('admins', 1));
    }

    public function test_superadmin_creates_a_company_admin_and_the_password_is_emailed(): void
    {
        Notification::fake();
        $company = Company::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admins.store'), [
                'name' => 'Szabó Katalin',
                'email' => 'katalin@nagy.example',
                'company_id' => $company->id,
            ])->assertRedirect(route('admins.index'));

        $admin = User::query()->where('email', 'katalin@nagy.example')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertSame($company->id, $admin->company_id);
        Notification::assertSentTo($admin, WelcomeWithPasswordNotification::class);
    }

    public function test_admin_company_is_required(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admins.store'), ['name' => 'X Y', 'email' => 'x@y.example'])
            ->assertSessionHasErrors('company_id');
    }

    public function test_superadmin_cannot_delete_another_superadmin_through_the_ui(): void
    {
        $other = User::factory()->superAdmin()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('admins.destroy', $other))
            ->assertForbidden();
    }
}
