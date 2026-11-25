<?php

namespace App\Support\Attendance;

use App\Models\CalendarOverride;
use App\Models\Karyawan;
use Carbon\CarbonImmutable;

class CalendarOverrideResolver
{
    public function findFor(
        Karyawan $karyawan,
        CarbonImmutable $attendanceDate,
    ): ?CalendarOverride {
        if ($karyawan->company_id === null) {
            return null;
        }

        return CalendarOverride::query()
            ->whereDate('calendar_date', $attendanceDate->toDateString())
            ->where('company_id', $karyawan->company_id)
            ->where(function ($query) use ($karyawan): void {
                $query->whereNull('directorate_id');

                if ($karyawan->directorate_id !== null) {
                    $query->orWhere('directorate_id', $karyawan->directorate_id);
                }
            })
            ->where(function ($query) use ($karyawan): void {
                $query->whereNull('department_id');

                if ($karyawan->department_id !== null) {
                    $query->orWhere('department_id', $karyawan->department_id);
                }
            })
            ->orderByRaw(
                '(
                    CASE WHEN directorate_id IS NOT NULL THEN 1 ELSE 0 END
                    + CASE WHEN department_id IS NOT NULL THEN 2 ELSE 0 END
                ) DESC',
            )
            ->orderByDesc('id')
            ->first();
    }
}
