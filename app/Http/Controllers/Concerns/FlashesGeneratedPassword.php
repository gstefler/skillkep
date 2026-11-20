<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Inertia\Inertia;

trait FlashesGeneratedPassword
{
    /** Show the one-time password banner on the next page. */
    protected function flashGeneratedPassword(User $user, string $password): void
    {
        Inertia::flash('generatedPassword', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $password,
        ]);
    }

    protected function flashToast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
