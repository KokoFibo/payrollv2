<?php

use App\Enums\KaryawanStatus;
use App\Models\AttendanceImport;
use App\Models\AttendanceRecord;
use App\Models\Karyawan;
use App\Models\PayrollAuditLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('database attendance preview summarizes matched new and existing records', function () {
    useAttendanceSourceDatabase();
    $developer = User::factory()->create(['role' => 8]);
    $firstEmployee = Karyawan::factory()->create([
        'nip' => 5001,
        'nama' => 'Source Preview One',
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);
    $secondEmployee = Karyawan::factory()->create([
        'nip' => 5002,
        'nama' => 'Source Preview Two',
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);
    AttendanceRecord::factory()->for($firstEmployee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'present',
    ]);
    insertAttendanceSourceRows([
        attendanceSourceRow($firstEmployee, '2026-11-19', [
            'first_in' => '08:00:00',
            'first_out' => '11:30:00',
            'second_in' => '12:30:00',
            'second_out' => '17:00:00',
        ]),
        attendanceSourceRow($secondEmployee, '2026-11-19', [
            'first_in' => '08:05:00',
            'first_out' => '11:30:00',
            'second_in' => '12:30:00',
            'second_out' => '17:05:00',
        ]),
        attendanceSourceRow($secondEmployee, '2026-11-19', [
            'user_id' => 999999,
            'karyawan_id' => '00000000-0000-0000-0000-000000000000',
        ]),
    ]);

    $this->actingAs($developer)
        ->getJson(route('admin.attendance.database-import.preview', [
            'attendance_date' => '2026-11-19',
            'page' => 1,
            'per_page' => 15,
        ]))
        ->assertOk()
        ->assertJsonPath('source.database', DB::connection()->getDatabaseName())
        ->assertJsonPath('summary.total', 3)
        ->assertJsonPath('summary.matched', 2)
        ->assertJsonPath('summary.unmatched', 1)
        ->assertJsonPath('summary.existing', 1)
        ->assertJsonPath('summary.create', 1)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.nip', 5001)
        ->assertJsonPath('data.0.target_state', 'update')
        ->assertJsonPath('data.1.nip', 5002)
        ->assertJsonPath('data.1.target_state', 'create');
});

test('database attendance import updates and creates records with audit history', function () {
    useAttendanceSourceDatabase();
    $developer = User::factory()->create(['role' => 8]);
    $dayEmployee = Karyawan::factory()->create([
        'nip' => 5101,
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);
    $noScanEmployee = Karyawan::factory()->create([
        'nip' => 5102,
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);
    $existingRecord = AttendanceRecord::factory()->for($dayEmployee)->create([
        'attendance_date' => '2026-11-20',
        'status' => 'present',
        'first_in' => '07:00:00',
        'first_out' => null,
        'second_in' => null,
        'second_out' => null,
    ]);
    insertAttendanceSourceRows([
        attendanceSourceRow($dayEmployee, '2026-11-20', [
            'first_in' => '08:00:00',
            'first_out' => '11:30:00',
            'second_in' => '12:30:00',
            'second_out' => '17:00:00',
            'overtime_in' => '00:00:00',
            'overtime_out' => '00:00:00',
            'total_jam_kerja' => 8.0,
            'total_hari_kerja' => 1.0,
            'total_jam_lembur' => 1.5,
            'total_jam_kerja_libur' => 2.0,
            'total_jam_lembur_libur' => 3.0,
        ]),
        attendanceSourceRow($noScanEmployee, '2026-11-20', [
            'second_in' => '12:30:00',
            'no_scan' => 'No Scan',
            'no_scan_history' => 'No Scan',
        ]),
    ]);

    $this->actingAs($developer)
        ->post(route('admin.attendance.database-import.store'), [
            'attendance_date' => '2026-11-20',
        ])
        ->assertRedirect(route('admin.attendance.index', ['date' => '2026-11-20']));

    $import = AttendanceImport::query()->firstOrFail();

    expect($import)
        ->source_employee_count->toBe(2)
        ->processed_employee_count->toBe(2)
        ->unmatched_employee_count->toBe(0)
        ->missing_punch_count->toBe(1);
    expect($dayEmployee->attendanceRecords()->whereDate('attendance_date', '2026-11-20')->count())
        ->toBe(1);
    expect($existingRecord->refresh())
        ->first_in->toBe('08:00:00')
        ->first_out->toBe('11:30:00')
        ->second_in->toBe('12:30:00')
        ->second_out->toBe('17:00:00')
        ->overtime_in->toBeNull()
        ->overtime_out->toBeNull()
        ->worked_hours->toBe('8.0')
        ->worked_days->toBe('1.0')
        ->overtime_hours->toBe('1.5')
        ->holiday_worked_hours->toBe('2.0')
        ->holiday_overtime_hours->toBe('3.0')
        ->expected_check_in->toBeNull()
        ->expected_check_out->toBeNull()
        ->is_holiday_pay->toBeTrue()
        ->has_missing_punch->toBeFalse()
        ->attendance_import_id->toBe($import->id);
    expect(AttendanceRecord::query()
        ->where('karyawan_id', $noScanEmployee->id)
        ->whereDate('attendance_date', '2026-11-20')
        ->firstOrFail())
        ->status->toBe('present')
        ->has_missing_punch->toBeTrue()
        ->requires_review->toBeTrue();
    expect(PayrollAuditLog::query()
        ->where('event_type', 'attendance_imported')
        ->where('employee_nip', 5101)
        ->exists())->toBeTrue();
    expect(PayrollAuditLog::query()
        ->where('event_type', 'attendance_imported')
        ->where('employee_nip', 5102)
        ->exists())->toBeTrue();
});

test('database attendance import processes a full month and calculates review status', function () {
    useAttendanceSourceDatabase();
    $developer = User::factory()->create(['role' => 8]);
    $employee = Karyawan::factory()->create([
        'nip' => 5201,
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);
    insertAttendanceSourceRows([
        attendanceSourceRow($employee, '2026-11-01', [
            'first_in' => '08:00:00',
            'first_out' => '11:30:00',
            'second_in' => '12:30:00',
            'second_out' => '17:00:00',
            'total_jam_kerja' => 8.0,
            'total_hari_kerja' => 1.0,
        ]),
        attendanceSourceRow($employee, '2026-11-02', [
            'second_in' => '12:30:00',
            'no_scan' => 'No Scan',
            'no_scan_history' => 'No Scan',
        ]),
        attendanceSourceRow($employee, '2026-12-01', [
            'first_in' => '08:00:00',
            'first_out' => '11:30:00',
            'second_in' => '12:30:00',
            'second_out' => '17:00:00',
            'total_jam_kerja' => 8.0,
            'total_hari_kerja' => 1.0,
        ]),
    ]);

    $this->actingAs($developer)
        ->getJson(route('admin.attendance.database-import.preview', [
            'attendance_mode' => 'month',
            'attendance_month' => '2026-11',
            'page' => 1,
            'per_page' => 15,
        ]))
        ->assertOk()
        ->assertJsonPath('period.mode', 'month')
        ->assertJsonPath('period.start_date', '2026-11-01')
        ->assertJsonPath('period.end_date', '2026-11-30')
        ->assertJsonPath('summary.total', 2)
        ->assertJsonPath('summary.matched', 2)
        ->assertJsonPath('summary.review_required', 1)
        ->assertJsonCount(2, 'dates')
        ->assertJsonPath('dates.0.attendance_date', '2026-11-01')
        ->assertJsonCount(2, 'data');

    $this->actingAs($developer)
        ->post(route('admin.attendance.database-import.store'), [
            'attendance_mode' => 'month',
            'attendance_month' => '2026-11',
        ])
        ->assertRedirect(route('admin.attendance.index', ['date' => '2026-11-01']));

    $import = AttendanceImport::query()->firstOrFail();

    expect($import->attendance_date->toDateString())->toBe('2026-11-01');
    expect($import)
        ->processed_employee_count->toBe(2);
    expect(AttendanceRecord::query()
        ->where('attendance_import_id', $import->id)
        ->where('requires_review', true)
        ->count())->toBe(1);
    expect(AttendanceRecord::query()
        ->where('karyawan_id', $employee->id)
        ->whereBetween('attendance_date', ['2026-11-01', '2026-11-30'])
        ->count())->toBe(2);
    expect(AttendanceRecord::query()
        ->where('karyawan_id', $employee->id)
        ->whereDate('attendance_date', '2026-11-02')
        ->firstOrFail())
        ->requires_review->toBeTrue()
        ->has_missing_punch->toBeTrue();
    expect(AttendanceRecord::query()
        ->where('karyawan_id', $employee->id)
        ->whereDate('attendance_date', '2026-12-01')
        ->exists())->toBeFalse();
});

test('database attendance batch endpoint imports one source day', function () {
    useAttendanceSourceDatabase();
    $developer = User::factory()->create(['role' => 8]);
    $employee = Karyawan::factory()->create([
        'nip' => 5301,
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);
    insertAttendanceSourceRows([
        attendanceSourceRow($employee, '2026-11-03', [
            'first_in' => '08:00:00',
            'first_out' => '11:30:00',
            'second_in' => '12:30:00',
            'second_out' => '17:00:00',
            'total_jam_kerja' => 8.0,
            'total_hari_kerja' => 1.0,
        ]),
    ]);

    $this->actingAs($developer)
        ->postJson(route('admin.attendance.database-import.store-day'), [
            'attendance_mode' => 'date',
            'attendance_date' => '2026-11-03',
        ])
        ->assertOk()
        ->assertJsonPath('attendance_date', '2026-11-03')
        ->assertJsonPath('processed_employee_count', 1)
        ->assertJsonPath('review_required_count', 0);

    expect(AttendanceRecord::query()
        ->where('karyawan_id', $employee->id)
        ->whereDate('attendance_date', '2026-11-03')
        ->exists())->toBeTrue();
});

test('database attendance import marks an employee present with any single punch', function () {
    useAttendanceSourceDatabase();
    $developer = User::factory()->create(['role' => 8]);
    $employee = Karyawan::factory()->create([
        'nip' => 5401,
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);
    insertAttendanceSourceRows([
        attendanceSourceRow($employee, '2026-11-04', [
            'second_in' => '12:30:00',
        ]),
    ]);

    $this->actingAs($developer)
        ->post(route('admin.attendance.database-import.store'), [
            'attendance_date' => '2026-11-04',
        ])
        ->assertRedirect(route('admin.attendance.index', ['date' => '2026-11-04']));

    expect(AttendanceRecord::query()
        ->where('karyawan_id', $employee->id)
        ->whereDate('attendance_date', '2026-11-04')
        ->firstOrFail())
        ->status->toBe('present')
        ->has_missing_punch->toBeTrue()
        ->requires_review->toBeTrue();
});

test('database attendance import skips rows without any punch', function () {
    useAttendanceSourceDatabase();
    $developer = User::factory()->create(['role' => 8]);
    $employee = Karyawan::factory()->create([
        'nip' => 5501,
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);
    $validEmployee = Karyawan::factory()->create([
        'nip' => 5502,
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);
    insertAttendanceSourceRows([
        attendanceSourceRow($employee, '2026-11-05'),
        attendanceSourceRow($validEmployee, '2026-11-05', [
            'second_in' => '12:30:00',
        ]),
    ]);

    $this->actingAs($developer)
        ->post(route('admin.attendance.database-import.store'), [
            'attendance_date' => '2026-11-05',
        ])
        ->assertRedirect();

    expect(AttendanceRecord::query()
        ->where('karyawan_id', $employee->id)
        ->whereDate('attendance_date', '2026-11-05')
        ->exists())->toBeFalse();
    expect(AttendanceImport::query()->firstOrFail())
        ->processed_employee_count->toBe(1)
        ->skipped_without_punch_count->toBe(1);
});

test('database attendance import rejects unsupported roles', function () {
    useAttendanceSourceDatabase();
    $admin = User::factory()->create(['role' => 4]);

    $this->actingAs($admin)
        ->getJson(route('admin.attendance.database-import.preview', [
            'attendance_date' => '2026-11-19',
        ]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.attendance.database-import.store'), [
            'attendance_date' => '2026-11-19',
        ])
        ->assertForbidden();
});

test('database attendance import validates the attendance date', function () {
    useAttendanceSourceDatabase();
    $developer = User::factory()->create(['role' => 8]);

    $this->actingAs($developer)
        ->post(route('admin.attendance.database-import.store'), [
            'attendance_date' => 'not-a-date',
        ])
        ->assertSessionHasErrors('attendance_date');
});

function useAttendanceSourceDatabase(): void
{
    config()->set('database.source_database', DB::connection()->getDatabaseName());
    Schema::dropIfExists('yfrekappresensis');
    Schema::create('yfrekappresensis', function (Blueprint $table): void {
        $table->id();
        $table->foreignUuid('karyawan_id');
        $table->integer('user_id')->index();
        $table->date('date')->index();
        $table->time('first_in')->nullable();
        $table->time('first_out')->nullable();
        $table->time('second_in')->nullable();
        $table->time('second_out')->nullable();
        $table->time('overtime_in')->nullable();
        $table->time('overtime_out')->nullable();
        $table->decimal('total_jam_kerja', 6, 1)->nullable();
        $table->decimal('total_hari_kerja', 6, 1)->nullable();
        $table->decimal('total_jam_lembur', 6, 1)->nullable();
        $table->decimal('total_jam_kerja_libur', 6, 1)->nullable();
        $table->decimal('total_hari_kerja_libur', 6, 1)->nullable();
        $table->decimal('total_jam_lembur_libur', 6, 1)->nullable();
        $table->integer('late')->nullable();
        $table->string('no_scan')->nullable();
        $table->string('shift')->nullable();
        $table->string('no_scan_history')->nullable();
        $table->string('late_history')->nullable();
        $table->integer('shift_malam')->nullable();
        $table->timestamps();
    });
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function attendanceSourceRow(Karyawan $employee, string $date, array $overrides = []): array
{
    return [
        'karyawan_id' => $employee->uuid,
        'user_id' => $employee->nip,
        'date' => $date,
        'first_in' => null,
        'first_out' => null,
        'second_in' => null,
        'second_out' => null,
        'overtime_in' => null,
        'overtime_out' => null,
        'total_jam_kerja' => null,
        'total_hari_kerja' => null,
        'total_jam_lembur' => null,
        'total_jam_kerja_libur' => null,
        'total_hari_kerja_libur' => null,
        'total_jam_lembur_libur' => null,
        'late' => 0,
        'no_scan' => null,
        'shift' => 'Pagi',
        'no_scan_history' => null,
        'late_history' => '0',
        'shift_malam' => 0,
        'created_at' => now(),
        'updated_at' => now(),
        ...$overrides,
    ];
}

/**
 * @param  list<array<string, mixed>>  $rows
 */
function insertAttendanceSourceRows(array $rows): void
{
    DB::table('yfrekappresensis')->insert($rows);
}
