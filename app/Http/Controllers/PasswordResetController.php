<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FlashesGeneratedPassword;
use App\Models\User;
use App\Services\UserProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PasswordResetController extends Controller
{
    use FlashesGeneratedPassword;

    public function store(User $user, UserProvisioningService $provisioner): RedirectResponse
    {
        Gate::authorize('manage', $user);

        $this->flashGeneratedPassword($user, $provisioner->resetPassword($user));
        $this->flashToast("{$user->name} jelszava visszaállítva.");

        return back();
    }
}
