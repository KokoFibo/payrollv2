<?php

namespace App\Providers;

use App\Models\AdminVisibilitySetting;
use App\Models\AttendanceRecord;
use App\Models\Karyawan;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Observers\PayrollAuditObserver;
use App\Support\Attendance\AttendanceScheduleResolver;
use App\Support\Attendance\CalendarOverrideResolver;
use App\Support\Attendance\NationalHolidayResolver;
use App\Support\Attendance\SpecialWorkScheduleResolver;
use Illuminate\Contracts\Foundation\Application;
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
        $this->app->bind(
            AttendanceScheduleResolver::class,
            fn (Application $app): AttendanceScheduleResolver => new AttendanceScheduleResolver(
                $app->make(SpecialWorkScheduleResolver::class),
                $app->make(NationalHolidayResolver::class),
                $app->make(CalendarOverrideResolver::class),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Karyawan::observe(PayrollAuditObserver::class);
        AttendanceRecord::observe(PayrollAuditObserver::class);
        PayrollPeriod::observe(PayrollAuditObserver::class);
        PayrollItem::observe(PayrollAuditObserver::class);
        Setting::observe(PayrollAuditObserver::class);
        AdminVisibilitySetting::observe(PayrollAuditObserver::class);

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
            'access-payroll-reports',
            fn (User $user): bool => (int) $user->role >= 6,
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
