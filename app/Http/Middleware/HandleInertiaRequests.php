<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only([
                    'id',
                    'name',
                    'email',
                    'role',
                    'email_verified_at',
                ]),
                'roleName' => $request->user()?->assignedRole?->name,
                'can' => [
                    'superAdmin' => $request->user()?->can('access-super-admin-dashboard') ?? false,
                    'managePayroll' => $request->user()?->can('manage-payroll') ?? false,
                    'manageUsers' => $request->user()?->can('manage-users') ?? false,
                    'exportEmployees' => $request->user()?->can('export-employees') ?? false,
                    'exportEmployeesKhusus' => $request->user()?->can('export-employees-khusus') ?? false,
                    'developer' => $request->user()?->can('access-developer-settings') ?? false,
                ],
            ],
            'flash' => [
                'toast' => fn () => $request->session()->get('toast'),
            ],
            'app' => [
                'name' => config('app.name', 'Payroll'),
                'companyName' => Cache::remember(
                    'app.company_name',
                    now()->addMinutes(10),
                    fn (): string => Setting::query()->where('key', 'company_name')->value('value')
                        ?? config('payroll.company_name', 'Payroll Workspace'),
                ),
            ],
        ];
    }
}
