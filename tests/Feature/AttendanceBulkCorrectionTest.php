<?php

use App\Enums\KaryawanStatus;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Department;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\User;

test('bulk preview returns only attendance records inside employee filters', function () {
    $developer = User::factory()->create(['role' => 8]);
    $company = Company::factory()->create(['name' => 'Bulk Company']);
    $department = Department::factory()->create(['name' => 'Production']);
    $otherDepartment = Department::factory()->create(['name' => 'Finance']);
    $position = Jabatan::factory()->create(['name' => 'General Worker']);
    $existingEmployee = Karyawan::factory()->create([
        'nip' => 1001,
        'nama' => 'Existing Attendance',
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'company_id' => $company->id,
        'department_id' => $department->id,
        'jabatan_id' => $position->id,
    ]);
    $secondEmployee = Karyawan::factory()->create([
        'nip' => 1002,
        'nama' => 'Second Attendance',
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'company_id' => $company->id,
        'department_id' => $department->id,
        'jabatan_id' => $position->id,
    ]);
    $missingEmployee = Karyawan::factory()->create([
        'nama' => 'No Attendance',
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'company_id' => $company->id,
        'department_id' => $department->id,
    ]);
    $otherDepartmentEmployee = Karyawan::factory()->create([
        'nama' => 'Other Department Employee',
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'company_id' => $company->id,
        'department_id' => $otherDepartment->id,
    ]);
    AttendanceRecord::factory()->for($existingEmployee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'present',
        'shift' => 'day',
        'has_missing_punch' => true,
    ]);
    AttendanceRecord::factory()->for($secondEmployee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'present',
        'shift' => 'night',
    ]);
    AttendanceRecord::factory()->for($otherDepartmentEmployee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'present',
    ]);

    $this->actingAs($developer)
        ->getJson(route('admin.developer-settings.attendance-bulk.preview', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
            'company_id' => $company->id,
            'department_id' => $department->id,
            'condition' => 'all',
            'page' => 1,
            'per_page' => 15,
        ]))
        ->assertOk()
        ->assertJsonPath('summary.total', 2)
        ->assertJsonPath('summary.incomplete', 1)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('pagination.total', 2)
        ->assertJsonPath('pagination.current_page', 1)
        ->assertJsonPath('data.0.employee.name', 'Existing Attendance')
        ->assertJsonPath('data.1.employee.name', 'Second Attendance')
        ->assertJsonPath('data.0.has_attendance', true)
        ->assertJsonPath('data.1.has_attendance', true);

    expect($missingEmployee->attendanceRecords()->count())->toBe(0);
});

test('bulk preview paginates uploaded attendance on the server', function () {
    $developer = User::factory()->create(['role' => 8]);
    $employee = Karyawan::factory()->create([
        'nip' => 3001,
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);

    foreach (range(1, 20) as $day) {
        AttendanceRecord::factory()->for($employee)->create([
            'attendance_date' => sprintf('2026-11-%02d', $day),
            'status' => 'present',
            'shift' => 'day',
        ]);
    }

    $this->actingAs($developer)
        ->getJson(route('admin.developer-settings.attendance-bulk.preview', [
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-20',
            'condition' => 'all',
            'page' => 1,
            'per_page' => 15,
        ]))
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('pagination.total', 20)
        ->assertJsonPath('pagination.current_page', 1)
        ->assertJsonPath('pagination.last_page', 2);

    $this->actingAs($developer)
        ->getJson(route('admin.developer-settings.attendance-bulk.preview', [
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-20',
            'condition' => 'all',
            'page' => 2,
            'per_page' => 15,
        ]))
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('pagination.total', 20)
        ->assertJsonPath('pagination.current_page', 2)
        ->assertJsonPath('pagination.last_page', 2);
});

test('bulk preview paginates daily attendance larger than 2000 records', function () {
    $developer = User::factory()->create(['role' => 8]);
    $employeeCount = 2005;
    $employees = Karyawan::factory()->count($employeeCount)->create([
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'etnis_id' => null,
        'company_id' => null,
        'directorate_id' => null,
        'department_id' => null,
        'jabatan_id' => null,
        'job_grade_id' => null,
    ]);
    $now = now();
    $attendanceRows = $employees->map(function (Karyawan $employee) use ($now): array {
        return [
            ...AttendanceRecord::factory()->make([
                'karyawan_id' => $employee->id,
                'attendance_date' => '2026-11-19',
                'status' => 'present',
                'shift' => 'day',
            ])->getAttributes(),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    })->all();
    AttendanceRecord::query()->insert($attendanceRows);

    $this->actingAs($developer)
        ->getJson(route('admin.developer-settings.attendance-bulk.preview', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
            'page' => 1,
            'per_page' => 100,
        ]))
        ->assertOk()
        ->assertJsonCount(100, 'data')
        ->assertJsonPath('pagination.total', $employeeCount)
        ->assertJsonPath('pagination.current_page', 1)
        ->assertJsonPath('pagination.last_page', 21);

    $this->actingAs($developer)
        ->getJson(route('admin.developer-settings.attendance-bulk.preview', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
            'page' => 21,
            'per_page' => 100,
        ]))
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('pagination.total', $employeeCount)
        ->assertJsonPath('pagination.current_page', 21)
        ->assertJsonPath('pagination.last_page', 21);
});

test('bulk update applies all attendance matching the filters across pages', function () {
    $developer = User::factory()->create(['role' => 8]);
    $employee = Karyawan::factory()->create([
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);

    foreach (range(1, 20) as $day) {
        AttendanceRecord::factory()->for($employee)->create([
            'attendance_date' => sprintf('2026-11-%02d', $day),
            'status' => 'present',
            'shift' => 'day',
        ]);
    }

    $this->actingAs($developer)
        ->put(route('admin.developer-settings.attendance-bulk.update'), [
            'select_all' => 1,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-20',
            'condition' => 'all',
            'times' => [
                'first_in' => '08:00',
                'first_out' => '11:30',
                'second_in' => '12:30',
                'second_out' => '17:00',
                'overtime_in' => null,
                'overtime_out' => null,
            ],
            'fill_empty_only' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'success'
            && str_contains($toast['message'], '20 attendance diperbarui'));

    expect(AttendanceRecord::query()
        ->whereBetween('attendance_date', ['2026-11-01', '2026-11-20'])
        ->where('second_in', '12:30:00')
        ->where('second_out', '17:00:00')
        ->count())->toBe(20);
});

test('developer bulk update creates missing attendance and recalculates selected records', function () {
    $developer = User::factory()->create(['role' => 8]);
    $department = Department::factory()->create(['name' => 'Production']);
    $position = Jabatan::factory()->create(['name' => 'General Worker']);
    $existingEmployee = Karyawan::factory()->create([
        'nip' => 2001,
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'department_id' => $department->id,
        'jabatan_id' => $position->id,
    ]);
    $missingEmployee = Karyawan::factory()->create([
        'nip' => 2002,
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'department_id' => $department->id,
        'jabatan_id' => $position->id,
    ]);
    $unselectedEmployee = Karyawan::factory()->create([
        'nip' => 2003,
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'department_id' => $department->id,
        'jabatan_id' => $position->id,
    ]);
    $existingRecord = AttendanceRecord::factory()->for($existingEmployee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'present',
        'first_in' => '07:45:00',
        'first_out' => null,
        'second_in' => null,
        'second_out' => null,
        'overtime_in' => null,
        'overtime_out' => null,
        'worked_hours' => 0,
        'has_missing_punch' => true,
    ]);
    $unselectedRecord = AttendanceRecord::factory()->for($unselectedEmployee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'present',
        'first_in' => '08:00:00',
        'first_out' => '11:30:00',
        'second_in' => '12:30:00',
        'second_out' => '17:00:00',
        'shift' => 'day',
        'worked_hours' => 8,
    ]);

    $this->actingAs($developer)
        ->put(route('admin.developer-settings.attendance-bulk.update'), [
            'targets' => [
                [
                    'karyawan_id' => $existingEmployee->id,
                    'attendance_date' => '2026-11-19',
                ],
                [
                    'karyawan_id' => $missingEmployee->id,
                    'attendance_date' => '2026-11-19',
                ],
            ],
            'times' => [
                'first_in' => '20:00',
                'first_out' => '23:30',
                'second_in' => '00:30',
                'second_out' => '05:00',
                'overtime_in' => null,
                'overtime_out' => null,
            ],
            'fill_empty_only' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'success'
            && str_contains($toast['message'], '1 attendance diperbarui')
            && str_contains($toast['message'], '1 attendance baru dibuat'));

    expect($existingRecord->refresh())
        ->shift->toBe('night')
        ->first_in->toBe('20:00:00')
        ->first_out->toBe('23:30:00')
        ->second_in->toBe('00:30:00')
        ->second_out->toBe('05:00:00')
        ->worked_hours->toBe('8.0')
        ->night_shift_allowance->toBe('1.0')
        ->has_missing_punch->toBeFalse();

    $createdRecord = AttendanceRecord::query()
        ->where('karyawan_id', $missingEmployee->id)
        ->whereDate('attendance_date', '2026-11-19')
        ->firstOrFail();

    expect($createdRecord)
        ->shift->toBe('night')
        ->worked_hours->toBe('8.0')
        ->night_shift_allowance->toBe('1.0')
        ->requires_review->toBeFalse();

    expect($unselectedRecord->refresh())
        ->first_in->toBe('08:00:00')
        ->worked_hours->toBe('8.0');
});

test('bulk update can fill only empty punch slots without replacing existing times', function () {
    $developer = User::factory()->create(['role' => 8]);
    $employee = Karyawan::factory()->create([
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);
    $record = AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'present',
        'first_in' => '08:00:00',
        'first_out' => '11:30:00',
        'second_in' => null,
        'second_out' => null,
        'overtime_in' => null,
        'overtime_out' => null,
        'has_missing_punch' => true,
    ]);

    $this->actingAs($developer)
        ->put(route('admin.developer-settings.attendance-bulk.update'), [
            'targets' => [[
                'karyawan_id' => $employee->id,
                'attendance_date' => '2026-11-19',
            ]],
            'times' => [
                'first_in' => '09:00',
                'first_out' => '12:00',
                'second_in' => '12:30',
                'second_out' => '17:00',
                'overtime_in' => null,
                'overtime_out' => null,
            ],
            'fill_empty_only' => true,
        ])
        ->assertRedirect();

    expect($record->refresh())
        ->first_in->toBe('08:00:00')
        ->first_out->toBe('11:30:00')
        ->second_in->toBe('12:30:00')
        ->second_out->toBe('17:00:00')
        ->worked_hours->toBe('8.0')
        ->has_missing_punch->toBeFalse();
});

test('bulk update rejects a new record without a first in time', function () {
    $developer = User::factory()->create(['role' => 8]);
    $employee = Karyawan::factory()->create([
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);

    $this->actingAs($developer)
        ->from(route('admin.developer-settings.index'))
        ->put(route('admin.developer-settings.attendance-bulk.update'), [
            'targets' => [[
                'karyawan_id' => $employee->id,
                'attendance_date' => '2026-11-19',
            ]],
            'times' => [
                'first_in' => null,
                'first_out' => '17:00',
            ],
            'fill_empty_only' => false,
        ])
        ->assertRedirect(route('admin.developer-settings.index'))
        ->assertSessionHasErrors('times.first_in');

    expect(AttendanceRecord::query()->count())->toBe(0);
});

test('bulk attendance correction is forbidden outside the developer role', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create();

    $this->actingAs($admin)
        ->getJson(route('admin.developer-settings.attendance-bulk.preview', [
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
        ]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('admin.developer-settings.attendance-bulk.update'), [
            'targets' => [[
                'karyawan_id' => $employee->id,
                'attendance_date' => '2026-11-19',
            ]],
            'times' => ['first_in' => '08:00'],
            'fill_empty_only' => false,
        ])
        ->assertForbidden();
});
