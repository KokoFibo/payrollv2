<?php

use App\Models\AttendanceImport;
use App\Models\AttendanceRecord;
use App\Models\Karyawan;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('developer removes attendance records and imports for selected months', function () {
    $developer = User::factory()->create(['role' => 8]);
    $karyawan = Karyawan::factory()->create();
    $augustImport = AttendanceImport::factory()->create([
        'imported_by' => $developer->id,
        'attendance_date' => '2026-08-10',
        'file_size' => 2048,
    ]);
    $septemberImport = AttendanceImport::factory()->create([
        'imported_by' => $developer->id,
        'attendance_date' => '2026-09-10',
        'file_size' => 1024,
    ]);
    $octoberImport = AttendanceImport::factory()->create([
        'imported_by' => $developer->id,
        'attendance_date' => '2026-10-10',
        'file_size' => 512,
    ]);
    $augustRecord = AttendanceRecord::factory()->create([
        'karyawan_id' => $karyawan->id,
        'attendance_date' => '2026-08-10',
        'attendance_import_id' => $augustImport->id,
    ]);
    $septemberRecord = AttendanceRecord::factory()->create([
        'karyawan_id' => $karyawan->id,
        'attendance_date' => '2026-09-10',
        'attendance_import_id' => $septemberImport->id,
    ]);
    $octoberRecord = AttendanceRecord::factory()->create([
        'karyawan_id' => $karyawan->id,
        'attendance_date' => '2026-10-10',
        'attendance_import_id' => $octoberImport->id,
    ]);

    $this->actingAs($developer)
        ->from(route('admin.developer-settings.index'))
        ->delete(route('admin.developer-settings.attendance-cleanup.destroy'), [
            'months' => ['2026-08', '2026-09'],
        ])
        ->assertRedirect(route('admin.developer-settings.index'))
        ->assertSessionHas('attendance_cleanup_result', fn (array $result): bool => $result === [
            'months' => ['2026-09', '2026-08'],
            'record_rows' => 2,
            'import_rows' => 2,
            'file_bytes' => 3072,
            'deleted_records' => 2,
            'deleted_imports' => 2,
        ])
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'success');

    $this->assertModelMissing($augustRecord);
    $this->assertModelMissing($septemberRecord);
    $this->assertModelMissing($augustImport);
    $this->assertModelMissing($septemberImport);
    $this->assertModelExists($octoberRecord);
    $this->assertModelExists($octoberImport);
});

test('attendance cleanup requires at least one valid month', function (array $months, string $errorKey) {
    $developer = User::factory()->create(['role' => 8]);

    $this->actingAs($developer)
        ->from(route('admin.developer-settings.index'))
        ->delete(route('admin.developer-settings.attendance-cleanup.destroy'), [
            'months' => $months,
        ])
        ->assertRedirect(route('admin.developer-settings.index'))
        ->assertSessionHasErrors($errorKey);
})->with([
    'empty selection' => [[], 'months'],
    'invalid month' => [['2026-13'], 'months.0'],
]);

test('non developers cannot clean attendance data', function () {
    $admin = User::factory()->create(['role' => 5]);

    $this->actingAs($admin)
        ->delete(route('admin.developer-settings.attendance-cleanup.destroy'), [
            'months' => ['2026-09'],
        ])
        ->assertForbidden();
});

test('developer settings include recent attendance cleanup month options', function () {
    $this->travelTo('2026-11-25 10:00:00');
    $developer = User::factory()->create(['role' => 8]);
    $karyawan = Karyawan::factory()->create();
    $import = AttendanceImport::factory()->create([
        'attendance_date' => '2026-09-10',
        'file_size' => 1024,
    ]);
    AttendanceRecord::factory()->create([
        'karyawan_id' => $karyawan->id,
        'attendance_date' => '2026-09-10',
        'attendance_import_id' => $import->id,
    ]);

    $this->actingAs($developer)
        ->get(route('admin.developer-settings.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('attendanceCleanupMonths.2.value', '2026-09')
            ->where('attendanceCleanupMonths.2.record_count', 1)
            ->where('attendanceCleanupMonths.2.import_count', 1)
            ->where('attendanceCleanupMonths.2.file_bytes', 1024));
});
