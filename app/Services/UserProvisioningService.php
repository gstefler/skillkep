<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Notifications\WelcomeWithPasswordNotification;
use Illuminate\Support\Str;

class UserProvisioningService
{
    /**
     * Create an account with a generated password and email it to the user.
     *
     * @return array{user: User, password: string}
     */
    public function create(
        string $name,
        string $email,
        UserRole $role,
        ?Company $company = null,
        ?User $creator = null,
    ): array {
        $password = Str::password(16, symbols: false);

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'company_id' => $company?->id,
            'created_by_user_id' => $creator?->id,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->notify(new WelcomeWithPasswordNotification($password));

        return ['user' => $user, 'password' => $password];
    }

    public function resetPassword(User $user): string
    {
        $password = Str::password(16, symbols: false);

        $user->forceFill(['password' => $password])->save();
        $user->notify(new WelcomeWithPasswordNotification($password, isReset: true));

        return $password;
    }
}
