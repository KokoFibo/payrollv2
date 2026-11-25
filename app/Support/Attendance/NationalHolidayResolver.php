<?php

namespace App\Support\Attendance;

use App\Models\NationalHoliday;
use Carbon\CarbonImmutable;

class NationalHolidayResolver
{
    public function findFor(CarbonImmutable $attendanceDate): ?NationalHoliday
    {
        return NationalHoliday::query()
            ->whereDate('starts_on', '<=', $attendanceDate->toDateString())
            ->where(function ($query) use ($attendanceDate): void {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $attendanceDate->toDateString());
            })
            ->orderBy('starts_on')
            ->orderBy('id')
            ->first();
    }
}
