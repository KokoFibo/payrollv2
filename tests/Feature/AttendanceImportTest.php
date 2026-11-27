<?php

use App\Enums\PayrollMethod;
use App\Models\AdminVisibilitySetting;
use App\Models\AttendanceImport;
use App\Models\AttendanceRecord;
use App\Models\CalendarOverride;
use App\Models\Company;
use App\Models\Department;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\NationalHoliday;
use App\Models\User;
use App\Support\Attendance\AttendancePolicySettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

test('admin imports attendance with employee matching and schedule comparison', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $securityDepartment = Department::factory()->create(['name' => 'Security']);
    $quality = Department::factory()->create(['name' => 'Quality']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $security = Jabatan::factory()->create(['name' => 'Security']);
    $qcAging = Jabatan::factory()->create(['name' => 'QC Aging']);

    $employees = [
        1001 => Karyawan::factory()->create([
            'nip' => 1001,
            'nama' => 'Database Employee',
            'department_id' => $production->id,
            'jabatan_id' => $generalWorker->id,
            'metode_penggajian' => PayrollMethod::Monthly->value,
        ]),
        1002 => Karyawan::factory()->create([
            'nip' => 1002,
            'nama' => 'Hourly Late Employee',
            'department_id' => $production->id,
            'jabatan_id' => $generalWorker->id,
            'metode_penggajian' => PayrollMethod::Hourly->value,
        ]),
        1003 => Karyawan::factory()->create([
            'nip' => 1003,
            'nama' => 'No Punch Employee',
            'department_id' => $production->id,
            'jabatan_id' => $generalWorker->id,
        ]),
        1004 => Karyawan::factory()->create([
            'nip' => 1004,
            'nama' => 'Duplicate Punch Employee',
            'department_id' => $production->id,
            'jabatan_id' => $generalWorker->id,
        ]),
        1005 => Karyawan::factory()->create([
            'nip' => 1005,
            'nama' => 'Missing Punch Employee',
            'department_id' => $production->id,
            'jabatan_id' => $generalWorker->id,
        ]),
        2001 => Karyawan::factory()->create([
            'nip' => 2001,
            'nama' => 'Night Security',
            'department_id' => $securityDepartment->id,
            'jabatan_id' => $security->id,
        ]),
        3001 => Karyawan::factory()->create([
            'nip' => 3001,
            'nama' => 'QC Aging Employee',
            'department_id' => $quality->id,
            'jabatan_id' => $qcAging->id,
        ]),
    ];

    $file = UploadedFile::fake()->createWithContent(
        '19 november.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [1001, 'Excel Employee Name', 'PD', '7:55 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '5:05 PM'],
            [1002, 'Excel Late Employee', 'PD', '8:04 AM'],
            [null, null, null, '11:35 AM'],
            [null, null, null, '12:25 PM'],
            [null, null, null, '5:00 PM'],
            [1003, 'No Punch Employee', 'PD', null],
            [1004, 'Duplicate Punch Employee', 'PD', '7:58 AM'],
            [null, null, null, '7:58 AM'],
            [null, null, null, '7:58 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '5:00 PM'],
            [1005, 'Missing Punch Employee', 'PD', '7:55 AM'],
            [null, null, null, '12:00 PM'],
            [null, null, null, '5:00 PM'],
            [2001, 'Night Security', 'Security', '7:45 PM'],
            [null, null, null, '7:05 AM+'],
            [3001, 'QC Aging Employee', 'Quality', '8:00 AM'],
            [null, null, null, '11:00 AM'],
            [null, null, null, '12:00 PM'],
            [null, null, null, '5:00 PM'],
            [9999, 'Unknown Employee', 'PD', '7:45 AM'],
            [null, null, null, '12:00 PM'],
            [null, null, null, '1:00 PM'],
            [null, null, null, '5:00 PM'],
        ]),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirectToRoute('admin.attendance.index', [
            'date' => '2026-11-19',
        ]);

    $import = AttendanceImport::query()->firstOrFail();

    expect($import)
        ->attendance_date->toDateString()->toBe('2026-11-19')
        ->and($import->processed_employee_count)->toBe(6)
        ->and($import->skipped_without_punch_count)->toBe(1)
        ->and($import->unmatched_employee_count)->toBe(1)
        ->and($import->raw_punch_count)->toBe(23)
        ->and($import->duplicate_punch_count)->toBe(2)
        ->and($import->missing_punch_count)->toBe(1)
        ->and($import->late_count)->toBe(2)
        ->and($import->unmatched_user_ids)->toBe(['9999']);

    $records = AttendanceRecord::query()
        ->where('attendance_import_id', $import->id)
        ->get()
        ->keyBy('karyawan_id');

    expect($records)->toHaveCount(6)
        ->and($records->has($employees[1003]->id))->toBeFalse();

    expect($records[$employees[1001]->id])
        ->status->toBe('present')
        ->shift->toBe('day')
        ->schedule_profile->toBe('general')
        ->first_in->toBe('07:55:00')
        ->first_out->toBe('11:30:00')
        ->second_in->toBe('12:30:00')
        ->second_out->toBe('17:05:00')
        ->worked_hours->toBe('8.0')
        ->raw_punch_count->toBe(4)
        ->effective_punch_count->toBe(4)
        ->late_minutes->toBe(0)
        ->has_missing_punch->toBeFalse();

    expect($records[$employees[1002]->id])
        ->status->toBe('late')
        ->late_minutes->toBe(4)
        ->late_penalty_hours->toBe(1);

    expect($records[$employees[1004]->id])
        ->raw_punch_count->toBe(6)
        ->effective_punch_count->toBe(4)
        ->duplicate_punch_count->toBe(2)
        ->first_in->toBe('07:58:00')
        ->first_out->toBe('11:30:00')
        ->second_in->toBe('12:30:00')
        ->second_out->toBe('17:00:00')
        ->has_duplicate_punch->toBeTrue()
        ->requires_review->toBeFalse();

    expect($records[$employees[1005]->id])
        ->effective_punch_count->toBe(3)
        ->has_missing_punch->toBeTrue()
        ->early_leave_minutes->toBe(0);

    expect($records[$employees[2001]->id])
        ->status->toBe('present')
        ->schedule_profile->toBe('security')
        ->shift->toBe('night')
        ->effective_punch_count->toBe(2)
        ->has_missing_punch->toBeFalse()
        ->first_in->toBe('19:45:00')
        ->first_out->toBeNull()
        ->second_in->toBeNull()
        ->second_out->toBe('07:05:00')
        ->last_out_day_offset->toBe(1)
        ->worked_hours->toBe('8.0')
        ->late_minutes->toBe(0)
        ->late_penalty_hours->toBeNull()
        ->early_leave_minutes->toBe(0)
        ->requires_review->toBeFalse();

    expect($records[$employees[3001]->id])
        ->schedule_profile->toBe('qc_aging')
        ->requires_review->toBeFalse();

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', [
            'date' => '2026-11-19',
            'search' => 'Database Employee',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Index')
            ->where('records.data.0.employee.name', 'Database Employee')
            ->where('records.data.0.first_in', '07:55')
            ->where('records.data.0.clock_slots.1.time', '11:30')
            ->where('recentImports.0.filename', '19 november.xlsx')
            ->where('stats.incomplete', 1));
});

test('attendance import doubles regular and overtime hours on a national holiday', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 5001,
        'nama' => 'Holiday Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    NationalHoliday::factory()->create([
        'name' => 'Hari Libur Uji',
        'starts_on' => '2026-11-19',
        'ends_on' => '2026-11-19',
        'total_days' => 1,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'holiday.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Holiday Employee', 'Production', '8:00 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '6:30 PM'],
        ]),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->worked_hours->toBe('0.0')
        ->overtime_hours->toBe('0.0')
        ->holiday_worked_hours->toBe('16.0')
        ->holiday_overtime_hours->toBe('3.0')
        ->comparison_notes->toContain('dikali 2');
});

test('attendance import applies a company calendar override on a national holiday', function () {
    $admin = User::factory()->create(['role' => 5]);
    $company = Company::factory()->create(['name' => 'Company Hari Kerja']);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 5002,
        'nama' => 'Company Workday Employee',
        'company_id' => $company->id,
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    NationalHoliday::factory()->create([
        'name' => 'Libur Nasional Jumat',
        'starts_on' => '2026-11-20',
        'ends_on' => '2026-11-20',
    ]);
    $override = CalendarOverride::factory()->create([
        'company_id' => $company->id,
        'calendar_date' => '2026-11-20',
        'name' => 'Jumat tetap masuk',
        'is_holiday_pay' => false,
        'schedule_day_type' => 'friday',
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'company-workday.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '20'],
            [$employee->nip, 'Company Workday Employee', 'Production', '8:00 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '5:00 PM'],
        ], '2026-11-20'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->is_holiday_pay->toBeFalse()
        ->calendar_override_id->toBe($override->id)
        ->worked_hours->toBe('7.5')
        ->holiday_worked_hours->toBe('0.0')
        ->comparison_notes->toContain('diperlakukan sebagai hari kerja');
});

test('attendance import maps a missing second checkout into overtime slots', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 2017,
        'nama' => 'Patmawati',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'patmawati.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Patmawati', 'Production', '7:41 AM'],
            [null, null, null, '12:05 PM'],
            [null, null, null, '12:54 PM'],
            [null, null, null, '5:49 PM'],
            [null, null, null, '8:06 PM'],
        ]),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->first_in->toBe('07:41:00')
        ->first_out->toBe('12:05:00')
        ->second_in->toBe('12:54:00')
        ->second_out->toBeNull()
        ->overtime_in->toBe('17:49:00')
        ->overtime_out->toBe('20:06:00')
        ->worked_hours->toBe('8.0')
        ->overtime_hours->toBe('2.0')
        ->requires_review->toBeTrue()
        ->comparison_notes->toContain('Punch keluar reguler (second out) belum tersedia.');
});

test('attendance import maps two effective punches to first in and second out', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 6017,
        'nama' => 'Two Punch Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'two-punch.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '24'],
            [$employee->nip, 'Two Punch Employee', 'Production', '6:46 AM'],
            [null, null, null, '6:46 AM'],
            [null, null, null, '6:47 AM'],
            [null, null, null, null],
            [null, null, null, null],
            [null, null, null, '4:05 PM'],
        ], '2026-11-24'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->first_in->toBe('06:46:00')
        ->first_out->toBeNull()
        ->second_in->toBeNull()
        ->second_out->toBe('16:05:00')
        ->raw_punch_count->toBe(4)
        ->effective_punch_count->toBe(2)
        ->duplicate_punch_count->toBe(2)
        ->has_missing_punch->toBeFalse()
        ->requires_review->toBeFalse()
        ->comparison_notes->toContain('Punch istirahat tidak ditemukan');
});

test('attendance import maps the break pair inside the configured break window', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 6021,
        'nama' => 'Break Window Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'break-window.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '20'],
            [$employee->nip, 'Break Window Employee', 'Production', '7:36 AM'],
            [null, null, null, '7:47 AM'],
            [null, null, null, '7:47 AM'],
            [null, null, null, '12:03 PM'],
            [null, null, null, '12:03 PM'],
            [null, null, null, '12:12 PM'],
            [null, null, null, '12:42 PM'],
            [null, null, null, '12:42 PM'],
            [null, null, null, '6:03 PM'],
            [null, null, null, '6:03 PM'],
        ], '2026-11-20'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->first_in->toBe('07:36:00')
        ->first_out->toBe('12:03:00')
        ->second_in->toBe('12:42:00')
        ->second_out->toBe('18:03:00')
        ->overtime_in->toBeNull()
        ->overtime_out->toBeNull()
        ->raw_punch_count->toBe(10)
        ->effective_punch_count->toBe(5)
        ->duplicate_punch_count->toBe(5)
        ->worked_hours->toBe('7.5')
        ->overtime_hours->toBe('1.0')
        ->has_missing_punch->toBeFalse()
        ->requires_review->toBeFalse();
});

test('attendance import ignores extra punches still inside the break window', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 6027,
        'nama' => 'Break Window Noise Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'break-window-noise.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Break Window Noise Employee', 'Production', '5:04 AM'],
            [null, null, null, '7:21 AM'],
            [null, null, null, '7:36 AM'],
            [null, null, null, '11:33 AM'],
            [null, null, null, '11:35 AM'],
            [null, null, null, '12:22 PM'],
            [null, null, null, '12:22 PM'],
            [null, null, null, '12:52 PM'],
            [null, null, null, '5:30 PM'],
            [null, null, null, '7:03 PM'],
            [null, null, null, '7:03 PM'],
            [null, null, null, '7:40 PM'],
            [null, null, null, '7:47 PM'],
        ], '2026-11-19'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->first_in->toBe('05:04:00')
        ->first_out->toBe('11:33:00')
        ->second_in->toBe('12:22:00')
        ->second_out->toBe('17:30:00')
        ->overtime_in->toBe('19:03:00')
        ->overtime_out->toBe('19:40:00')
        ->ignored_punch_count->toBe(3)
        ->has_missing_punch->toBeFalse()
        ->requires_review->toBeFalse();
});

test('attendance import marks an early break out as late for day and night shifts', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $dayEmployee = Karyawan::factory()->create([
        'nip' => 6022,
        'nama' => 'Early Day Break Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
        'metode_penggajian' => PayrollMethod::Hourly->value,
    ]);
    $nightEmployee = Karyawan::factory()->create([
        'nip' => 6023,
        'nama' => 'Early Night Break Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
        'metode_penggajian' => PayrollMethod::Hourly->value,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'early-break.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$dayEmployee->nip, 'Early Day Break Employee', 'Production', '8:00 AM'],
            [null, null, null, '11:29 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '5:00 PM'],
            [$nightEmployee->nip, 'Early Night Break Employee', 'Production', '8:00 PM'],
            [null, null, null, '10:59 PM'],
            [null, null, null, '11:30 PM'],
            [null, null, null, '5:00 AM+'],
        ], '2026-11-19'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $records = AttendanceRecord::query()
        ->whereIn('karyawan_id', [$dayEmployee->id, $nightEmployee->id])
        ->get()
        ->keyBy('karyawan_id');

    expect($records[$dayEmployee->id])
        ->first_out->toBe('11:29:00')
        ->status->toBe('late')
        ->late_minutes->toBe(1)
        ->late_penalty_hours->toBe(1)
        ->comparison_notes->toContain('Keluar istirahat 1 menit lebih awal.');

    expect($records[$nightEmployee->id])
        ->shift->toBe('night')
        ->first_out->toBe('22:59:00')
        ->status->toBe('late')
        ->late_minutes->toBe(1)
        ->late_penalty_hours->toBe(1)
        ->comparison_notes->toContain('Keluar istirahat 1 menit lebih awal.');
});

test('attendance import deducts regular work hours by full late hours', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $fourMinuteEmployee = Karyawan::factory()->create([
        'nip' => 6028,
        'nama' => 'Four Minute Late',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $oneHourEmployee = Karyawan::factory()->create([
        'nip' => 6029,
        'nama' => 'One Hour Four Late',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'late-work-deduction.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$fourMinuteEmployee->nip, 'Four Minute Late', 'Production', '8:04 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '5:00 PM'],
            [$oneHourEmployee->nip, 'One Hour Four Late', 'Production', '9:04 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '5:00 PM'],
        ], '2026-11-19'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $records = AttendanceRecord::query()
        ->whereIn('karyawan_id', [$fourMinuteEmployee->id, $oneHourEmployee->id])
        ->get()
        ->keyBy('karyawan_id');

    expect($records[$fourMinuteEmployee->id])
        ->late_minutes->toBe(4)
        ->late_penalty_hours->toBe(1)
        ->worked_hours->toBe('7.0')
        ->status->toBe('late');

    expect($records[$oneHourEmployee->id])
        ->late_minutes->toBe(64)
        ->late_penalty_hours->toBe(2)
        ->worked_hours->toBe('6.0')
        ->status->toBe('late');
});

test('attendance import uses the configured attendance tolerance', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 6032,
        'nama' => 'Configured Tolerance',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    AdminVisibilitySetting::factory()->create(['id' => 1]);
    app(AttendancePolicySettings::class)->updateToleranceMinutes(0);
    $file = UploadedFile::fake()->createWithContent(
        'configured-tolerance.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Configured Tolerance', 'Production', '8:03 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '5:00 PM'],
        ], '2026-11-19'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->status->toBe('late')
        ->late_minutes->toBe(3)
        ->late_penalty_hours->toBe(1)
        ->worked_hours->toBe('7.0');
});

test('attendance import deducts holiday work hours by late penalty hours', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 6030,
        'nama' => 'Holiday Late Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'holiday-late-deduction.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '22'],
            [$employee->nip, 'Holiday Late Employee', 'Production', '8:04 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '5:00 PM'],
        ], '2026-11-22'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->is_holiday_pay->toBeTrue()
        ->worked_hours->toBe('0.0')
        ->holiday_worked_hours->toBe('14.0')
        ->late_penalty_hours->toBe(1)
        ->status->toBe('late');
});

test('attendance import applies second in return time and tolerance', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 6031,
        'nama' => 'Late Break Return',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'late-break-return.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Late Break Return', 'Production', '8:00 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:34 PM'],
            [null, null, null, '5:00 PM'],
        ], '2026-11-19'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->status->toBe('late')
        ->late_minutes->toBe(4)
        ->late_penalty_hours->toBe(1)
        ->worked_hours->toBe('7.0')
        ->comparison_notes->toContain('Terlambat masuk istirahat 4 menit.');
});

test('attendance import keeps an active review after corrected punches are imported', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 6020,
        'nama' => 'Corrected Punch Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $incompleteFile = UploadedFile::fake()->createWithContent(
        'incomplete-punch.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Corrected Punch Employee', 'Production', '8:00 AM'],
        ], '2026-11-19'),
    );
    $correctedFile = UploadedFile::fake()->createWithContent(
        'corrected-punch.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Corrected Punch Employee', 'Production', '8:00 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '5:00 PM'],
        ], '2026-11-19'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $incompleteFile,
        ])
        ->assertRedirect();
    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $correctedFile,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->worked_hours->toBe('8.0')
        ->has_missing_punch->toBeFalse()
        ->requires_review->toBeTrue()
        ->comparison_notes->toContain('Status review dipertahankan sampai admin menghapusnya secara manual.');
});

test('attendance import counts saturday night work from seventeen and overtime after midnight', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 6018,
        'nama' => 'Saturday Night Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'saturday-night.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '21'],
            [$employee->nip, 'Saturday Night Employee', 'Production', '4:39 PM'],
            [null, null, null, '1:03 AM+'],
        ], '2026-11-21'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->shift->toBe('night')
        ->expected_check_in->toBe('17:00:00')
        ->expected_check_out->toBe('00:00:00')
        ->first_in->toBe('16:39:00')
        ->second_out->toBe('01:03:00')
        ->second_out_day_offset->toBe(1)
        ->worked_hours->toBe('6.0')
        ->overtime_hours->toBe('1.0');
});

test('attendance import infers next day on night shift punches without plus suffix', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 6026,
        'nama' => 'Night Rollover Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'night-rollover.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '20'],
            [$employee->nip, 'Night Rollover Employee', 'Production', '7:46 PM'],
            [null, null, null, '12:01 AM'],
            [null, null, null, '12:52 AM'],
            [null, null, null, '6:33 AM'],
        ], '2026-11-20'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->shift->toBe('night')
        ->first_in->toBe('19:46:00')
        ->first_out->toBe('00:01:00')
        ->first_out_day_offset->toBe(1)
        ->second_in->toBe('00:52:00')
        ->second_in_day_offset->toBe(1)
        ->second_out->toBe('06:33:00')
        ->second_out_day_offset->toBe(1)
        ->worked_hours->toBe('8.0')
        ->overtime_hours->toBe('1.5')
        ->requires_review->toBeFalse();

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', ['date' => '2026-11-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.data.0.first_out_day_offset', 1));
});

test('attendance import does not count saturday overtime under thirty minutes after midnight', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 6028,
        'nama' => 'Saturday Short Overtime Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'saturday-short-overtime.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '21'],
            [$employee->nip, 'Saturday Short Overtime Employee', 'Production', '4:33 PM'],
            [null, null, null, '8:36 PM'],
            [null, null, null, '9:10 PM'],
            [null, null, null, '12:12 AM+'],
        ], '2026-11-21'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->shift->toBe('night')
        ->expected_check_in->toBe('17:00:00')
        ->expected_check_out->toBe('00:00:00')
        ->worked_hours->toBe('6.0')
        ->overtime_hours->toBe('0.0');
});

test('attendance import caps saturday security regular work at six hours', function () {
    $admin = User::factory()->create(['role' => 5]);
    $securityDepartment = Department::factory()->create(['name' => 'Security']);
    $securityPosition = Jabatan::factory()->create(['name' => 'Security']);
    $employee = Karyawan::factory()->create([
        'nip' => 6019,
        'nama' => 'Saturday Security Employee',
        'department_id' => $securityDepartment->id,
        'jabatan_id' => $securityPosition->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'security-overtime.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '21'],
            [$employee->nip, 'Saturday Security Employee', 'Security', '5:00 PM'],
            [null, null, null, '1:00 AM+'],
        ], '2026-11-21'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->schedule_profile->toBe('security')
        ->shift->toBe('night')
        ->expected_check_out->toBe('00:00:00')
        ->worked_hours->toBe('6.0')
        ->overtime_hours->toBe('2.0')
        ->has_missing_punch->toBeFalse();
});

test('attendance import ignores extra punches for two-punch security', function () {
    $admin = User::factory()->create(['role' => 5]);
    $securityDepartment = Department::factory()->create(['name' => 'Security']);
    $securityPosition = Jabatan::factory()->create(['name' => 'Security']);
    $employee = Karyawan::factory()->create([
        'nip' => 6024,
        'nama' => 'Two Punch Saturday Security',
        'department_id' => $securityDepartment->id,
        'jabatan_id' => $securityPosition->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'two-punch-security.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '21'],
            [$employee->nip, 'Two Punch Saturday Security', 'Security', '7:02 PM'],
            [null, null, null, '7:02 PM'],
            [null, null, null, '7:02 PM'],
            [null, null, null, '7:25 PM'],
            [null, null, null, '7:26 PM'],
            [null, null, null, '7:26 PM'],
            [null, null, null, '7:09 AM+'],
            [null, null, null, '7:09 AM+'],
            [null, null, null, '7:09 AM+'],
            [null, null, null, '7:09 AM+'],
        ], '2026-11-21'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->schedule_profile->toBe('security')
        ->shift->toBe('night')
        ->first_in->toBe('19:02:00')
        ->first_out->toBeNull()
        ->second_in->toBeNull()
        ->second_out->toBe('07:09:00')
        ->second_out_day_offset->toBe(1)
        ->overtime_in->toBeNull()
        ->overtime_out->toBeNull()
        ->raw_punch_count->toBe(10)
        ->effective_punch_count->toBe(3)
        ->duplicate_punch_count->toBe(7)
        ->ignored_punch_count->toBe(1)
        ->has_missing_punch->toBeFalse()
        ->requires_review->toBeFalse()
        ->comparison_notes->toContain('1 punch tambahan diabaikan setelah pemetaan slot.');
});

test('attendance import reviews security when two punches are near checkout', function () {
    $admin = User::factory()->create(['role' => 5]);
    $securityDepartment = Department::factory()->create(['name' => 'Security']);
    $securityPosition = Jabatan::factory()->create(['name' => 'Security']);
    $employee = Karyawan::factory()->create([
        'nip' => 6025,
        'nama' => 'Ambiguous Security Checkout',
        'department_id' => $securityDepartment->id,
        'jabatan_id' => $securityPosition->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'ambiguous-security-checkout.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Ambiguous Security Checkout', 'Security', '7:46 PM'],
            [null, null, null, '12:02 AM+'],
            [null, null, null, '12:30 AM+'],
            [null, null, null, '6:02 AM+'],
            [null, null, null, '6:58 AM+'],
        ], '2026-11-19'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->first_in->toBe('19:46:00')
        ->first_out->toBeNull()
        ->second_in->toBeNull()
        ->second_out->toBe('06:58:00')
        ->second_out_day_offset->toBe(1)
        ->ignored_punch_count->toBe(3)
        ->has_missing_punch->toBeFalse()
        ->requires_review->toBeTrue()
        ->comparison_notes->toContain('2 punch berada di sekitar jam pulang Security; checkout perlu ditinjau.');
});

test('attendance import keeps a fourth punch inside fifty nine minutes as second checkout', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 7017,
        'nama' => 'Late Checkout Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'late-checkout.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Late Checkout Employee', 'Production', '7:20 AM'],
            [null, null, null, '12:05 PM'],
            [null, null, null, '12:31 PM'],
            [null, null, null, '5:50 PM'],
        ]),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->second_out->toBe('17:50:00')
        ->overtime_out->toBeNull()
        ->worked_hours->toBe('8.0')
        ->overtime_hours->toBe('0.5');
});

test('attendance import keeps a fourth punch after the overtime threshold as second checkout', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 7018,
        'nama' => 'Long Work Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'long-work.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Long Work Employee', 'Production', '8:00 AM'],
            [null, null, null, '12:00 PM'],
            [null, null, null, '1:00 PM'],
            [null, null, null, '8:06 PM'],
        ]),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->second_out->toBe('20:06:00')
        ->overtime_out->toBeNull()
        ->worked_hours->toBe('8.0')
        ->overtime_hours->toBe('3.0');
});

test('attendance import rejects a multi-day workbook', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create(['nip' => 1001]);
    $file = UploadedFile::fake()->createWithContent(
        'multi-day.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Employee', 'Production', '8:00 AM'],
        ], '2026-11-19', '2026-11-20'),
    );

    $this->actingAs($admin)
        ->from(route('admin.attendance.index'))
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect(route('admin.attendance.index'))
        ->assertSessionHasErrors('attendance_file');

    expect(AttendanceImport::query()->count())->toBe(0);
});

test('attendance import forbids users who cannot manage payroll', function () {
    $user = User::factory()->create(['role' => 4]);

    $this->actingAs($user)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => UploadedFile::fake()->create('attendance.xlsx', 10),
        ])
        ->assertForbidden();
});

/**
 * @param  list<array{0: int|string|null, 1: string|null, 2: string|null, 3: string|null}>  $rows
 */
function attendanceImportWorkbook(
    array $rows,
    string $periodStart = '2026-11-19',
    ?string $periodEnd = null,
): string {
    $periodEnd ??= $periodStart;
    $directory = storage_path('framework/testing');
    File::ensureDirectoryExists($directory);
    $path = $directory.'/attendance-import-'.bin2hex(random_bytes(6)).'.xlsx';
    $archive = new ZipArchive;

    if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create attendance workbook fixture.');
    }

    try {
        $archive->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML);
        $archive->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
        $archive->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Attendance Logs" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML);
        $archive->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML);

        $sheetRows = [
            ['Attendance Logs', null, null, null],
            ["Period : {$periodStart} ~ {$periodEnd}", null, null, null],
        ];
        $sheetRows = [...$sheetRows, ...$rows];
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($sheetRows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $sheetXml .= '<row r="'.$rowNumber.'">';

            foreach (['A', 'B', 'C', 'D'] as $columnIndex => $column) {
                $value = $row[$columnIndex] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                $reference = $column.$rowNumber;

                if (is_int($value) || ctype_digit((string) $value)) {
                    $sheetXml .= '<c r="'.$reference.'"><v>'.htmlspecialchars(
                        (string) $value,
                        ENT_XML1 | ENT_QUOTES,
                        'UTF-8',
                    ).'</v></c>';

                    continue;
                }

                $sheetXml .= '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'
                    .htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    .'</t></is></c>';
            }

            $sheetXml .= '</row>';
        }

        $sheetXml .= '</sheetData></worksheet>';
        $archive->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    } finally {
        $archive->close();
    }

    $content = File::get($path);
    File::delete($path);

    return $content;
}
