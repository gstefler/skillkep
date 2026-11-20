<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/horizon')->assertRedirect(route('login'));
    }

    public function test_learners_and_admins_cannot_open_horizon(): void
    {
        $company = Company::factory()->create();

        $this->actingAs(User::factory()->create(['company_id' => $company]))
            ->get('/horizon')
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create(['company_id' => $company]))
            ->get('/horizon')
            ->assertForbidden();
    }

    public function test_superadmins_can_open_horizon(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/horizon')
            ->assertOk();
    }
}
