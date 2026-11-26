<?php

namespace App\Support\Attendance;

use App\Models\SpecialWorkSchedule;
use Carbon\CarbonImmutable;

class SpecialWorkScheduleResolver
{
    /**
     * @var array<string, SpecialWorkSchedule|null>
     */
    private array $cache = [];

    public function findFor(
        string $profile,
        string $dayType,
        string $shift,
        CarbonImmutable $attendanceDate,
        ?int $companyId = null,
        ?int $directorateId = null,
        ?int $departmentId = null,
    ): ?SpecialWorkSchedule {
        $cacheKey = implode(':', [
            $attendanceDate->toDateString(),
            $profile,
            $dayType,
            $shift,
            $companyId ?? '-',
            $directorateId ?? '-',
            $departmentId ?? '-',
        ]);

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        return $this->cache[$cacheKey] = SpecialWorkSchedule::query()
            ->whereDate('starts_on', '<=', $attendanceDate->toDateString())
            ->whereDate('ends_on', '>=', $attendanceDate->toDateString())
            ->where(function ($query) use ($companyId): void {
                $query->whereNull('company_id');

                if ($companyId !== null) {
                    $query->orWhere('company_id', $companyId);
                }
            })
            ->where(function ($query) use ($directorateId): void {
                $query->whereNull('directorate_id');

                if ($directorateId !== null) {
                    $query->orWhere('directorate_id', $directorateId);
                }
            })
            ->where(function ($query) use ($departmentId): void {
                $query->whereNull('department_id');

                if ($departmentId !== null) {
                    $query->orWhere('department_id', $departmentId);
                }
            })
            ->where(function ($query) use ($profile): void {
                $query->whereNull('schedule_profile')
                    ->orWhere('schedule_profile', $profile);
            })
            ->where(function ($query) use ($dayType): void {
                $query->whereNull('day_type')
                    ->orWhere('day_type', $dayType);
            })
            ->where(function ($query) use ($shift): void {
                $query->whereNull('shift')
                    ->orWhere('shift', $shift);
            })
            ->orderByRaw(
                '(
                    CASE WHEN company_id IS NOT NULL THEN 1 ELSE 0 END
                    + CASE WHEN directorate_id IS NOT NULL THEN 1 ELSE 0 END
                    + CASE WHEN department_id IS NOT NULL THEN 1 ELSE 0 END
                ) DESC',
            )
            ->orderByRaw(
                'CASE WHEN schedule_profile = ? THEN 1 ELSE 0 END DESC',
                [$profile],
            )
            ->orderByRaw(
                'CASE WHEN day_type = ? THEN 1 ELSE 0 END DESC',
                [$dayType],
            )
            ->orderByRaw(
                'CASE WHEN shift = ? THEN 1 ELSE 0 END DESC',
                [$shift],
            )
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->first();
    }
}
