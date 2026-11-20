<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local demo data: every account uses the password "password".
 *
 *   superadmin@example.com   superadmin
 *   admin@example.com        admin of Nagy és Társa Kft.
 *   test@example.com         learner of Nagy és Társa Kft.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = $this->account('Kovács Gábor', 'superadmin@example.com', UserRole::SuperAdmin);

        $companies = collect([
            ['name' => 'Nagy és Társa Kft.', 'email' => 'iroda@nagyestarsa.example', 'vat_id' => '12345678-2-41', 'phone' => '+36 1 234 5678'],
            ['name' => 'Budai Logisztika Zrt.', 'email' => 'info@budailogisztika.example', 'vat_id' => '23456789-2-13', 'phone' => '+36 1 345 6789'],
            ['name' => 'Tisza Élelmiszer Kft.', 'email' => 'hr@tiszaelelmiszer.example', 'vat_id' => '34567890-2-06', 'phone' => '+36 62 456 789'],
        ])->map(fn (array $data) => Company::query()->firstOrCreate(['name' => $data['name']], $data));

        [$nagy, $budai, $tisza] = $companies->all();

        $this->account('Szabó Katalin', 'admin@example.com', UserRole::Admin, $nagy, $superAdmin);
        $this->account('Horváth Péter', 'admin.budai@example.com', UserRole::Admin, $budai, $superAdmin);

        $this->account('Teszt Tanuló', 'test@example.com', UserRole::Learner, $nagy);

        $learners = [
            [$nagy, 'Tóth Anna'], [$nagy, 'Varga Zoltán'], [$nagy, 'Kiss Éva'],
            [$budai, 'Molnár István'], [$budai, 'Farkas Judit'], [$budai, 'Papp Bence'], [$budai, 'Lakatos Mária'],
            [$tisza, 'Juhász László'], [$tisza, 'Oláh Krisztina'],
        ];

        foreach ($learners as $index => [$company, $name]) {
            $this->account($name, 'tanulo'.($index + 1).'@example.com', UserRole::Learner, $company);
        }
    }

    private function account(string $name, string $email, UserRole $role, ?Company $company = null, ?User $creator = null): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->fill([
            'name' => $name,
            'password' => 'password',
            'role' => $role,
            'company_id' => $company?->id,
            'created_by_user_id' => $creator?->id,
        ]);
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()]);
        }
        $user->save();

        return $user;
    }
}
