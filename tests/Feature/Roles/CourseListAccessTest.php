<?php

namespace Tests\Feature\Roles;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseListAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_learners_are_sent_from_the_dashboard_to_their_empty_course_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('courses.index'));
    }

    public function test_learners_see_an_empty_course_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('courses.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('courses/Index')->where('courses', []));
    }

    public function test_learners_cannot_reach_any_management_page(): void
    {
        $learner = User::factory()->create();

        foreach (['learners.index', 'learners.create', 'companies.index', 'admins.index'] as $route) {
            $this->actingAs($learner)->get(route($route))->assertForbidden();
        }
    }

    public function test_admins_see_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard'));
    }
}
