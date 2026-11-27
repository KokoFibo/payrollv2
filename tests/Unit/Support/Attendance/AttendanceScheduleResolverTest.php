<?php

use App\Models\Department;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Support\Attendance\AttendanceScheduleResolver;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

it('uses the saturday security night schedule', function () {
    $schedule = (new AttendanceScheduleResolver)->resolve(
        attendanceScheduleEmployee('Security', 'Security'),
        CarbonImmutable::parse('2026-11-21'),
        CarbonImmutable::parse('2026-11-21 19:45:00'),
    );

    expect($schedule)
        ->profile->toBe('security')
        ->shift->toBe('night')
        ->expectedCheckIn->format('H:i')->toBe('17:00')
        ->expectedCheckOut->format('Y-m-d H:i')->toBe('2026-11-22 00:00')
        ->standardWorkHours->toBe(6.0)
        ->expectedPunchCount->toBe(2);
});

it('uses the saturday qc aging night schedule and flags review', function () {
    $schedule = (new AttendanceScheduleResolver)->resolve(
        attendanceScheduleEmployee('Quality', 'QC Aging'),
        CarbonImmutable::parse('2026-11-21'),
        CarbonImmutable::parse('2026-11-21 20:00:00'),
    );

    expect($schedule)
        ->profile->toBe('qc_aging')
        ->shift->toBe('night')
        ->expectedCheckIn->format('H:i')->toBe('20:00')
        ->expectedCheckOut->format('Y-m-d H:i')->toBe('2026-11-22 03:00')
        ->expectedPunchCount->toBe(4)
        ->requiresReview->toBeTrue();
});

it('uses the friday day schedule for general employees', function () {
    $schedule = (new AttendanceScheduleResolver)->resolve(
        attendanceScheduleEmployee('Production', 'General Worker'),
        CarbonImmutable::parse('2026-11-20'),
        CarbonImmutable::parse('2026-11-20 07:55:00'),
    );

    expect($schedule)
        ->profile->toBe('general')
        ->shift->toBe('day')
        ->expectedCheckIn->format('H:i')->toBe('08:00')
        ->expectedCheckOut->format('Y-m-d H:i')->toBe('2026-11-20 17:00')
        ->expectedPunchCount->toBe(4);
});

it('uses the shift whose start is closest to the first punch', function () {
    $schedule = (new AttendanceScheduleResolver)->resolve(
        attendanceScheduleEmployee('Production', 'General Worker'),
        CarbonImmutable::parse('2026-11-21'),
        CarbonImmutable::parse('2026-11-21 16:39:00'),
    );

    expect($schedule)
        ->profile->toBe('general')
        ->shift->toBe('night')
        ->expectedCheckIn->format('H:i')->toBe('17:00')
        ->expectedCheckOut->format('Y-m-d H:i')->toBe('2026-11-22 00:00');
});

it('matches the reference work schedule for every profile and shift', function (
    string $department,
    string $position,
    string $expectedProfile,
    string $date,
    string $firstPunch,
    string $expectedShift,
    string $expectedCheckIn,
    string $expectedCheckOut,
    float $expectedStandardHours,
) {
    $schedule = (new AttendanceScheduleResolver)->resolve(
        attendanceScheduleEmployee($department, $position),
        CarbonImmutable::parse($date),
        CarbonImmutable::parse($firstPunch),
    );

    expect($schedule)
        ->profile->toBe($expectedProfile)
        ->shift->toBe($expectedShift)
        ->expectedCheckIn->format('Y-m-d H:i')->toBe($expectedCheckIn)
        ->expectedCheckOut->format('Y-m-d H:i')->toBe($expectedCheckOut)
        ->standardWorkHours->toBe($expectedStandardHours);
})->with([
    'general weekday day' => ['Production', 'General Worker', 'general', '2026-11-19', '2026-11-19 08:00:00', 'day', '2026-11-19 08:00', '2026-11-19 17:00', 8.0],
    'general weekday night' => ['Production', 'General Worker', 'general', '2026-11-19', '2026-11-19 20:00:00', 'night', '2026-11-19 20:00', '2026-11-20 05:00', 8.0],
    'general friday day' => ['Production', 'General Worker', 'general', '2026-11-20', '2026-11-20 08:00:00', 'day', '2026-11-20 08:00', '2026-11-20 17:00', 7.5],
    'general friday night' => ['Production', 'General Worker', 'general', '2026-11-20', '2026-11-20 20:00:00', 'night', '2026-11-20 20:00', '2026-11-21 05:00', 8.0],
    'general saturday day' => ['Production', 'General Worker', 'general', '2026-11-21', '2026-11-21 08:00:00', 'day', '2026-11-21 08:00', '2026-11-21 15:00', 6.0],
    'general saturday night' => ['Production', 'General Worker', 'general', '2026-11-21', '2026-11-21 17:00:00', 'night', '2026-11-21 17:00', '2026-11-22 00:00', 6.0],
    'security weekday day' => ['Security', 'Security', 'security', '2026-11-19', '2026-11-19 08:00:00', 'day', '2026-11-19 08:00', '2026-11-19 20:00', 8.0],
    'security weekday night' => ['Security', 'Security', 'security', '2026-11-19', '2026-11-19 20:00:00', 'night', '2026-11-19 20:00', '2026-11-20 08:00', 8.0],
    'security friday day' => ['Security', 'Security', 'security', '2026-11-20', '2026-11-20 08:00:00', 'day', '2026-11-20 08:00', '2026-11-20 20:00', 8.0],
    'security friday night' => ['Security', 'Security', 'security', '2026-11-20', '2026-11-20 20:00:00', 'night', '2026-11-20 20:00', '2026-11-21 08:00', 8.0],
    'security saturday day' => ['Security', 'Security', 'security', '2026-11-21', '2026-11-21 08:00:00', 'day', '2026-11-21 08:00', '2026-11-21 17:00', 6.0],
    'security saturday night' => ['Security', 'Security', 'security', '2026-11-21', '2026-11-21 17:00:00', 'night', '2026-11-21 17:00', '2026-11-22 00:00', 6.0],
    'qc aging weekday day' => ['Quality', 'QC Aging', 'qc_aging', '2026-11-19', '2026-11-19 08:00:00', 'day', '2026-11-19 08:00', '2026-11-19 17:00', 8.0],
    'qc aging weekday night' => ['Quality', 'QC Aging', 'qc_aging', '2026-11-19', '2026-11-19 20:00:00', 'night', '2026-11-19 20:00', '2026-11-20 05:00', 8.0],
    'qc aging friday day' => ['Quality', 'QC Aging', 'qc_aging', '2026-11-20', '2026-11-20 08:00:00', 'day', '2026-11-20 08:00', '2026-11-20 17:00', 7.5],
    'qc aging friday night' => ['Quality', 'QC Aging', 'qc_aging', '2026-11-20', '2026-11-20 20:00:00', 'night', '2026-11-20 20:00', '2026-11-21 05:00', 8.0],
    'qc aging saturday day' => ['Quality', 'QC Aging', 'qc_aging', '2026-11-21', '2026-11-21 08:00:00', 'day', '2026-11-21 08:00', '2026-11-21 15:00', 6.0],
    'qc aging saturday night' => ['Quality', 'QC Aging', 'qc_aging', '2026-11-21', '2026-11-21 20:00:00', 'night', '2026-11-21 20:00', '2026-11-22 03:00', 6.0],
]);

it('uses the configured regular hours for security by day type', function (
    string $date,
    float $standardHours,
) {
    $schedule = (new AttendanceScheduleResolver)->resolve(
        attendanceScheduleEmployee('Security', 'Security'),
        CarbonImmutable::parse($date),
        CarbonImmutable::parse("{$date} 08:00:00"),
    );

    expect($schedule->standardWorkHours)->toBe($standardHours);
})->with([
    'thursday' => ['2026-11-19', 8.0],
    'friday' => ['2026-11-20', 8.0],
    'saturday' => ['2026-11-21', 6.0],
]);

it('marks sunday work as holiday pay using the monday schedule', function () {
    $schedule = (new AttendanceScheduleResolver)->resolve(
        attendanceScheduleEmployee('Production', 'General Worker'),
        CarbonImmutable::parse('2026-11-22'),
        CarbonImmutable::parse('2026-11-22 08:00:00'),
    );

    expect($schedule)
        ->isHolidayPay->toBeTrue()
        ->standardWorkHours->toBe(8.0)
        ->expectedCheckIn->format('H:i')->toBe('08:00')
        ->expectedCheckOut->format('H:i')->toBe('17:00');
});

function attendanceScheduleEmployee(string $departmentName, string $positionName): Karyawan
{
    $employee = new Karyawan;
    $employee->setRelation('department', new Department(['name' => $departmentName]));
    $employee->setRelation('jabatan', new Jabatan(['name' => $positionName]));

    return $employee;
}
