<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_from_home_to_login()
    {
        $this->get(route('home'))->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_land_on_the_dashboard()
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('home'))->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk();
    }
}
