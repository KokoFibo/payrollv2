<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpecialWorkSchedule;
use App\Support\Attendance\AttendanceCalendarRecalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SpecialWorkScheduleController extends Controller
{
    public function __construct(
        private AttendanceCalendarRecalculator $calendarRecalculator,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('access-developer-settings');

        $specialWorkSchedule = SpecialWorkSchedule::query()->create($this->validatedData($request));
        $recalculatedCount = $this->recalculateFor(
            $specialWorkSchedule,
            $request->user()->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Jadwal khusus ditambahkan',
            'message' => 'Periode jadwal kerja khusus berhasil dibuat. '
                .$recalculatedCount.' attendance dihitung ulang.',
        ]);
    }

    public function update(Request $request, SpecialWorkSchedule $specialWorkSchedule): RedirectResponse
    {
        Gate::authorize('access-developer-settings');

        $oldStartsOn = $specialWorkSchedule->starts_on->toImmutable();
        $oldEndsOn = $specialWorkSchedule->ends_on->toImmutable();
        $oldCompanyId = $specialWorkSchedule->company_id;
        $specialWorkSchedule->update($this->validatedData($request));
        $recalculatedCount = $this->calendarRecalculator->recalculateDateRange(
            $oldStartsOn->lessThan($specialWorkSchedule->starts_on)
                ? $oldStartsOn
                : $specialWorkSchedule->starts_on->toImmutable(),
            $oldEndsOn->greaterThan($specialWorkSchedule->ends_on)
                ? $oldEndsOn
                : $specialWorkSchedule->ends_on->toImmutable(),
            $this->mergedCompanyIds($oldCompanyId, $specialWorkSchedule->company_id),
            $request->user()->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Jadwal khusus diperbarui',
            'message' => 'Perubahan jadwal kerja khusus berhasil disimpan. '
                .$recalculatedCount.' attendance dihitung ulang.',
        ]);
    }

    public function destroy(SpecialWorkSchedule $specialWorkSchedule): RedirectResponse
    {
        Gate::authorize('access-developer-settings');

        $startsOn = $specialWorkSchedule->starts_on->toImmutable();
        $endsOn = $specialWorkSchedule->ends_on->toImmutable();
        $companyIds = $this->companyIds($specialWorkSchedule->company_id);
        $specialWorkSchedule->delete();
        $recalculatedCount = $this->calendarRecalculator->recalculateDateRange(
            $startsOn,
            $endsOn,
            $companyIds,
            request()->user()->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Jadwal khusus dihapus',
            'message' => 'Jadwal kerja khusus berhasil dihapus. '
                .$recalculatedCount.' attendance dihitung ulang.',
        ]);
    }

    private function recalculateFor(
        SpecialWorkSchedule $specialWorkSchedule,
        int $recordedBy,
    ): int {
        return $this->calendarRecalculator->recalculateDateRange(
            $specialWorkSchedule->starts_on->toImmutable(),
            $specialWorkSchedule->ends_on->toImmutable(),
            $this->companyIds($specialWorkSchedule->company_id),
            $recordedBy,
        );
    }

    /**
     * @return list<int>|null
     */
    private function companyIds(?int $companyId): ?array
    {
        return $companyId === null ? null : [$companyId];
    }

    /**
     * @return list<int>|null
     */
    private function mergedCompanyIds(?int $oldCompanyId, ?int $newCompanyId): ?array
    {
        if ($oldCompanyId === null || $newCompanyId === null) {
            return null;
        }

        return array_values(array_unique([$oldCompanyId, $newCompanyId]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'directorate_id' => ['nullable', 'integer', 'exists:directorates,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'schedule_profile' => [
                'nullable',
                Rule::in(array_keys(SpecialWorkSchedule::PROFILES)),
            ],
            'day_type' => [
                'nullable',
                Rule::in(array_keys(SpecialWorkSchedule::DAY_TYPES)),
            ],
            'shift' => [
                'nullable',
                Rule::in(array_keys(SpecialWorkSchedule::SHIFTS)),
            ],
            'check_in' => ['required', 'date_format:H:i'],
            'check_out' => ['required', 'date_format:H:i'],
            'check_out_day_offset' => ['required', 'integer', 'between:0,2'],
            'standard_work_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'expected_punch_count' => ['required', 'integer', 'between:0,6'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
