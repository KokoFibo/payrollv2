<?php

use App\Models\AttendanceRecord;
use App\Models\CalendarOverride;
use App\Models\Company;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\NationalHoliday;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can view attendance records and statistics', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create([
        'nama' => 'Attendance Employee',
        'gaji_pokok' => 4_500_000,
    ]);
    AttendanceRecord::factory()->create([
        'karyawan_id' => $employee->id,
        'attendance_date' => today(),
        'status' => 'present',
        'overtime_hours' => 1,
    ]);

    $this->actingAs($admin)
        ->get('/admin/attendance?date='.today()->toDateString().'&perPage=5')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Index')
            ->has('records.data', 1)
            ->where('records.per_page', 5)
            ->where('records.data.0.employee.name', 'Attendance Employee')
            ->where('filters.perPage', 5)
            ->where('filters.date', today()->toDateString())
            ->where('stats.present', 1)
            ->where('stats.overtimeHours', 1));
});

test('attendance opens on the latest available attendance date by default', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create();
    AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-18',
    ]);
    AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-21',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.date', '2026-11-21')
            ->has('records.data', 1));
});

test('admin can create update and delete attendance', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create(['gaji_pokok' => 4_500_000]);

    $this->actingAs($admin)
        ->post('/admin/attendance', [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-20',
            'status' => 'late',
            'first_in' => '08:30',
            'first_out' => '17:00',
            'overtime_hours' => 0.75,
            'notes' => 'Traffic delay',
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->firstOrFail();

    expect($record->karyawan_id)->toBe($employee->id)
        ->and($record->recorded_by)->toBe($admin->id)
        ->and($record->status)->toBe('late')
        ->and($record->first_in)->toBe('08:30:00');

    $this->actingAs($admin)
        ->put(route('admin.attendance.update', $record), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-20',
            'status' => 'present',
            'first_in' => '08:00',
            'first_out' => '17:00',
            'overtime_hours' => 0,
            'notes' => null,
        ])
        ->assertRedirect();

    expect($record->refresh()->status)->toBe('present');

    $this->actingAs($admin)
        ->delete(route('admin.attendance.destroy', $record))
        ->assertRedirect();

    $this->assertModelMissing($record);
});

test('attendance rejects duplicate employee dates', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create(['gaji_pokok' => 4_500_000]);
    AttendanceRecord::factory()->create([
        'karyawan_id' => $employee->id,
        'attendance_date' => '2026-11-20',
    ]);

    $this->actingAs($admin)
        ->from('/admin/attendance')
        ->post('/admin/attendance', [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-20',
            'status' => 'present',
            'overtime_hours' => 0,
        ])
        ->assertSessionHasErrors('karyawan_id');
});

test('attendance forbids non admin roles', function () {
    $user = User::factory()->create(['role' => 4]);

    $this->actingAs($user)->get('/admin/attendance')->assertForbidden();
});

test('attendance filters employees by organization and position', function () {
    $admin = User::factory()->create(['role' => 5]);
    $company = Company::factory()->create(['name' => 'Company Match']);
    $directorate = Directorate::factory()->create(['name' => 'Directorate Match']);
    $department = Department::factory()->create(['name' => 'Department Match']);
    $jabatan = Jabatan::factory()->create(['name' => 'Jabatan Match']);
    $matchingEmployee = Karyawan::factory()->create([
        'nama' => 'Matching Employee',
        'company_id' => $company->id,
        'directorate_id' => $directorate->id,
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    $otherEmployee = Karyawan::factory()->create(['nama' => 'Other Employee']);
    AttendanceRecord::factory()->for($matchingEmployee)->create([
        'attendance_date' => '2026-11-20',
    ]);
    AttendanceRecord::factory()->for($otherEmployee)->create([
        'attendance_date' => '2026-11-20',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', [
            'date' => '2026-11-20',
            'company_id' => $company->id,
            'directorate_id' => $directorate->id,
            'department_id' => $department->id,
            'jabatan_id' => $jabatan->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)
            ->where('records.data.0.employee.name', 'Matching Employee')
            ->where('records.data.0.employee.company', 'Company Match')
            ->where('records.data.0.employee.directorate', 'Directorate Match')
            ->has('options.directorates', 1)
            ->has('options.departments', 1)
            ->has('options.jabatans', 1));
});

test('attendance defaults to five rows with missing punch first and supports late filter', function () {
    $admin = User::factory()->create(['role' => 5]);
    $missingEmployee = Karyawan::factory()->create(['nama' => 'Alpha Missing']);
    $completeEmployee = Karyawan::factory()->create(['nama' => 'Beta Complete']);
    AttendanceRecord::factory()->for($missingEmployee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'late',
        'late_minutes' => 30,
        'has_missing_punch' => true,
    ]);
    AttendanceRecord::factory()->for($completeEmployee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'present',
        'late_minutes' => 0,
        'has_missing_punch' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', ['date' => '2026-11-19']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.per_page', 5)
            ->where('records.data.0.employee.name', 'Alpha Missing'));

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', [
            'date' => '2026-11-19',
            'late_only' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)
            ->where('records.data.0.employee.name', 'Alpha Missing')
            ->where('filters.lateOnly', true));
});

test('admin can delete all attendance records for one date', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create();
    $selectedRecord = AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-20',
    ]);
    $otherRecord = AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-21',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.attendance.destroy-day'), [
            'attendance_date' => '2026-11-20',
        ])
        ->assertRedirect();

    $this->assertModelMissing($selectedRecord);
    $this->assertModelExists($otherRecord);
});

test('attendance date metadata marks sunday and national holidays', function () {
    $admin = User::factory()->create(['role' => 5]);
    NationalHoliday::factory()->create([
        'name' => 'Hari Libur Uji',
        'starts_on' => '2026-11-22',
        'ends_on' => '2026-11-22',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', ['date' => '2026-11-22']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dateInfo.isSunday', true)
            ->where('dateInfo.isHoliday', true)
            ->where('dateInfo.holidayName', 'Hari Libur Uji'));
});

test('attendance skips employees without punch and updates an incomplete row inline', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nama' => 'Incomplete Punch Employee',
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    Karyawan::factory()->create([
        'nama' => 'No Punch Employee',
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'present',
        'first_in' => '07:41:00',
        'first_out' => null,
        'second_in' => null,
        'second_out' => null,
        'overtime_in' => null,
        'overtime_out' => null,
        'has_missing_punch' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', ['date' => '2026-11-19']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)
            ->where('records.data.0.employee.name', 'Incomplete Punch Employee')
            ->where('records.data.0.has_attendance', true)
            ->where('records.data.0.has_missing_punch', true));

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-19',
            'status' => 'present',
            'first_in' => '07:41',
            'first_out' => '12:05',
            'second_in' => '12:54',
            'second_out' => null,
            'overtime_in' => '17:49',
            'overtime_out' => '20:06',
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->second_out->toBeNull()
        ->overtime_in->toBe('17:49:00')
        ->overtime_out->toBe('20:06:00')
        ->worked_hours->toBe('8.0')
        ->overtime_hours->toBe('2.0')
        ->requires_review->toBeFalse();
});

test('inline edit recalculates holiday pay from the employee company calendar', function (
    bool $companyUsesHolidayPay,
    string $expectedWorkedHours,
    string $expectedHolidayHours,
) {
    $admin = User::factory()->create(['role' => 5]);
    $company = Company::factory()->create(['name' => 'Company Inline']);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'company_id' => $company->id,
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    NationalHoliday::factory()->create([
        'name' => 'Libur Nasional Jumat',
        'starts_on' => '2026-11-20',
        'ends_on' => '2026-11-20',
    ]);
    $override = $companyUsesHolidayPay
        ? null
        : CalendarOverride::factory()->create([
            'company_id' => $company->id,
            'calendar_date' => '2026-11-20',
            'name' => 'Masuk seperti Jumat biasa',
            'is_holiday_pay' => false,
            'schedule_day_type' => 'friday',
        ]);

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-20',
            'status' => 'present',
            'first_in' => '08:00',
            'first_out' => '11:30',
            'second_in' => '12:30',
            'second_out' => '17:00',
            'overtime_in' => null,
            'overtime_out' => null,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()
        ->where('karyawan_id', $employee->id)
        ->whereDate('attendance_date', '2026-11-20')
        ->firstOrFail();

    expect($record)
        ->is_holiday_pay->toBe($companyUsesHolidayPay)
        ->calendar_override_id->toBe($override?->id)
        ->worked_hours->toBe($expectedWorkedHours)
        ->holiday_worked_hours->toBe($expectedHolidayHours)
        ->requires_review->toBeFalse();
})->with([
    'company treats the holiday as a normal Friday' => [false, '7.5', '0.0'],
    'company keeps global holiday pay' => [true, '0.0', '16.0'],
]);
