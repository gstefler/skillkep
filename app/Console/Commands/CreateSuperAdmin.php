<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserProvisioningService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

#[Signature('user:create-superadmin {--name= : Full name (family name first)} {--email= : Email address}')]
#[Description('Create a superadmin with a generated password, which is printed once and emailed')]
class CreateSuperAdmin extends Command
{
    public function handle(UserProvisioningService $provisioner): int
    {
        $name = $this->option('name') ?: text(label: 'Név', required: true);

        $email = $this->option('email') ?: text(
            label: 'E-mail-cím',
            required: true,
            validate: fn (string $value) => $this->validateEmail($value),
        );

        if ($error = $this->validateEmail((string) $email)) {
            $this->error($error);

            return self::FAILURE;
        }

        ['user' => $user, 'password' => $password] = $provisioner->create(
            name: $name,
            email: $email,
            role: UserRole::SuperAdmin,
        );

        $this->newLine();
        $this->info("Superadmin created: {$user->name} <{$user->email}>");
        $this->warn('Temporary password (shown only once, save it now):');
        $this->line("  {$password}");
        $this->newLine();

        return self::SUCCESS;
    }

    private function validateEmail(string $value): ?string
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Must be a valid email address.';
        }

        if (User::query()->where('email', $value)->exists()) {
            return 'A user with that email already exists.';
        }

        return null;
    }
}
