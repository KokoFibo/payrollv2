<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NationalHoliday;
use App\Support\Attendance\AttendanceCalendarRecalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class NationalHolidayController extends Controller
{
    public function store(
        Request $request,
        AttendanceCalendarRecalculator $calendarRecalculator,
    ): RedirectResponse {
        Gate::authorize('access-developer-settings');

        $validated = $this->validatedData($request);
        $recalculatedCount = 0;

        DB::transaction(function () use (
            $validated,
            $request,
            $calendarRecalculator,
            &$recalculatedCount,
        ): void {
            $holiday = NationalHoliday::query()->create($validated);
            $recalculatedCount = $this->recalculateHoliday(
                $holiday,
                $request->user()->id,
                $calendarRecalculator,
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Hari libur ditambahkan',
            'message' => 'Kalender hari libur nasional berhasil diperbarui. '
                .$recalculatedCount.' attendance dihitung ulang.',
        ]);
    }

    public function update(
        Request $request,
        NationalHoliday $nationalHoliday,
        AttendanceCalendarRecalculator $calendarRecalculator,
    ): RedirectResponse {
        Gate::authorize('access-developer-settings');

        $validated = $this->validatedData($request);
        $oldStartsOn = $nationalHoliday->starts_on?->toImmutable();
        $oldEndsOn = $nationalHoliday->ends_on?->toImmutable();
        $recalculatedCount = 0;

        DB::transaction(function () use (
            $nationalHoliday,
            $validated,
            $oldStartsOn,
            $oldEndsOn,
            $request,
            $calendarRecalculator,
            &$recalculatedCount,
        ): void {
            $nationalHoliday->update($validated);
            $recalculatedCount += $this->recalculateRange(
                $oldStartsOn,
                $oldEndsOn,
                $request->user()->id,
                $calendarRecalculator,
            );
            $recalculatedCount += $this->recalculateHoliday(
                $nationalHoliday->refresh(),
                $request->user()->id,
                $calendarRecalculator,
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Hari libur diperbarui',
            'message' => 'Perubahan kalender hari libur berhasil disimpan. '
                .$recalculatedCount.' attendance dihitung ulang.',
        ]);
    }

    public function destroy(
        Request $request,
        NationalHoliday $nationalHoliday,
        AttendanceCalendarRecalculator $calendarRecalculator,
    ): RedirectResponse {
        Gate::authorize('access-developer-settings');

        $oldStartsOn = $nationalHoliday->starts_on?->toImmutable();
        $oldEndsOn = $nationalHoliday->ends_on?->toImmutable();
        $recalculatedCount = 0;

        DB::transaction(function () use (
            $nationalHoliday,
            $oldStartsOn,
            $oldEndsOn,
            $request,
            $calendarRecalculator,
            &$recalculatedCount,
        ): void {
            $nationalHoliday->delete();
            $recalculatedCount = $this->recalculateRange(
                $oldStartsOn,
                $oldEndsOn,
                $request->user()->id,
                $calendarRecalculator,
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Hari libur dihapus',
            'message' => 'Data hari libur berhasil dihapus. '
                .$recalculatedCount.' attendance dihitung ulang.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'total_days' => ['nullable', 'integer', 'min:1', 'max:366'],
        ]);

        $validated['ends_on'] = $validated['ends_on'] ?? $validated['starts_on'];
        $validated['total_days'] = CarbonImmutable::parse($validated['starts_on'])
            ->diffInDays(CarbonImmutable::parse($validated['ends_on'])) + 1;

        return $validated;
    }

    private function recalculateHoliday(
        NationalHoliday $holiday,
        int $recordedBy,
        AttendanceCalendarRecalculator $calendarRecalculator,
    ): int {
        return $this->recalculateRange(
            $holiday->starts_on?->toImmutable(),
            $holiday->ends_on?->toImmutable(),
            $recordedBy,
            $calendarRecalculator,
        );
    }

    private function recalculateRange(
        ?CarbonImmutable $startsOn,
        ?CarbonImmutable $endsOn,
        int $recordedBy,
        AttendanceCalendarRecalculator $calendarRecalculator,
    ): int {
        if ($startsOn === null) {
            return 0;
        }

        return $calendarRecalculator->recalculateDateRange(
            $startsOn,
            $endsOn ?? $startsOn,
            null,
            $recordedBy,
        );
    }
}
