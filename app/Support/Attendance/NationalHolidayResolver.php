<?php

namespace App\Support\Attendance;

use App\Models\NationalHoliday;
use Carbon\CarbonImmutable;

class NationalHolidayResolver
{
    /**
     * @var array<string, NationalHoliday|null>
     */
    private array $cache = [];

    public function findFor(CarbonImmutable $attendanceDate): ?NationalHoliday
    {
        $cacheKey = $attendanceDate->toDateString();

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        return $this->cache[$cacheKey] = NationalHoliday::query()
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
