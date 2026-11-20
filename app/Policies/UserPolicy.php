<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Whether $actor may view, reset the password of, or delete $target.
     *
     * - Superadmins manage every learner and admin (superadmins are created
     *   with the artisan command only).
     * - Admins manage the learners of their own company.
     */
    public function manage(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return $target->role !== UserRole::SuperAdmin;
        }

        return $actor->isAdmin()
            && $target->isLearner()
            && $actor->company_id !== null
            && $actor->company_id === $target->company_id;
    }
}
