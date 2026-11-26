<?php

namespace App\Support\Attendance;

use App\Models\AdminVisibilitySetting;
use Illuminate\Support\Facades\Cache;

class AttendancePolicySettings
{
    public const CACHE_KEY = 'attendance_tolerance_minutes';

    public function toleranceMinutes(): int
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(10),
            fn (): int => (int) (
                AdminVisibilitySetting::query()->value('attendance_tolerance_minutes')
                ?? config('attendance.tolerance_minutes', 3)
            ),
        );
    }

    public function updateToleranceMinutes(int $minutes): void
    {
        AdminVisibilitySetting::query()->updateOrCreate(
            ['id' => 1],
            ['attendance_tolerance_minutes' => $minutes],
        );

        Cache::forget(self::CACHE_KEY);
    }
}
