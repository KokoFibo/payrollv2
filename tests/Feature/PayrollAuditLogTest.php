<?php

use App\Models\AttendanceRecord;
use App\Models\Karyawan;
use App\Models\PayrollAuditLog;
use App\Models\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Support\PayrollAuditService;
use Inertia\Testing\AssertableInertia as Assert;

test('changing employee salary fields creates an immutable audit log', function () {
    $admin = User::factory()->create([
        'name' => 'Payroll Editor',
        'role' => 6,
    ]);
    $employee = Karyawan::factory()->create([
        'nip' => 3001,
        'nama' => 'Audited Employee',
        'gaji_pokok' => 5_000_000,
        'bonus' => 0,
    ]);

    $this->actingAs($admin);
    $employee->update([
        'gaji_pokok' => 6_500_000,
        'bonus' => 750_000,
    ]);

    $auditLog = PayrollAuditLog::query()->firstOrFail();

    expect($auditLog)
        ->actor_name->toBe('Payroll Editor')
        ->event_type->toBe('employee_updated')
        ->employee_nip->toBe('3001')
        ->employee_name->toBe('Audited Employee');
    $changes = collect($auditLog->changes)->keyBy('field');

    expect($changes['gaji_pokok'])->toMatchArray([
        'field' => 'gaji_pokok',
        'label' => 'Gaji pokok',
        'old' => 5_000_000,
        'new' => 6_500_000,
    ]);
    expect($changes['bonus'])->toMatchArray([
        'field' => 'bonus',
        'label' => 'Bonus',
        'old' => 0,
        'new' => 750_000,
    ]);
    expect(fn () => $auditLog->update(['subject_label' => 'Changed']))
        ->toThrow(LogicException::class);
});

test('non payroll employee updates do not create audit logs', function () {
    $admin = User::factory()->create(['role' => 6]);
    $employee = Karyawan::factory()->create(['nama' => 'Original Name']);

    $this->actingAs($admin);
    $employee->update(['nama' => 'Updated Name']);

    expect(PayrollAuditLog::query()->count())->toBe(0);
});

test('payroll period and payroll setting changes are audited', function () {
    $superAdmin = User::factory()->create(['role' => 7]);
    $period = PayrollPeriod::factory()->create([
        'name' => 'Audit Period',
        'status' => 'draft',
    ]);
    $setting = Setting::query()->create([
        'group' => 'payroll',
        'key' => 'payroll_cutoff_day',
        'value' => '20',
        'type' => 'number',
        'label' => 'Tanggal cutoff payroll',
    ]);

    $this->actingAs($superAdmin);
    $period->update(['status' => 'processed']);
    $setting->update(['value' => '25']);

    $logs = PayrollAuditLog::query()->orderBy('id')->get();

    expect($logs)->toHaveCount(2);
    expect($logs[0])
        ->event_type->toBe('payroll_period_updated')
        ->subject_label->toBe('Audit Period');
    expect(collect($logs[0]->changes)->pluck('field')->all())->toContain('status');
    expect($logs[1])
        ->event_type->toBe('payroll_setting_updated')
        ->subject_label->toBe('Tanggal cutoff payroll');
    expect(collect($logs[1]->changes)->first())
        ->toMatchArray([
            'field' => 'value',
            'old' => '20',
            'new' => '25',
        ]);
});

test('attendance payroll changes are logged with employee identity', function () {
    $developer = User::factory()->create(['role' => 8]);
    $employee = Karyawan::factory()->create([
        'nip' => 4001,
        'nama' => 'Attendance Audit Employee',
    ]);
    $record = AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-19',
        'overtime_hours' => 0,
        'night_shift_allowance' => 0,
    ]);

    $this->actingAs($developer);
    $record->update([
        'shift' => 'night',
        'overtime_hours' => 1.5,
        'night_shift_allowance' => 1,
    ]);

    $auditLog = PayrollAuditLog::query()->firstOrFail();

    expect($auditLog)
        ->event_type->toBe('attendance_updated')
        ->employee_nip->toBe('4001')
        ->employee_name->toBe('Attendance Audit Employee');
    expect(collect($auditLog->changes)->pluck('field')->all())
        ->toContain('shift', 'overtime_hours', 'night_shift_allowance');
});

test('super admin dashboard renders payroll audit logs', function () {
    $superAdmin = User::factory()->create(['role' => 7]);
    $auditService = app(PayrollAuditService::class);

    $this->actingAs($superAdmin);
    $auditService->record(
        eventType: 'payroll_setting_updated',
        subjectType: 'setting',
        subjectId: 1,
        subjectLabel: 'Cutoff payroll',
        changes: [[
            'field' => 'value',
            'label' => 'Cutoff payroll',
            'old' => '20',
            'new' => '25',
        ]],
    );

    $this->actingAs($superAdmin)
        ->get(route('admin.super-admin-dashboard.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Dashboard')
            ->has('payrollAuditLogs.data', 1)
            ->where('payrollAuditLogs.data.0.event_label', 'Konfigurasi payroll diubah')
            ->where('payrollAuditLogs.data.0.actor_name', $superAdmin->name)
            ->where('payrollAuditLogs.data.0.changes.0.field', 'value'));
});

test('administrators cannot access payroll audit logs on the super admin page', function () {
    $admin = User::factory()->create(['role' => 5]);

    $this->actingAs($admin)
        ->get(route('admin.super-admin-dashboard.index'))
        ->assertForbidden();
});
