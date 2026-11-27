<?php

use App\Enums\PayrollMethod;
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

test('attendance filters records by shift before rendering', function () {
    $admin = User::factory()->create(['role' => 5]);
    $dayEmployee = Karyawan::factory()->create(['nama' => 'Day Shift Employee']);
    $nightEmployee = Karyawan::factory()->create(['nama' => 'Night Shift Employee']);
    AttendanceRecord::factory()->for($dayEmployee)->create([
        'attendance_date' => '2026-11-19',
        'shift' => 'day',
    ]);
    AttendanceRecord::factory()->for($nightEmployee)->create([
        'attendance_date' => '2026-11-19',
        'shift' => 'night',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', [
            'date' => '2026-11-19',
            'shift' => 'night',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)
            ->where('records.data.0.employee.name', 'Night Shift Employee')
            ->where('filters.shift', 'night')
            ->has('options.shifts', 2));
});

test('security checkout before schedule is counted as a normal full shift', function (
    string $date,
    string $firstIn,
    string $secondOut,
) {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Security']);
    $jabatan = Jabatan::factory()->create(['name' => 'Security']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $employee->id,
            'attendance_date' => $date,
            'status' => 'present',
            'first_in' => $firstIn,
            'first_out' => null,
            'second_in' => null,
            'second_out' => $secondOut,
            'overtime_in' => null,
            'overtime_out' => null,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()
        ->where('karyawan_id', $employee->id)
        ->whereDate('attendance_date', $date)
        ->firstOrFail();

    expect($record)
        ->status->toBe('present')
        ->worked_hours->toBe('8.0')
        ->late_minutes->toBe(0)
        ->late_penalty_hours->toBeNull()
        ->early_leave_minutes->toBe(0);
})->with([
    'day shift checkout before 20:00' => [
        '2026-11-20',
        '08:00',
        '15:00',
    ],
    'night shift checkout before 08:00' => [
        '2026-11-20',
        '20:00',
        '04:00',
    ],
]);

test('security saturday night weights overtime before and after five', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Security']);
    $jabatan = Jabatan::factory()->create(['name' => 'Security']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-21',
            'status' => 'present',
            'first_in' => '17:00',
            'first_out' => null,
            'second_in' => null,
            'second_out' => '06:29',
            'overtime_in' => null,
            'overtime_out' => null,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()
        ->where('karyawan_id', $employee->id)
        ->whereDate('attendance_date', '2026-11-21')
        ->firstOrFail();

    expect($record)
        ->schedule_profile->toBe('security')
        ->shift->toBe('night')
        ->expected_check_out->toBe('00:00:00')
        ->second_out_day_offset->toBe(1)
        ->worked_hours->toBe('6.0')
        ->overtime_hours->toBe('11.0')
        ->comparison_notes->toContain('lembur pukul 00:00-05:00 dikali 2, setelah 05:00 dikali 1.');
});

test('saturday regular work uses six hours before lateness is deducted separately from overtime', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-21',
            'status' => 'present',
            'first_in' => '07:43',
            'first_out' => '12:00',
            'second_in' => '13:04',
            'second_out' => '16:06',
            'overtime_in' => null,
            'overtime_out' => null,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()
        ->where('karyawan_id', $employee->id)
        ->whereDate('attendance_date', '2026-11-21')
        ->firstOrFail();

    expect($record)
        ->status->toBe('late')
        ->worked_hours->toBe('5.0')
        ->overtime_hours->toBe('1.0')
        ->late_minutes->toBe(4)
        ->late_penalty_hours->toBe(1);
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

test('attendance exposes punch fields that caused lateness', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create(['nama' => 'Late Punch Employee']);
    AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'late',
        'late_minutes' => 25,
        'comparison_notes' => implode(' ', [
            'Terlambat masuk 10 menit.',
            'Keluar istirahat 6 menit lebih awal.',
            'Terlambat masuk istirahat 4 menit.',
            'Checkout 5 menit lebih awal.',
        ]),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', ['date' => '2026-11-19']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.data.0.employee.name', 'Late Punch Employee')
            ->where('records.data.0.late_fields', [
                'first_in',
                'first_out',
                'second_in',
                'second_out',
            ]));
});

test('attendance review filter shows unresolved and resolved records for the selected date', function () {
    $admin = User::factory()->create([
        'name' => 'Review Admin',
        'role' => 5,
    ]);
    $pendingEmployee = Karyawan::factory()->create([
        'nip' => 1001,
        'nama' => 'Pending Review',
    ]);
    $resolvedEmployee = Karyawan::factory()->create([
        'nip' => 1002,
        'nama' => 'Resolved Review',
    ]);
    $normalEmployee = Karyawan::factory()->create(['nama' => 'Normal Attendance']);
    $otherDateEmployee = Karyawan::factory()->create(['nama' => 'Other Date Review']);
    AttendanceRecord::factory()->for($pendingEmployee)->create([
        'attendance_date' => '2026-11-19',
        'requires_review' => true,
    ]);
    AttendanceRecord::factory()->for($resolvedEmployee)->create([
        'attendance_date' => '2026-11-19',
        'requires_review' => false,
        'review_resolved_at' => now(),
        'review_resolved_by' => $admin->id,
    ]);
    AttendanceRecord::factory()->for($normalEmployee)->create([
        'attendance_date' => '2026-11-19',
        'requires_review' => false,
    ]);
    AttendanceRecord::factory()->for($otherDateEmployee)->create([
        'attendance_date' => '2026-11-20',
        'requires_review' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', [
            'date' => '2026-11-19',
            'review_only' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 2)
            ->where('records.data.0.employee.name', 'Pending Review')
            ->where('records.data.0.requires_review', true)
            ->where('records.data.0.review_resolved_at', null)
            ->where('records.data.1.employee.name', 'Resolved Review')
            ->where('records.data.1.requires_review', false)
            ->where('records.data.1.review_resolved_by', 'Review Admin')
            ->where('filters.reviewOnly', true)
            ->where('filters.sort', 'employee.nip')
            ->where('filters.direction', 'asc'));
});

test('review filter keeps an edited record until review is done', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create(['nama' => 'Edited Review Employee']);
    $record = AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'late',
        'first_in' => '08:10:00',
        'has_missing_punch' => true,
        'requires_review' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-19',
            'status' => 'present',
            'first_in' => '08:00',
            'first_out' => '11:30',
            'second_in' => '12:30',
            'second_out' => '17:00',
            'overtime_in' => null,
            'overtime_out' => null,
        ])
        ->assertRedirect();

    expect($record->refresh())
        ->requires_review->toBeTrue()
        ->review_resolved_at->toBeNull();

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', [
            'date' => '2026-11-19',
            'review_only' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)
            ->where('records.data.0.employee.name', 'Edited Review Employee'));

    $this->actingAs($admin)
        ->put(route('admin.attendance.resolve-review', $record))
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', [
            'date' => '2026-11-19',
            'review_only' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)
            ->where('records.data.0.employee.name', 'Edited Review Employee')
            ->where('records.data.0.requires_review', false)
            ->where('records.data.0.review_resolved_by', $admin->name));
});

test('editing reviewed attendance requires another review', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create(['nama' => 'Previously Reviewed Employee']);
    $record = AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'present',
        'first_in' => '08:00:00',
        'first_out' => '12:00:00',
        'second_in' => '13:00:00',
        'second_out' => '17:00:00',
        'requires_review' => false,
        'review_resolved_at' => now(),
        'review_resolved_by' => $admin->id,
        'review_resolution_note' => 'Review sebelumnya.',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-19',
            'status' => 'present',
            'first_in' => '08:00',
            'first_out' => '12:00',
            'second_in' => '13:00',
            'second_out' => '16:30',
            'overtime_in' => null,
            'overtime_out' => null,
        ])
        ->assertRedirect();

    expect($record->refresh())
        ->requires_review->toBeTrue()
        ->review_resolved_at->toBeNull()
        ->review_resolved_by->toBeNull()
        ->review_resolution_note->toBeNull()
        ->comparison_notes->toContain('Data diubah setelah review; menunggu review ulang.');

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', [
            'date' => '2026-11-19',
            'review_only' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)
            ->where('records.data.0.employee.name', 'Previously Reviewed Employee')
            ->where('records.data.0.requires_review', true));
});

test('saving reviewed attendance without changes preserves review', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create();
    $record = AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'present',
        'first_in' => '08:00:00',
        'first_out' => '12:00:00',
        'second_in' => '13:00:00',
        'second_out' => '17:00:00',
        'requires_review' => false,
        'review_resolved_at' => now(),
        'review_resolved_by' => $admin->id,
        'review_resolution_note' => 'Review sebelumnya.',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-19',
            'status' => 'present',
            'first_in' => '08:00',
            'first_out' => '12:00',
            'second_in' => '13:00',
            'second_out' => '17:00',
            'overtime_in' => null,
            'overtime_out' => null,
        ])
        ->assertRedirect();

    expect($record->refresh())
        ->requires_review->toBeFalse()
        ->review_resolved_at->not->toBeNull()
        ->review_resolved_by->toBe($admin->id)
        ->review_resolution_note->toBe('Review sebelumnya.');
});

test('unreviewed attendance keeps its order when edited data changes the selected sort', function () {
    $admin = User::factory()->create(['role' => 5]);
    $pendingEmployee = Karyawan::factory()->create([
        'nip' => 2001,
        'nama' => 'Pending Sort Employee',
    ]);
    $reviewedEmployee = Karyawan::factory()->create([
        'nip' => 2002,
        'nama' => 'Reviewed Sort Employee',
    ]);
    AttendanceRecord::factory()->for($pendingEmployee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'late',
        'first_in' => '08:10:00',
        'late_minutes' => 10,
        'has_missing_punch' => true,
        'requires_review' => true,
    ]);
    AttendanceRecord::factory()->for($reviewedEmployee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'late',
        'first_in' => '08:05:00',
        'late_minutes' => 5,
        'requires_review' => false,
        'review_resolved_at' => now(),
        'review_resolved_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', [
            'date' => '2026-11-19',
            'sort' => 'late_minutes',
            'direction' => 'desc',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.data.0.employee.name', 'Pending Sort Employee'));

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $pendingEmployee->id,
            'attendance_date' => '2026-11-19',
            'status' => 'present',
            'first_in' => '08:00',
            'first_out' => '12:00',
            'second_in' => '13:00',
            'second_out' => '17:00',
            'overtime_in' => null,
            'overtime_out' => null,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', [
            'date' => '2026-11-19',
            'sort' => 'late_minutes',
            'direction' => 'desc',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.data.0.employee.name', 'Pending Sort Employee')
            ->where('records.data.0.requires_review', true)
            ->where('records.data.1.employee.name', 'Reviewed Sort Employee'));
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
        ->requires_review->toBeTrue()
        ->comparison_notes->toContain('Punch keluar reguler (second out) belum tersedia.');
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

test('correcting punches recalculates hours but keeps review for admin resolution', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
    ]);
    AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'late',
        'first_in' => '08:10:00',
        'late_minutes' => 10,
        'late_penalty_hours' => 1,
        'has_missing_punch' => true,
        'requires_review' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-19',
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
        ->whereDate('attendance_date', '2026-11-19')
        ->firstOrFail();

    expect($record)
        ->late_minutes->toBe(0)
        ->late_penalty_hours->toBeNull()
        ->worked_hours->toBe('8.0')
        ->has_missing_punch->toBeFalse()
        ->requires_review->toBeTrue()
        ->comparison_notes->toContain('Status review dipertahankan sampai admin menghapusnya secara manual.')
        ->review_resolved_at->toBeNull();
});

test('inline edit removes late status when corrected punches are on schedule', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create();
    AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'late',
        'first_in' => '08:10:00',
        'late_minutes' => 10,
        'late_penalty_hours' => 1,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-19',
            'status' => 'late',
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
        ->whereDate('attendance_date', '2026-11-19')
        ->firstOrFail();

    expect($record)
        ->status->toBe('present')
        ->late_minutes->toBe(0)
        ->late_penalty_hours->toBeNull();

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', ['date' => '2026-11-19']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.data.0.status', 'present')
            ->where('records.data.0.status_label', 'Hadir')
            ->where('records.data.0.late_minutes', 0));
});

test('inline edit marks an early break out as late', function () {
    $admin = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $jabatan = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
        'metode_penggajian' => PayrollMethod::Hourly->value,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-19',
            'status' => 'present',
            'first_in' => '08:00',
            'first_out' => '11:29',
            'second_in' => '12:30',
            'second_out' => '17:00',
            'overtime_in' => null,
            'overtime_out' => null,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()
        ->where('karyawan_id', $employee->id)
        ->whereDate('attendance_date', '2026-11-19')
        ->firstOrFail();

    expect($record)
        ->status->toBe('late')
        ->late_minutes->toBe(1)
        ->late_penalty_hours->toBe(1)
        ->comparison_notes->toContain('Keluar istirahat 1 menit lebih awal.');
});

test('inline edit reviews attendance when only one break punch exists', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-19',
            'status' => 'present',
            'first_in' => '08:00',
            'first_out' => '11:30',
            'second_in' => null,
            'second_out' => '17:00',
            'overtime_in' => null,
            'overtime_out' => null,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()
        ->where('karyawan_id', $employee->id)
        ->firstOrFail();

    expect($record)
        ->requires_review->toBeTrue()
        ->has_missing_punch->toBeTrue()
        ->comparison_notes->toContain('Pasangan keluar dan masuk istirahat tidak lengkap.');
});

test('inline edit reviews attendance when only one overtime punch exists', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.attendance.update-inline'), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-19',
            'status' => 'present',
            'first_in' => '08:00',
            'first_out' => '11:30',
            'second_in' => '12:30',
            'second_out' => '17:00',
            'overtime_in' => '18:00',
            'overtime_out' => null,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()
        ->where('karyawan_id', $employee->id)
        ->firstOrFail();

    expect($record)
        ->requires_review->toBeTrue()
        ->has_missing_punch->toBeTrue()
        ->comparison_notes->toContain('Pasangan masuk dan keluar lembur tidak lengkap.');
});

test('admin can resolve review without changing attendance punches', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create();
    $record = AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-19',
        'first_in' => '08:00:00',
        'requires_review' => true,
        'comparison_notes' => 'Punch keluar belum lengkap.',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.attendance.resolve-review', $record))
        ->assertRedirect();

    expect($record->refresh())
        ->requires_review->toBeFalse()
        ->first_in->toBe('08:00:00')
        ->comparison_notes->toBe('Punch keluar belum lengkap.')
        ->review_resolved_at->not->toBeNull()
        ->review_resolved_by->toBe($admin->id)
        ->review_resolution_note->toBe('Data ditandai sudah benar oleh admin.');
});

test('admin can delete review metadata without marking data correct', function () {
    $admin = User::factory()->create(['role' => 5]);
    $record = AttendanceRecord::factory()->create([
        'requires_review' => false,
        'review_resolved_at' => now(),
        'review_resolved_by' => $admin->id,
        'review_resolution_note' => 'Data sebelumnya ditandai benar.',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.attendance.destroy-review', $record))
        ->assertRedirect();

    expect($record->refresh())
        ->requires_review->toBeFalse()
        ->review_resolved_at->toBeNull()
        ->review_resolved_by->toBeNull()
        ->review_resolution_note->toBeNull();
});

test('attendance shows monthly review count and penalty after three reviews', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create(['nama' => 'Monthly Review Employee']);

    foreach (range(2, 5) as $day) {
        AttendanceRecord::factory()->for($employee)->create([
            'attendance_date' => "2026-11-0{$day}",
            'requires_review' => true,
        ]);
    }

    AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-06',
        'requires_review' => false,
        'review_resolved_at' => now(),
        'review_resolved_by' => $admin->id,
        'review_resolution_note' => 'Sudah diperiksa.',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', ['date' => '2026-11-05']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.data.0.monthly_review_count', 4)
            ->where('records.data.0.review_penalty_hours', 1));
});

test('non admin cannot resolve or delete attendance review', function () {
    $user = User::factory()->create(['role' => 4]);
    $record = AttendanceRecord::factory()->create([
        'requires_review' => true,
    ]);

    $this->actingAs($user)
        ->delete(route('admin.attendance.destroy-review', $record))
        ->assertForbidden();
    $this->actingAs($user)
        ->put(route('admin.attendance.resolve-review', $record))
        ->assertForbidden();

    expect($record->refresh()->requires_review)->toBeTrue();
});
