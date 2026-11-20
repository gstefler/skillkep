<?php

namespace Tests\Feature\Courses;

use App\Models\Company;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR-0007: a course is made available to companies, and each learner must
 * still be enrolled one by one.
 */
class CourseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_making_a_course_available_to_a_company_enrols_nobody(): void
    {
        $company = Company::factory()->create();
        $learner = User::factory()->create(['company_id' => $company->id]);
        $course = Course::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('manage.courses.companies.update', $course), ['company_ids' => [$company->id]])
            ->assertRedirect();

        $this->assertTrue($course->isAvailableTo($company->id));
        $this->assertSame(0, $course->learners()->count());

        $this->actingAs($learner)->get(route('courses.show', $course))->assertForbidden();
        $this->actingAs($learner)->get(route('courses.index'))
            ->assertInertia(fn ($page) => $page->where('courses', []));
    }

    public function test_an_enrolled_learner_sees_and_opens_the_published_course(): void
    {
        $learner = User::factory()->create(['company_id' => Company::factory()]);
        $course = Course::factory()->enrolling($learner)->create();
        Module::factory()->for($course)->create(['order' => 1]);

        $this->actingAs($learner)->get(route('courses.index'))
            ->assertInertia(fn ($page) => $page
                ->component('courses/Index')
                ->has('courses', 1)
                ->where('courses.0.slug', $course->slug));

        $this->actingAs($learner)->get(route('courses.show', $course))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('courses/Show')->has('modules', 1));
    }

    public function test_an_unpublished_course_stays_hidden_even_from_enrolled_learners(): void
    {
        $learner = User::factory()->create(['company_id' => Company::factory()]);
        $course = Course::factory()->unpublished()->enrolling($learner)->create();

        $this->actingAs($learner)->get(route('courses.show', $course))->assertForbidden();
        $this->actingAs($learner)->get(route('courses.index'))
            ->assertInertia(fn ($page) => $page->where('courses', []));
    }

    public function test_an_admin_enrols_only_learners_of_the_own_company(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $admin = User::factory()->admin()->create(['company_id' => $company->id]);
        $own = User::factory()->create(['company_id' => $company->id]);
        $foreign = User::factory()->create(['company_id' => $other->id]);
        $course = Course::factory()->create();
        $course->companies()->attach([$company->id, $other->id]);

        $this->actingAs($admin)
            ->put(route('manage.courses.learners.update', $course), ['learner_ids' => [$own->id, $foreign->id]])
            ->assertSessionHasErrors('learner_ids');
        $this->assertSame(0, $course->learners()->count());

        $this->actingAs($admin)
            ->put(route('manage.courses.learners.update', $course), ['learner_ids' => [$own->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame([$own->id], $course->learners()->pluck('users.id')->all());
        $this->assertSame($admin->id, $course->learners()->first()->pivot->enrolled_by_user_id);
    }

    public function test_an_admin_saving_the_list_leaves_other_companies_enrolments_alone(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $admin = User::factory()->admin()->create(['company_id' => $company->id]);
        $own = User::factory()->create(['company_id' => $company->id]);
        $foreign = User::factory()->create(['company_id' => $other->id]);
        $course = Course::factory()->create();
        $course->companies()->attach([$company->id, $other->id]);
        $course->learners()->attach([$own->id, $foreign->id]);

        $this->actingAs($admin)
            ->put(route('manage.courses.learners.update', $course), ['learner_ids' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame([$foreign->id], $course->learners()->pluck('users.id')->all());
    }

    public function test_learners_of_a_company_without_the_course_cannot_be_enrolled(): void
    {
        $learner = User::factory()->create(['company_id' => Company::factory()]);
        $course = Course::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('manage.courses.learners.update', $course), ['learner_ids' => [$learner->id]])
            ->assertSessionHasErrors('learner_ids');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('manage.courses.learners.store', [$course, $learner]))
            ->assertStatus(422);

        $this->assertSame(0, $course->learners()->count());
    }

    public function test_removing_a_company_ends_its_learners_enrolment(): void
    {
        $keep = Company::factory()->create();
        $drop = Company::factory()->create();
        $staying = User::factory()->create(['company_id' => $keep->id]);
        $leaving = User::factory()->create(['company_id' => $drop->id]);
        $course = Course::factory()->create();
        $course->companies()->attach([$keep->id, $drop->id]);
        $course->learners()->attach([$staying->id, $leaving->id]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('manage.courses.companies.update', $course), ['company_ids' => [$keep->id]])
            ->assertRedirect();

        $this->assertSame([$staying->id], $course->learners()->pluck('users.id')->all());
    }

    public function test_an_admin_sees_only_the_courses_of_the_own_company_and_cannot_administer_them(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->create(['company_id' => $company->id]);
        $visible = Course::factory()->availableTo($company)->create();
        $hidden = Course::factory()->create();

        $this->actingAs($admin)->get(route('manage.courses.index'))
            ->assertInertia(fn ($page) => $page
                ->component('manage/courses/Index')
                ->has('courses.data', 1)
                ->where('courses.data.0.id', $visible->id)
                ->where('canUpload', false));

        $this->actingAs($admin)->get(route('manage.courses.show', $hidden))->assertForbidden();
        $this->actingAs($admin)->get(route('manage.courses.show', $visible))
            ->assertInertia(fn ($page) => $page->where('canAdminister', false)->where('companies', []));

        $this->actingAs($admin)->patch(route('manage.courses.publish', $visible))->assertForbidden();
        $this->actingAs($admin)->delete(route('manage.courses.destroy', $visible))->assertForbidden();
        $this->actingAs($admin)->put(route('manage.courses.companies.update', $visible), ['company_ids' => []])->assertForbidden();
    }

    public function test_learners_cannot_reach_course_management(): void
    {
        $learner = User::factory()->create(['company_id' => Company::factory()]);
        $course = Course::factory()->enrolling($learner)->create();

        $this->actingAs($learner)->get(route('manage.courses.index'))->assertForbidden();
        $this->actingAs($learner)->get(route('manage.courses.show', $course))->assertForbidden();
        $this->actingAs($learner)->put(route('manage.courses.learners.update', $course), ['learner_ids' => []])->assertForbidden();
    }

    public function test_the_learner_page_grants_and_revokes_access(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->create(['company_id' => $company->id]);
        $learner = User::factory()->create(['company_id' => $company->id]);
        $course = Course::factory()->availableTo($company)->create();

        $this->actingAs($admin)->get(route('learners.show', $learner))
            ->assertInertia(fn ($page) => $page->has('courses', 1)->where('courses.0.enrolled', false));

        $this->actingAs($admin)->post(route('manage.courses.learners.store', [$course, $learner]))->assertRedirect();
        $this->assertTrue($course->learners()->whereKey($learner->id)->exists());

        $this->actingAs($admin)->delete(route('manage.courses.learners.destroy', [$course, $learner]))->assertRedirect();
        $this->assertFalse($course->learners()->whereKey($learner->id)->exists());
    }

    public function test_publishing_toggles_and_sequential_modules_chain_the_modules(): void
    {
        $course = Course::factory()->unpublished()->create();
        $modules = collect([1, 2, 3])->map(fn (int $order) => Module::factory()->for($course)->create(['order' => $order]));
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->patch(route('manage.courses.publish', $course))->assertRedirect();
        $this->assertTrue($course->fresh()->is_published);

        $this->actingAs($superAdmin)->patch(route('manage.courses.sequential-modules', $course))->assertRedirect();
        $this->assertSame(
            [null, $modules[0]->id, $modules[1]->id],
            $course->modules()->pluck('prerequisite_module_id')->all(),
        );

        $this->actingAs($superAdmin)->patch(route('manage.courses.sequential-modules', $course))->assertRedirect();
        $this->assertSame([null, null, null], $course->modules()->pluck('prerequisite_module_id')->all());
    }
}
