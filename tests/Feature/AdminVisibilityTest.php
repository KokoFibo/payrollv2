<?php

use App\Enums\KaryawanStatus;
use App\Models\AdminVisibilitySetting;
use App\Models\Karyawan;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin sees all employees but salary fields above the limit are blank', function () {
    AdminVisibilitySetting::factory()->create(['admin_salary_limit' => 4_500_000]);
    $admin = User::factory()->create(['role' => 5]);
    Karyawan::factory()->create([
        'nip' => 900001,
        'nama' => 'Visible Salary Employee',
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'gaji_pokok' => 4_500_000,
        'gaji_bpjs' => 100_000,
    ]);
    Karyawan::factory()->create([
        'nip' => 900002,
        'nama' => 'Masked Salary Employee',
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'gaji_pokok' => 7_000_000,
        'gaji_bpjs' => 300_000,
        'gaji_overtime' => 500_000,
        'iuran_air' => 50_000,
        'iuran_locker' => 25_000,
    ]);

    $this->actingAs($admin)
        ->get('/admin/employees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('karyawans.data', 2)
            ->where('karyawans.data.0.nama', 'Masked Salary Employee')
            ->where('karyawans.data.0.salary_masked', true)
            ->where('karyawans.data.0.gaji_pokok', null)
            ->where('karyawans.data.0.gaji_bpjs', null)
            ->where('karyawans.data.0.gaji_overtime', null)
            ->where('karyawans.data.0.iuran_air', null)
            ->where('karyawans.data.0.iuran_locker', null)
            ->where('karyawans.data.1.nama', 'Visible Salary Employee')
            ->where('karyawans.data.1.salary_masked', false)
            ->where('karyawans.data.1.gaji_pokok', 4_500_000));
});

test('higher roles see salary values without masking', function (int $role) {
    AdminVisibilitySetting::factory()->create(['admin_salary_limit' => 4_500_000]);
    $user = User::factory()->create(['role' => $role]);
    Karyawan::factory()->create([
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'gaji_pokok' => 9_000_000,
    ]);

    $this->actingAs($user)
        ->get('/admin/employees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('karyawans.data', 1)
            ->where('karyawans.data.0.salary_masked', false)
            ->where('karyawans.data.0.gaji_pokok', 9_000_000));
})->with([
    'senior admin' => [6],
    'super admin' => [7],
    'developer' => [8],
]);

test('admin can open employee above the salary limit but payroll fields are hidden', function () {
    AdminVisibilitySetting::factory()->create(['admin_salary_limit' => 4_500_000]);
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create([
        'gaji_pokok' => 6_000_000,
        'gaji_overtime' => 500_000,
        'metode_penggajian' => 'Bulanan',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.employees.edit', $employee))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Form')
            ->where('salaryLocked', true)
            ->where('employee.gaji_pokok', null)
            ->where('employee.gaji_overtime', null)
            ->where('employee.metode_penggajian', null));
});

test('admin can edit employee within the salary limit', function () {
    AdminVisibilitySetting::factory()->create(['admin_salary_limit' => 4_500_000]);
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create(['gaji_pokok' => 4_500_000]);

    $this->actingAs($admin)
        ->get(route('admin.employees.edit', $employee))
        ->assertOk();
});

test('admin can edit non payroll data above the limit while payroll values stay unchanged', function () {
    AdminVisibilitySetting::factory()->create(['admin_salary_limit' => 4_500_000]);
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create([
        'gaji_pokok' => 6_000_000,
        'gaji_overtime' => 500_000,
        'metode_penggajian' => 'Bulanan',
    ]);
    $payload = $employee->only($employee->getFillable());
    $payload['nama'] = 'Updated Without Payroll Access';
    $payload['status_karyawan'] = $employee->status_karyawan->value;
    $payload['outsource'] = $employee->outsource->value;
    $payload['tanggal_lahir'] = $employee->tanggal_lahir?->toDateString();
    $payload['tanggal_bergabung'] = $employee->tanggal_bergabung?->toDateString();
    $payload['tanggal_resigned'] = $employee->tanggal_resigned?->toDateString();
    $payload['tanggal_blacklist'] = $employee->tanggal_blacklist?->toDateString();
    $payload['gaji_pokok'] = 9_000_000;
    $payload['gaji_overtime'] = 2_000_000;
    $payload['metode_penggajian'] = 'Harian';

    foreach (['potongan_jht', 'potongan_jp', 'potongan_jkk', 'potongan_jkm', 'potongan_kesehatan'] as $field) {
        $payload[$field] = (bool) ($payload[$field] ?? false);
    }

    $this->actingAs($admin)
        ->from(route('admin.employees.edit', $employee))
        ->put(route('admin.employees.update', $employee), $payload)
        ->assertRedirect(route('admin.employees.index'));

    expect($employee->refresh()->nama)->toBe('Updated Without Payroll Access')
        ->and($employee->gaji_pokok)->toBe(6_000_000)
        ->and($employee->gaji_overtime)->toBe(500_000)
        ->and($employee->metode_penggajian)->toBe('Bulanan');
});

test('developer can adjust the salary masking limit', function () {
    $developer = User::factory()->create(['role' => 8]);
    AdminVisibilitySetting::factory()->create([
        'id' => 1,
        'admin_salary_limit' => 4_500_000,
    ]);

    $this->actingAs($developer)
        ->put(route('admin.developer-settings.admin-salary-limit.update'), [
            'admin_salary_limit' => 7_500_000,
        ])
        ->assertRedirect();

    expect((int) AdminVisibilitySetting::query()->value('admin_salary_limit'))
        ->toBe(7_500_000);
});

test('developer settings exposes the salary masking limit', function () {
    $developer = User::factory()->create(['role' => 8]);
    AdminVisibilitySetting::factory()->create([
        'id' => 1,
        'admin_salary_limit' => 4_500_000,
    ]);

    $this->actingAs($developer)
        ->get('/admin/developer-settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DeveloperSettings/Index')
            ->where('adminSalaryLimit', 4_500_000));
});

test('developer can adjust the attendance tolerance', function () {
    $developer = User::factory()->create(['role' => 8]);
    AdminVisibilitySetting::factory()->create([
        'id' => 1,
        'attendance_tolerance_minutes' => 3,
    ]);

    $this->actingAs($developer)
        ->put(route('admin.developer-settings.attendance-tolerance.update'), [
            'attendance_tolerance_minutes' => 5,
        ])
        ->assertRedirect();

    expect((int) AdminVisibilitySetting::query()->value('attendance_tolerance_minutes'))
        ->toBe(5);
});

test('developer settings exposes the attendance tolerance', function () {
    $developer = User::factory()->create(['role' => 8]);
    AdminVisibilitySetting::factory()->create([
        'id' => 1,
        'attendance_tolerance_minutes' => 4,
    ]);

    $this->actingAs($developer)
        ->get('/admin/developer-settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DeveloperSettings/Index')
            ->where('attendanceToleranceMinutes', 4));
});

test('admin cannot export employees but senior admin can', function (int $role, int $status) {
    $user = User::factory()->create(['role' => $role]);
    Karyawan::factory()->create(['nama' => 'Export Employee']);

    $response = $this->actingAs($user)->get(route('admin.employees.export'));

    $response->assertStatus($status);
})->with([
    'admin' => [5, 403],
    'senior admin' => [6, 200],
    'developer' => [8, 200],
]);
