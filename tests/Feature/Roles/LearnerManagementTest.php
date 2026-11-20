<?php

namespace Tests\Feature\Roles;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_learners_in_their_own_company_only(): void
    {
        $own = Company::factory()->create();
        $other = Company::factory()->create();
        $admin = User::factory()->admin()->create(['company_id' => $own->id]);

        // The company_id sent by an admin is ignored.
        $this->actingAs($admin)->post(route('learners.store'), [
            'name' => 'Tóth Anna',
            'email' => 'anna@example.com',
            'company_id' => $other->id,
        ])->assertRedirect(route('learners.index'));

        $learner = User::query()->where('email', 'anna@example.com')->firstOrFail();
        $this->assertTrue($learner->isLearner());
        $this->assertSame($own->id, $learner->company_id);
        $this->assertSame($admin->id, $learner->created_by_user_id);
    }

    public function test_superadmin_must_choose_a_company_for_a_learner(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->post(route('learners.store'), [
            'name' => 'Tóth Anna',
            'email' => 'anna@example.com',
        ])->assertSessionHasErrors('company_id');

        $company = Company::factory()->create();
        $this->actingAs($superAdmin)->post(route('learners.store'), [
            'name' => 'Tóth Anna',
            'email' => 'anna@example.com',
            'company_id' => $company->id,
        ])->assertRedirect(route('learners.index'));

        $this->assertSame($company->id, User::query()->where('email', 'anna@example.com')->value('company_id'));
    }

    public function test_admin_only_sees_learners_of_their_company(): void
    {
        $own = Company::factory()->create();
        $admin = User::factory()->admin()->create(['company_id' => $own->id]);
        User::factory()->count(2)->create(['company_id' => $own->id]);
        User::factory()->count(3)->create(['company_id' => Company::factory()]);

        $this->actingAs($admin)->get(route('learners.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('learners.data', 2));

        $this->actingAs(User::factory()->superAdmin()->create())->get(route('learners.index'))
            ->assertInertia(fn ($page) => $page->has('learners.data', 5));
    }

    public function test_admin_cannot_open_reset_or_delete_a_learner_of_another_company(): void
    {
        $admin = User::factory()->admin()->create(['company_id' => Company::factory()]);
        $foreign = User::factory()->create(['company_id' => Company::factory()]);

        $this->actingAs($admin)->get(route('learners.show', $foreign))->assertForbidden();
        $this->actingAs($admin)->post(route('users.password-reset', $foreign))->assertForbidden();
        $this->actingAs($admin)->delete(route('learners.destroy', $foreign))->assertForbidden();
        $this->assertModelExists($foreign);
    }

    public function test_admin_cannot_reset_the_password_of_another_admin(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->create(['company_id' => $company->id]);
        $colleague = User::factory()->admin()->create(['company_id' => $company->id]);

        $this->actingAs($admin)->post(route('users.password-reset', $colleague))->assertForbidden();
    }

    public function test_admin_resets_and_deletes_own_company_learner(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->create(['company_id' => $company->id]);
        $learner = User::factory()->create(['company_id' => $company->id]);
        $oldHash = $learner->password;

        $this->actingAs($admin)->post(route('users.password-reset', $learner))->assertRedirect();
        $this->assertNotSame($oldHash, $learner->fresh()->password);

        $this->actingAs($admin)->delete(route('learners.destroy', $learner))
            ->assertRedirect(route('learners.index'));
        $this->assertModelMissing($learner);
    }

    public function test_learner_search_filters_by_name_and_email(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->create(['company_id' => $company->id]);
        User::factory()->create(['company_id' => $company->id, 'name' => 'Tóth Anna']);
        User::factory()->create(['company_id' => $company->id, 'name' => 'Kiss Éva']);

        $this->actingAs($admin)->get(route('learners.index', ['q' => 'anna']))
            ->assertInertia(fn ($page) => $page->has('learners.data', 1)->where('learners.data.0.name', 'Tóth Anna'));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->create(['company_id' => $company->id]);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)->post(route('learners.store'), ['name' => 'A B', 'email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');
    }
}
