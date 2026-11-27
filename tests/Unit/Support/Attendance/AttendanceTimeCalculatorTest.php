<?php

use App\Support\Attendance\AttendanceSchedule;
use App\Support\Attendance\AttendanceTimeCalculator;
use Carbon\CarbonImmutable;

it('starts regular work at the scheduled time when the employee arrives early', function () {
    $result = (new AttendanceTimeCalculator)->calculate(
        attendanceTimeSchedule('2026-11-19'),
        [
            attendancePunch('2026-11-19 07:00:00'),
            attendancePunch('2026-11-19 11:30:00'),
            attendancePunch('2026-11-19 12:30:00'),
            attendancePunch('2026-11-19 17:29:00'),
        ],
    );

    expect($result)
        ->worked_hours->toBe(8.0)
        ->overtime_hours->toBe(0.0);
});

it('counts overtime from the first complete half hour after the regular shift', function (string $checkOut, float $overtime) {
    $result = (new AttendanceTimeCalculator)->calculate(
        attendanceTimeSchedule('2026-11-19'),
        [
            attendancePunch('2026-11-19 08:00:00'),
            attendancePunch('2026-11-19 11:30:00'),
            attendancePunch('2026-11-19 12:30:00'),
            attendancePunch($checkOut),
        ],
    );

    expect($result['overtime_hours'])->toBe($overtime)
        ->and($result['worked_hours'])->toBe(8.0);
})->with([
    '29 minutes past' => ['2026-11-19 17:29:00', 0.0],
    '30 minutes past' => ['2026-11-19 17:30:00', 0.5],
    '1 hour 29 minutes past' => ['2026-11-19 18:29:00', 1.0],
    '1 hour 30 minutes past' => ['2026-11-19 18:30:00', 1.5],
]);

it('uses overtime slots when the second checkout is followed by a break and overtime', function () {
    $result = (new AttendanceTimeCalculator)->calculate(
        attendanceTimeSchedule('2026-11-19'),
        [
            attendancePunch('2026-11-19 08:00:00'),
            attendancePunch('2026-11-19 11:30:00'),
            attendancePunch('2026-11-19 12:30:00'),
            attendancePunch('2026-11-19 17:00:00'),
            attendancePunch('2026-11-19 17:30:00'),
            attendancePunch('2026-11-19 18:30:00'),
        ],
    );

    expect($result)
        ->worked_hours->toBe(8.0)
        ->overtime_hours->toBe(1.0);
});

it('rounds overtime start with grace and overtime end down to half hours', function (
    string $overtimeIn,
    string $overtimeOut,
    float $expectedOvertimeHours,
) {
    $result = (new AttendanceTimeCalculator)->calculate(
        attendanceTimeSchedule('2026-11-19'),
        [
            attendancePunch('2026-11-19 08:00:00'),
            attendancePunch('2026-11-19 11:30:00'),
            attendancePunch('2026-11-19 12:30:00'),
            attendancePunch('2026-11-19 17:00:00'),
            attendancePunch("2026-11-19 {$overtimeIn}"),
            attendancePunch("2026-11-19 {$overtimeOut}"),
        ],
    );

    expect($result['overtime_hours'])->toBe($expectedOvertimeHours);
})->with([
    'three minutes after the slot uses the slot start' => ['18:03:00', '19:59:00', 1.5],
    'four minutes after the slot rounds up' => ['18:04:00', '19:59:00', 1.0],
    'ending before the next half hour rounds down to one hour' => ['18:00:00', '19:29:00', 1.0],
    'ending three minutes after the half hour rounds down' => ['18:00:00', '19:03:00', 1.0],
]);

it('doubles regular and overtime hours on holiday pay dates', function () {
    $result = (new AttendanceTimeCalculator)->calculate(
        attendanceTimeSchedule('2026-11-19', holidayPay: true),
        [
            attendancePunch('2026-11-19 08:00:00'),
            attendancePunch('2026-11-19 11:30:00'),
            attendancePunch('2026-11-19 12:30:00'),
            attendancePunch('2026-11-19 18:30:00'),
        ],
    );

    expect($result)
        ->worked_hours->toBe(0.0)
        ->overtime_hours->toBe(0.0)
        ->holiday_worked_hours->toBe(16.0)
        ->holiday_overtime_hours->toBe(3.0);
});

it('counts security regular hours by day type and overtime after scheduled checkout', function (
    string $checkIn,
    string $expectedCheckOut,
    string $checkOut,
    float $expectedWorkedHours,
    float $expectedOvertimeHours,
) {
    $attendanceDate = CarbonImmutable::parse($checkIn)->startOfDay();
    $isSaturday = $attendanceDate->isSaturday();
    $schedule = new AttendanceSchedule(
        profile: 'security',
        profileLabel: 'Security',
        shift: CarbonImmutable::parse($checkIn)->hour >= 12 ? 'night' : 'day',
        expectedCheckIn: CarbonImmutable::parse($checkIn),
        expectedCheckOut: CarbonImmutable::parse($expectedCheckOut),
        standardWorkHours: $isSaturday ? 6 : 8,
        isHolidayPay: false,
        holidayName: null,
        expectedPunchCount: 2,
        requiresReview: false,
        breakHours: $isSaturday ? 1 : 0,
    );
    $result = (new AttendanceTimeCalculator)->calculate(
        $schedule,
        [
            attendancePunch($checkIn),
            null,
            null,
            attendancePunch($checkOut),
        ],
    );

    expect($result)
        ->worked_hours->toBe($expectedWorkedHours)
        ->overtime_hours->toBe($expectedOvertimeHours);
})->with([
    'twelve hour shift' => ['2026-11-19 08:00:00', '2026-11-19 20:00:00', '2026-11-19 20:00:00', 8.0, 0.0],
    'saturday day with one hour overtime' => ['2026-11-21 08:00:00', '2026-11-21 17:00:00', '2026-11-21 18:00:00', 6.0, 1.0],
    'saturday night without overtime' => ['2026-11-21 17:00:00', '2026-11-22 00:00:00', '2026-11-22 00:00:00', 6.0, 0.0],
    'saturday night with one hour overtime' => ['2026-11-21 17:00:00', '2026-11-22 00:00:00', '2026-11-22 01:00:00', 6.0, 2.0],
]);

it('weights saturday security night overtime before and after five', function (
    string $checkOut,
    float $expectedOvertimeHours,
) {
    $schedule = new AttendanceSchedule(
        profile: 'security',
        profileLabel: 'Security',
        shift: 'night',
        expectedCheckIn: CarbonImmutable::parse('2026-11-21 17:00:00'),
        expectedCheckOut: CarbonImmutable::parse('2026-11-22 00:00:00'),
        standardWorkHours: 6,
        isHolidayPay: false,
        holidayName: null,
        expectedPunchCount: 2,
        requiresReview: false,
        breakHours: 1,
    );
    $result = (new AttendanceTimeCalculator)->calculate(
        $schedule,
        [
            attendancePunch('2026-11-21 17:00:00'),
            null,
            null,
            attendancePunch($checkOut),
        ],
    );

    expect($result)
        ->worked_hours->toBe(6.0)
        ->overtime_hours->toBe($expectedOvertimeHours);
})->with([
    'at five with ten weighted hours' => ['2026-11-22 05:00:00', 10.0],
    'at six twenty nine with eleven weighted hours' => ['2026-11-22 06:29:00', 11.0],
    'at eight twenty nine with thirteen weighted hours' => ['2026-11-22 08:29:00', 13.0],
]);

it('counts saturday overtime only in complete half hours after checkout', function (
    string $checkOut,
    float $expectedOvertimeHours,
) {
    $schedule = new AttendanceSchedule(
        profile: 'general',
        profileLabel: 'Produksi / Umum',
        shift: 'night',
        expectedCheckIn: CarbonImmutable::parse('2026-11-21 17:00:00'),
        expectedCheckOut: CarbonImmutable::parse('2026-11-22 00:00:00'),
        standardWorkHours: 6,
        isHolidayPay: false,
        holidayName: null,
        expectedPunchCount: 4,
        requiresReview: false,
        breakHours: 1,
    );
    $result = (new AttendanceTimeCalculator)->calculate(
        $schedule,
        [
            attendancePunch('2026-11-21 16:33:00'),
            attendancePunch('2026-11-21 20:36:00'),
            attendancePunch('2026-11-21 21:10:00'),
            attendancePunch($checkOut),
        ],
    );

    expect($result)
        ->worked_hours->toBe(6.0)
        ->overtime_hours->toBe($expectedOvertimeHours);
})->with([
    'two minutes after midnight' => ['2026-11-22 00:02:00', 0.0],
    'twelve minutes after midnight' => ['2026-11-22 00:12:00', 0.0],
    'twenty nine minutes after midnight' => ['2026-11-22 00:29:00', 0.0],
    'thirty minutes after midnight' => ['2026-11-22 00:30:00', 0.5],
    'fifty nine minutes after midnight' => ['2026-11-22 00:59:00', 0.5],
    'one hour after midnight' => ['2026-11-22 01:00:00', 1.0],
    'six hours twenty nine minutes after midnight' => ['2026-11-22 06:29:00', 6.0],
]);

it('uses configured friday night hours before counting overtime', function (
    string $checkOut,
    float $expectedOvertimeHours,
) {
    $schedule = new AttendanceSchedule(
        profile: 'general',
        profileLabel: 'Produksi / Umum',
        shift: 'night',
        expectedCheckIn: CarbonImmutable::parse('2026-11-20 20:00:00'),
        expectedCheckOut: CarbonImmutable::parse('2026-11-21 05:00:00'),
        standardWorkHours: 8,
        isHolidayPay: false,
        holidayName: null,
        expectedPunchCount: 4,
        requiresReview: false,
        breakHours: 1,
    );
    $result = (new AttendanceTimeCalculator)->calculate(
        $schedule,
        [
            attendancePunch('2026-11-20 20:00:00'),
            null,
            null,
            attendancePunch($checkOut),
        ],
    );

    expect($result)
        ->worked_hours->toBe(8.0)
        ->overtime_hours->toBe($expectedOvertimeHours);
})->with([
    'at scheduled checkout' => ['2026-11-21 05:00:00', 0.0],
    'twenty nine minutes after checkout' => ['2026-11-21 05:29:00', 0.0],
    'thirty minutes after checkout' => ['2026-11-21 05:30:00', 0.5],
]);

it('counts a full security shift when checkout is before the scheduled end', function (
    string $shift,
    string $checkIn,
    string $expectedCheckOut,
    string $checkOut,
) {
    $schedule = new AttendanceSchedule(
        profile: 'security',
        profileLabel: 'Security',
        shift: $shift,
        expectedCheckIn: CarbonImmutable::parse($checkIn),
        expectedCheckOut: CarbonImmutable::parse($expectedCheckOut),
        standardWorkHours: 8,
        isHolidayPay: false,
        holidayName: null,
        expectedPunchCount: 2,
        requiresReview: false,
    );
    $result = (new AttendanceTimeCalculator)->calculate(
        $schedule,
        [
            attendancePunch($checkIn),
            null,
            null,
            attendancePunch($checkOut),
        ],
    );

    expect($result)
        ->worked_hours->toBe(8.0)
        ->overtime_hours->toBe(0.0);
})->with([
    'day checkout before 20:00' => [
        'day',
        '2026-11-20 08:00:00',
        '2026-11-20 20:00:00',
        '2026-11-20 15:00:00',
    ],
    'night checkout before 08:00' => [
        'night',
        '2026-11-20 20:00:00',
        '2026-11-21 08:00:00',
        '2026-11-21 04:00:00',
    ],
]);

it('uses the maximum regular hours when punches cover only part of the shift', function () {
    $result = (new AttendanceTimeCalculator)->calculate(
        attendanceTimeSchedule('2026-11-19'),
        [
            attendancePunch('2026-11-19 08:00:00'),
            attendancePunch('2026-11-19 09:36:00'),
            attendancePunch('2026-11-19 09:36:00'),
            attendancePunch('2026-11-19 09:36:00'),
        ],
    );

    expect($result['worked_hours'])->toBe(8.0);
});

it('uses the maximum regular hours before early checkout deductions', function () {
    $result = (new AttendanceTimeCalculator)->calculate(
        attendanceTimeSchedule('2026-11-19'),
        [
            attendancePunch('2026-11-19 08:00:00'),
            attendancePunch('2026-11-19 08:50:00'),
            attendancePunch('2026-11-19 09:10:00'),
            attendancePunch('2026-11-19 09:58:00'),
        ],
    );

    expect($result['worked_hours'])->toBe(8.0);
});

it('uses eight maximum regular hours for friday night shift', function () {
    $schedule = new AttendanceSchedule(
        profile: 'general',
        profileLabel: 'Produksi / Umum',
        shift: 'night',
        expectedCheckIn: CarbonImmutable::parse('2026-11-20 20:00:00'),
        expectedCheckOut: CarbonImmutable::parse('2026-11-21 05:00:00'),
        standardWorkHours: 8,
        isHolidayPay: false,
        holidayName: null,
        expectedPunchCount: 4,
        requiresReview: false,
        breakHours: 1,
    );
    $result = (new AttendanceTimeCalculator)->calculate(
        $schedule,
        [
            attendancePunch('2026-11-20 20:00:00'),
            attendancePunch('2026-11-20 23:30:00'),
            attendancePunch('2026-11-21 00:30:00'),
            attendancePunch('2026-11-21 05:00:00'),
        ],
    );

    expect($result)
        ->worked_hours->toBe(8.0)
        ->overtime_hours->toBe(0.0);
});

it('caps friday and saturday regular work hours', function (string $date, float $standardHours) {
    $result = (new AttendanceTimeCalculator)->calculate(
        attendanceTimeSchedule($date, standardHours: $standardHours),
        [
            attendancePunch("{$date} 08:00:00"),
            attendancePunch("{$date} 11:30:00"),
            attendancePunch("{$date} 12:30:00"),
            attendancePunch("{$date} 17:00:00"),
        ],
    );

    expect($result['worked_hours'])->toBe($standardHours);
})->with([
    'friday' => ['2026-11-20', 7.5],
    'saturday' => ['2026-11-21', 6.0],
]);

it('uses the full saturday regular quota when checkout reaches the scheduled end', function () {
    $schedule = new AttendanceSchedule(
        profile: 'general',
        profileLabel: 'Produksi / Umum',
        shift: 'day',
        expectedCheckIn: CarbonImmutable::parse('2026-11-21 08:00:00'),
        expectedCheckOut: CarbonImmutable::parse('2026-11-21 15:00:00'),
        standardWorkHours: 6,
        isHolidayPay: false,
        holidayName: null,
        expectedPunchCount: 4,
        requiresReview: false,
        breakHours: 1,
    );
    $result = (new AttendanceTimeCalculator)->calculate(
        $schedule,
        [
            attendancePunch('2026-11-21 07:43:00'),
            attendancePunch('2026-11-21 12:00:00'),
            attendancePunch('2026-11-21 13:04:00'),
            attendancePunch('2026-11-21 16:06:00'),
        ],
    );

    expect($result)
        ->worked_hours->toBe(6.0)
        ->overtime_hours->toBe(1.0);
});

function attendanceTimeSchedule(
    string $date,
    float $standardHours = 8,
    bool $holidayPay = false,
): AttendanceSchedule {
    $attendanceDate = CarbonImmutable::parse($date);

    return new AttendanceSchedule(
        profile: 'general',
        profileLabel: 'Produksi / Umum',
        shift: 'day',
        expectedCheckIn: $attendanceDate->setTime(8, 0),
        expectedCheckOut: $attendanceDate->setTime(17, 0),
        standardWorkHours: $standardHours,
        isHolidayPay: $holidayPay,
        holidayName: $holidayPay ? 'Hari Libur' : null,
        expectedPunchCount: 4,
        requiresReview: false,
    );
}

/**
 * @return array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}
 */
function attendancePunch(string $dateTime): array
{
    $punchedAt = CarbonImmutable::parse($dateTime);

    return [
        'raw_value' => $punchedAt->format('g:i A'),
        'punched_at' => $punchedAt,
        'is_next_day' => false,
        'sequence' => 1,
    ];
}
