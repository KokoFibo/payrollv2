<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarOverride;
use App\Support\Attendance\AttendanceCalendarRecalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CalendarOverrideController extends Controller
{
    public function store(
        Request $request,
        AttendanceCalendarRecalculator $calendarRecalculator,
    ): RedirectResponse {
        Gate::authorize('access-developer-settings');

        $validated = $this->validatedStoreData($request);
        $companyIds = $validated['company_ids'];
        unset($validated['company_ids']);
        $calendarDate = CarbonImmutable::parse($validated['calendar_date']);
        $recalculatedCount = 0;

        DB::transaction(function () use (
            $companyIds,
            $validated,
            $calendarDate,
            $request,
            $calendarRecalculator,
            &$recalculatedCount,
        ): void {
            foreach ($companyIds as $companyId) {
                CalendarOverride::query()->updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'directorate_id' => $validated['directorate_id'] ?? null,
                        'department_id' => $validated['department_id'] ?? null,
                        'calendar_date' => $validated['calendar_date'],
                    ],
                    $validated,
                );
            }

            $recalculatedCount = $calendarRecalculator->recalculateDateRange(
                $calendarDate,
                $calendarDate,
                $companyIds,
                $request->user()->id,
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Pengecualian kalender ditambahkan',
            'message' => count($companyIds).' company berhasil diperbarui kalendernya. '
                .$recalculatedCount.' attendance dihitung ulang.',
        ]);
    }

    public function update(
        Request $request,
        CalendarOverride $calendarOverride,
        AttendanceCalendarRecalculator $calendarRecalculator,
    ): RedirectResponse {
        Gate::authorize('access-developer-settings');

        $validated = $this->validatedUpdateData($request);
        $oldCompanyId = $calendarOverride->company_id;
        $oldCalendarDate = $calendarOverride->calendar_date->toImmutable();
        $newCalendarDate = CarbonImmutable::parse($validated['calendar_date']);
        $recalculatedCount = 0;

        DB::transaction(function () use (
            $calendarOverride,
            $validated,
            $oldCompanyId,
            $oldCalendarDate,
            $newCalendarDate,
            $request,
            $calendarRecalculator,
            &$recalculatedCount,
        ): void {
            $calendarOverride->update($validated);
            $recalculatedCount += $calendarRecalculator->recalculateDateRange(
                $oldCalendarDate,
                $oldCalendarDate,
                [$oldCompanyId],
                $request->user()->id,
            );

            if (
                $oldCompanyId !== $calendarOverride->company_id
                || $oldCalendarDate->toDateString() !== $newCalendarDate->toDateString()
            ) {
                $recalculatedCount += $calendarRecalculator->recalculateDateRange(
                    $newCalendarDate,
                    $newCalendarDate,
                    [$calendarOverride->company_id],
                    $request->user()->id,
                );
            }
        });

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Pengecualian kalender diperbarui',
            'message' => 'Perubahan kalender kerja perusahaan berhasil disimpan. '
                .$recalculatedCount.' attendance dihitung ulang.',
        ]);
    }

    public function destroy(
        Request $request,
        CalendarOverride $calendarOverride,
        AttendanceCalendarRecalculator $calendarRecalculator,
    ): RedirectResponse {
        Gate::authorize('access-developer-settings');

        $companyId = $calendarOverride->company_id;
        $calendarDate = $calendarOverride->calendar_date->toImmutable();
        $recalculatedCount = 0;

        DB::transaction(function () use (
            $calendarOverride,
            $companyId,
            $calendarDate,
            $request,
            $calendarRecalculator,
            &$recalculatedCount,
        ): void {
            $calendarOverride->delete();
            $recalculatedCount = $calendarRecalculator->recalculateDateRange(
                $calendarDate,
                $calendarDate,
                [$companyId],
                $request->user()->id,
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Pengecualian kalender dihapus',
            'message' => 'Karyawan pada scope tersebut kembali memakai kalender global. '
                .$recalculatedCount.' attendance dihitung ulang.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedStoreData(Request $request): array
    {
        return $request->validate([
            'company_ids' => ['required', 'array', 'min:1'],
            'company_ids.*' => ['required', 'integer', 'distinct', 'exists:companies,id'],
            ...$this->calendarRules(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedUpdateData(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            ...$this->calendarRules(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function calendarRules(): array
    {
        return [
            'directorate_id' => ['nullable', 'integer', 'exists:directorates,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'calendar_date' => ['required', 'date'],
            'name' => ['nullable', 'string', 'max:150'],
            'is_holiday_pay' => ['required', 'boolean'],
            'schedule_day_type' => [
                'nullable',
                Rule::in(array_keys(CalendarOverride::DAY_TYPES)),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
