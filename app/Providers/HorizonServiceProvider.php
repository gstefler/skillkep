<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Register the Horizon gate.
     *
     * Outside the local environment only superadmins may open the dashboard.
     * Guests never reach this check: config/horizon.php adds the `auth`
     * middleware, which sends them to the login page.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => $user?->isSuperAdmin() ?? false);
    }
}
