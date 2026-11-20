<?php

namespace App\Enums;

enum UserRole: string
{
    case Learner = 'learner';
    case Admin = 'admin';
    case SuperAdmin = 'superadmin';

    public function label(): string
    {
        return match ($this) {
            self::Learner => 'Tanuló',
            self::Admin => 'Adminisztrátor',
            self::SuperAdmin => 'Szuperadminisztrátor',
        };
    }
}
