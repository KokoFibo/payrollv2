<?php

use App\Models\AttendanceRecord;
use App\Models\Karyawan;
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
        'overtime_minutes' => 60,
    ]);

    $this->actingAs($admin)
        ->get('/admin/attendance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Index')
            ->has('records.data', 1)
            ->where('records.data.0.employee.name', 'Attendance Employee')
            ->where('stats.present', 1)
            ->where('stats.overtimeHours', 1));
});

test('admin can create update and delete attendance', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create(['gaji_pokok' => 4_500_000]);

    $this->actingAs($admin)
        ->post('/admin/attendance', [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-20',
            'status' => 'late',
            'check_in' => '08:30',
            'check_out' => '17:00',
            'overtime_minutes' => 45,
            'notes' => 'Traffic delay',
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->firstOrFail();

    expect($record->karyawan_id)->toBe($employee->id)
        ->and($record->recorded_by)->toBe($admin->id)
        ->and($record->status)->toBe('late');

    $this->actingAs($admin)
        ->put(route('admin.attendance.update', $record), [
            'karyawan_id' => $employee->id,
            'attendance_date' => '2026-11-20',
            'status' => 'present',
            'check_in' => '08:00',
            'check_out' => '17:00',
            'overtime_minutes' => 0,
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
            'overtime_minutes' => 0,
        ])
        ->assertSessionHasErrors('karyawan_id');
});

test('attendance forbids non admin roles', function () {
    $user = User::factory()->create(['role' => 4]);

    $this->actingAs($user)->get('/admin/attendance')->assertForbidden();
});
