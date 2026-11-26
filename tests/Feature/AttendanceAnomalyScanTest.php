<?php

use App\Models\AttendanceRecord;
use App\Models\CalendarOverride;
use App\Models\Company;
use App\Models\Department;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\SpecialWorkSchedule;
use App\Models\User;

test('admin scans only the selected attendance date or date range', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);

    attendanceScanRecord($employee, '2026-11-19', 8);
    attendanceScanRecord($employee, '2026-11-20', 7.5);

    $this->actingAs($admin)
        ->getJson(route('admin.attendance.anomalies', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
        ]))
        ->assertOk()
        ->assertJsonPath('period.days', 1)
        ->assertJsonPath('summary.records', 1)
        ->assertJsonCount(0, 'results');

    $this->actingAs($admin)
        ->getJson(route('admin.attendance.anomalies', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-20',
        ]))
        ->assertOk()
        ->assertJsonPath('period.days', 2)
        ->assertJsonPath('summary.records', 2);
});

test('scan detects missing regular checkout and checkout in the break window', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $missingCheckoutEmployee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    $breakCheckoutEmployee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);

    attendanceScanRecord($missingCheckoutEmployee, '2026-11-19', 8, [
        'second_out' => null,
        'overtime_in' => '17:49:00',
        'overtime_out' => '20:06:00',
        'overtime_hours' => 2,
    ]);
    attendanceScanRecord($breakCheckoutEmployee, '2026-11-19', 8, [
        'second_in' => '12:42:00',
        'second_out' => '12:12:00',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.attendance.anomalies', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
        ]))
        ->assertOk();

    $codes = collect($response->json('results'))->pluck('code');

    expect($codes)
        ->toContain('SECOND_OUT_MISSING')
        ->toContain('SECOND_OUT_IN_BREAK_WINDOW')
        ->toContain('SECOND_OUT_ORDER');
});

test('scan skips records that already require review', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    attendanceScanRecord($employee, '2026-11-19', 8, [
        'second_out' => null,
        'overtime_in' => '17:49:00',
        'overtime_out' => '20:06:00',
        'requires_review' => true,
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.attendance.anomalies', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
        ]))
        ->assertOk()
        ->assertJsonPath('summary.records', 1)
        ->assertJsonPath('summary.skipped_review', 1)
        ->assertJsonCount(0, 'results');
});

test('scan does not report duplicate punches as anomalies', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    attendanceScanRecord($employee, '2026-11-19', 8, [
        'duplicate_punch_count' => 2,
        'has_duplicate_punch' => true,
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.attendance.anomalies', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
        ]))
        ->assertOk()
        ->assertJsonCount(0, 'results');
});

test('scan includes work hour and overtime threshold anomalies', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    attendanceScanRecord($employee, '2026-11-19', 6, [
        'overtime_hours' => 5,
        'overtime_in' => '17:00:00',
        'overtime_out' => '22:00:00',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.attendance.anomalies', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
            'short_work_minutes' => 60,
            'overtime_threshold_hours' => 4,
        ]))
        ->assertOk();

    $codes = collect($response->json('results'))->pluck('code');

    expect($codes)
        ->toContain('WORK_HOURS_SHORT')
        ->toContain('OVERTIME_EXCESSIVE');
});

test('scan detects second in after the selected break return', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    attendanceScanRecord($employee, '2026-11-19', 8, [
        'second_in' => '12:34:00',
        'status' => 'late',
        'late_minutes' => 4,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.attendance.anomalies', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
        ]))
        ->assertOk();

    expect(collect($response->json('results'))->pluck('code'))
        ->toContain('SECOND_IN_LATE');
});

test('scan uses the active special work schedule', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    SpecialWorkSchedule::factory()->create([
        'name' => 'Ramadan',
        'starts_on' => '2026-11-19',
        'ends_on' => '2026-11-20',
        'schedule_profile' => 'general',
        'day_type' => 'sunday_thursday',
        'shift' => 'day',
        'check_in' => '08:00:00',
        'check_out' => '16:00:00',
        'check_out_day_offset' => 0,
        'standard_work_hours' => 8,
        'expected_punch_count' => 4,
    ]);
    attendanceScanRecord($employee, '2026-11-19', 6.5, [
        'expected_check_out' => '16:00:00',
        'second_out' => '16:00:00',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.attendance.anomalies', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
        ]))
        ->assertOk();

    expect($response->json('results.0.schedule_context'))->toBe('Jadwal khusus: Ramadan')
        ->and(collect($response->json('results'))->pluck('code'))
        ->not->toContain('SCHEDULE_CONTEXT_CHANGED');
});

test('scan uses company calendar exceptions', function () {
    $admin = User::factory()->create(['role' => 5]);
    $company = Company::factory()->create();
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'company_id' => $company->id,
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    CalendarOverride::factory()->create([
        'company_id' => $company->id,
        'directorate_id' => null,
        'department_id' => null,
        'calendar_date' => '2026-11-20',
        'name' => 'Libur Perusahaan Uji',
        'is_holiday_pay' => true,
        'schedule_day_type' => 'friday',
    ]);
    attendanceScanRecord($employee, '2026-11-20', 0, [
        'is_holiday_pay' => true,
        'holiday_worked_hours' => 10,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.attendance.anomalies', [
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-20',
        ]))
        ->assertOk();

    expect($response->json('results.0.schedule_context'))->toBe('Pengecualian kalender: Libur Perusahaan Uji');
});

test('scan respects a first out after midnight on a night shift', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    attendanceScanRecord($employee, '2026-11-20', 8, [
        'shift' => 'night',
        'expected_check_in' => '20:00:00',
        'expected_check_out' => '05:00:00',
        'first_in' => '19:46:00',
        'first_out' => '00:01:00',
        'first_out_day_offset' => 1,
        'second_in' => '00:52:00',
        'second_in_day_offset' => 1,
        'second_out' => '06:33:00',
        'second_out_day_offset' => 1,
        'overtime_hours' => 1.5,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.attendance.anomalies', [
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-20',
        ]))
        ->assertOk();

    $codes = collect($response->json('results'))->pluck('code');

    expect($response->json('results'))->toBe([])
        ->and($codes)
        ->not->toContain('FIRST_OUT_ORDER')
        ->not->toContain('SECOND_OUT_FAR_AFTER_CHECKOUT');
});

test('scan flags second out more than four hours after checkout', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    attendanceScanRecord($employee, '2026-11-19', 8, [
        'second_out' => '22:01:00',
        'overtime_hours' => 5,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.attendance.anomalies', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
            'overtime_threshold_hours' => 4,
        ]))
        ->assertOk();

    $codes = collect($response->json('results'))->pluck('code');

    expect($codes)
        ->toContain('SECOND_OUT_FAR_AFTER_CHECKOUT')
        ->toContain('OVERTIME_EXCESSIVE');
});

test('scan rejects a range longer than thirty one days', function () {
    $admin = User::factory()->create(['role' => 5]);

    $this->actingAs($admin)
        ->getJson(route('admin.attendance.anomalies', [
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-02',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('end_date');
});

test('scan forbids users without payroll access', function () {
    $user = User::factory()->create(['role' => 4]);

    $this->actingAs($user)
        ->getJson(route('admin.attendance.anomalies', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
        ]))
        ->assertForbidden();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function attendanceScanRecord(
    Karyawan $employee,
    string $attendanceDate,
    float $workedHours,
    array $overrides = [],
): AttendanceRecord {
    return AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => $attendanceDate,
        'status' => 'present',
        'shift' => 'day',
        'schedule_profile' => 'general',
        'expected_check_in' => '08:00:00',
        'expected_check_out' => '17:00:00',
        'first_in' => '08:00:00',
        'first_out' => '11:30:00',
        'second_in' => '12:30:00',
        'second_out' => '17:00:00',
        'overtime_in' => null,
        'overtime_out' => null,
        'worked_hours' => $workedHours,
        'worked_days' => 1,
        'overtime_hours' => 0,
        'raw_punch_count' => 4,
        'effective_punch_count' => 4,
        'duplicate_punch_count' => 0,
        'ignored_punch_count' => 0,
        'late_minutes' => 0,
        'early_leave_minutes' => 0,
        'has_missing_punch' => false,
        'has_duplicate_punch' => false,
        'requires_review' => false,
        'is_holiday_pay' => false,
        'holiday_worked_hours' => 0,
        'holiday_overtime_hours' => 0,
        ...$overrides,
    ]);
}
