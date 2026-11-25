<?php

namespace App\Support\Attendance;

use Carbon\CarbonImmutable;

final readonly class AttendanceSchedule
{
    /**
     * @param  array<int, string>  $reviewNotes
     */
    public function __construct(
        public string $profile,
        public string $profileLabel,
        public string $shift,
        public CarbonImmutable $expectedCheckIn,
        public CarbonImmutable $expectedCheckOut,
        public float $standardWorkHours,
        public bool $isHolidayPay,
        public ?string $holidayName,
        public int $expectedPunchCount,
        public bool $requiresReview,
        public array $reviewNotes = [],
        public ?int $calendarOverrideId = null,
        public ?string $calendarOverrideName = null,
    ) {}

    public function shiftLabel(): string
    {
        return $this->shift === 'night' ? 'Shift malam' : 'Shift pagi';
    }
}
