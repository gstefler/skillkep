<?php

namespace Tests\Feature\Roles;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_superadmin_that_can_log_in_with_the_printed_password(): void
    {
        $this->artisan('user:create-superadmin', ['--name' => 'Kovács Gábor', '--email' => 'gabor@example.com'])
            ->expectsOutputToContain('Superadmin created')
            ->assertSuccessful();

        $user = User::query()->where('email', 'gabor@example.com')->firstOrFail();
        $this->assertSame(UserRole::SuperAdmin, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->company_id);
    }

    public function test_it_refuses_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'gabor@example.com']);

        $this->artisan('user:create-superadmin', ['--name' => 'Kovács Gábor', '--email' => 'gabor@example.com'])
            ->assertFailed();

        $this->assertSame(1, User::query()->count());
    }
}
