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
        ->expectedCheckOut->format('Y-m-d H:i')->toBe('2026-11-22 08:00')
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

it('limits security regular hours by day type', function (string $date, float $standardHours) {
    $schedule = (new AttendanceScheduleResolver)->resolve(
        attendanceScheduleEmployee('Security', 'Security'),
        CarbonImmutable::parse($date),
        CarbonImmutable::parse("{$date} 08:00:00"),
    );

    expect($schedule->standardWorkHours)->toBe($standardHours);
})->with([
    'thursday' => ['2026-11-19', 8.0],
    'friday' => ['2026-11-20', 7.5],
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
