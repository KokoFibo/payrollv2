<?php

namespace App\Support\Attendance;

use App\Models\AttendanceRecord;
use Carbon\CarbonImmutable;

class AttendanceCalendarRecalculator
{
    public function __construct(
        private AttendanceRecordEditor $recordEditor,
    ) {}

    /**
     * @param  list<int>|null  $companyIds
     */
    public function recalculateDateRange(
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        ?array $companyIds,
        int $recordedBy,
    ): int {
        if ($endsOn->lessThan($startsOn)) {
            return 0;
        }

        $companyIds = $companyIds === null
            ? null
            : array_values(array_unique($companyIds));

        if ($companyIds === []) {
            return 0;
        }

        $recalculatedCount = 0;

        AttendanceRecord::query()
            ->whereBetween('attendance_date', [
                $startsOn->toDateString(),
                $endsOn->toDateString(),
            ])
            ->whereNotNull('first_in')
            ->when(
                $companyIds !== null,
                fn ($query) => $query->whereHas(
                    'karyawan',
                    fn ($query) => $query->whereIn('company_id', $companyIds),
                ),
            )
            ->with([
                'karyawan.company',
                'karyawan.directorate',
                'karyawan.department',
                'karyawan.jabatan',
            ])
            ->chunkById(200, function ($records) use (&$recalculatedCount, $recordedBy): void {
                foreach ($records as $record) {
                    $this->recordEditor->recalculate($record, $recordedBy);
                    $recalculatedCount++;
                }
            });

        return $recalculatedCount;
    }
}
