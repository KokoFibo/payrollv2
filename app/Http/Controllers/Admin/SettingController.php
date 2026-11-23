<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage-payroll');

        return Inertia::render('Settings/Index', [
            'settings' => collect(Setting::query()->orderBy('group')->orderBy('label')->get())
                ->groupBy('group')
                ->map(fn ($items): array => $items
                    ->map(fn (Setting $setting): array => [
                        'key' => $setting->key,
                        'label' => $setting->label,
                        'value' => $setting->value,
                        'type' => $setting->type,
                    ])
                    ->values()
                    ->all())
                ->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage-payroll');

        $validated = $request->validate([
            'general.company_name' => ['required', 'string', 'max:100'],
            'general.locale' => ['required', 'in:id,en'],
            'general.timezone' => ['required', 'timezone'],
            'payroll.cutoff_start_day' => ['required', 'integer', 'between:1,31'],
            'payroll.cutoff_end_day' => ['required', 'integer', 'between:1,31'],
            'payroll.pay_date' => ['required', 'integer', 'between:1,31'],
            'attendance.work_start_time' => ['required', 'date_format:H:i'],
            'attendance.late_tolerance_minutes' => ['required', 'integer', 'between:0,120'],
        ]);

        foreach ($validated as $group => $values) {
            Setting::putMany($group, $values);
        }

        Cache::forget('app.company_name');

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Pengaturan disimpan',
            'message' => 'Konfigurasi aplikasi berhasil diperbarui.',
        ]);
    }
}
