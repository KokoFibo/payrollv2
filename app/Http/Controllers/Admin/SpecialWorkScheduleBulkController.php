<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpecialWorkSchedule;
use App\Support\Attendance\AttendanceCalendarRecalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SpecialWorkScheduleBulkController extends Controller
{
    public function __invoke(
        Request $request,
        AttendanceCalendarRecalculator $calendarRecalculator,
    ): RedirectResponse {
        Gate::authorize('access-developer-settings');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.enabled' => ['required', 'boolean'],
            'schedules.*.schedule_profile' => [
                'required',
                Rule::in(array_keys(SpecialWorkSchedule::PROFILES)),
            ],
            'schedules.*.shift' => [
                'required',
                Rule::in(array_keys(SpecialWorkSchedule::SHIFTS)),
            ],
            'schedules.*.check_in' => ['required', 'date_format:H:i'],
            'schedules.*.check_out' => ['required', 'date_format:H:i'],
            'schedules.*.check_out_day_offset' => ['required', 'integer', 'between:0,2'],
            'schedules.*.standard_work_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'schedules.*.expected_punch_count' => ['required', 'integer', 'between:0,6'],
        ]);
        $enabledSchedules = collect($validated['schedules'])
            ->filter(fn (array $schedule): bool => (bool) $schedule['enabled'])
            ->values();

        $duplicateScope = $enabledSchedules
            ->countBy(fn (array $schedule): string => $schedule['schedule_profile'].':'.$schedule['shift'])
            ->contains(fn (int $count): bool => $count > 1);

        if ($duplicateScope) {
            throw ValidationException::withMessages([
                'schedules' => 'Setiap kombinasi profile dan shift hanya boleh dipilih satu kali.',
            ]);
        }

        $createdCount = 0;
        $deletedCount = 0;
        $activeScopes = $enabledSchedules
            ->mapWithKeys(fn (array $schedule): array => [
                $schedule['schedule_profile'].':'.$schedule['shift'] => true,
            ]);

        DB::transaction(function () use (
            $validated,
            $enabledSchedules,
            $activeScopes,
            &$createdCount,
            &$deletedCount,
        ): void {
            $existingSchedules = SpecialWorkSchedule::query()
                ->where('name', $validated['name'])
                ->whereDate('starts_on', $validated['starts_on'])
                ->whereDate('ends_on', $validated['ends_on'])
                ->whereNull('company_id')
                ->whereNull('directorate_id')
                ->whereNull('department_id')
                ->whereNull('day_type')
                ->get();

            foreach ($existingSchedules as $existingSchedule) {
                $scope = $existingSchedule->schedule_profile.':'.$existingSchedule->shift;

                if (! $activeScopes->has($scope)) {
                    $existingSchedule->delete();
                    $deletedCount++;
                }
            }

            foreach ($enabledSchedules as $schedule) {
                $specialSchedule = SpecialWorkSchedule::query()->updateOrCreate(
                    [
                        'name' => $validated['name'],
                        'company_id' => null,
                        'directorate_id' => null,
                        'department_id' => null,
                        'starts_on' => $validated['starts_on'],
                        'ends_on' => $validated['ends_on'],
                        'schedule_profile' => $schedule['schedule_profile'],
                        'day_type' => null,
                        'shift' => $schedule['shift'],
                    ],
                    [
                        'check_in' => $schedule['check_in'],
                        'check_out' => $schedule['check_out'],
                        'check_out_day_offset' => $schedule['check_out_day_offset'],
                        'standard_work_hours' => $schedule['standard_work_hours'],
                        'expected_punch_count' => $schedule['expected_punch_count'],
                        'notes' => $validated['name'],
                    ],
                );
                $createdCount += $specialSchedule->wasRecentlyCreated ? 1 : 0;
            }
        });

        $recalculatedCount = $calendarRecalculator->recalculateDateRange(
            CarbonImmutable::parse($validated['starts_on']),
            CarbonImmutable::parse($validated['ends_on']),
            null,
            $request->user()->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Jadwal serentak disimpan',
            'message' => $enabledSchedules->count()
                .' shift diperbarui, '
                .$createdCount
                .' jadwal baru dibuat, dan '
                .$deletedCount
                .' jadwal tidak aktif dihapus, serta '
                .$recalculatedCount
                .' attendance dihitung ulang.',
        ]);
    }
}
