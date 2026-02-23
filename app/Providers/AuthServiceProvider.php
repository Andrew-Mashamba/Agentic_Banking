<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('manage-staff', function (User $user) {
            return in_array($user->role ?? '', ['admin', 'manager']);
        });

        Gate::define('manage-orders', function (User $user) {
            return in_array($user->role ?? '', ['admin', 'manager', 'waiter']);
        });

        Gate::define('manage-kitchen', function (User $user) {
            return in_array($user->role ?? '', ['admin', 'manager', 'chef']);
        });

        Gate::define('manage-bar', function (User $user) {
            return in_array($user->role ?? '', ['admin', 'manager', 'bartender']);
        });

        Gate::define('access-admin', function (User $user) {
            return ($user->role ?? '') === 'admin';
        });

        Gate::define('access-manager', function (User $user) {
            return in_array($user->role ?? '', ['admin', 'manager']);
        });

        Gate::define('view-reports', function (User $user) {
            return in_array($user->role ?? '', ['admin', 'manager']);
        });

        Gate::define('manage-settings', function (User $user) {
            return ($user->role ?? '') === 'admin';
        });
    }
}
