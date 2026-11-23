<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::define(
            'access-developer-settings',
            fn (User $user): bool => (int) $user->role === 8,
        );

        Gate::define(
            'access-super-admin-dashboard',
            fn (User $user): bool => in_array((int) $user->role, [7, 8], true),
        );

        Gate::define(
            'manage-payroll',
            fn (User $user): bool => (int) $user->role >= 5,
        );

        Gate::define(
            'manage-users',
            fn (User $user): bool => in_array((int) $user->role, [7, 8], true),
        );

        Gate::define(
            'export-employees',
            fn (User $user): bool => (int) $user->role >= 6,
        );

        Gate::define(
            'export-employees-khusus',
            fn (User $user): bool => (int) $user->role >= 5,
        );
    }
}
