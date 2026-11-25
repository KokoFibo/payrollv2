<?php

use App\Enums\KaryawanStatus;
use App\Models\Karyawan;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('payroll period calculates employee gross deductions and net salary', function () {
    $admin = User::factory()->create(['role' => 6]);
    Karyawan::factory()->create([
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'gaji_pokok' => 5_000_000,
        'gaji_tetap' => 750_000,
        'gaji_overtime' => 500_000,
        'gaji_shift_malam_satpam' => 100_000,
        'tunjangan_jabatan' => 300_000,
        'tunjangan_housing' => 400_000,
        'tunjangan_skill' => 100_000,
        'bonus' => 250_000,
        'gaji_bpjs' => 250_000,
        'iuran_air' => 50_000,
        'iuran_locker' => 25_000,
        'denda' => 10_000,
    ]);

    $this->actingAs($admin)
        ->post('/admin/payroll', [
            'name' => 'Payroll November 2026',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'pay_date' => '2026-12-01',
            'notes' => 'November payroll',
        ])
        ->assertRedirect();

    $period = PayrollPeriod::query()->firstOrFail();
    $item = PayrollItem::query()->firstOrFail();

    expect($period->total_gross)->toBe(7_400_000)
        ->and($period->total_deductions)->toBe(335_000)
        ->and($period->total_net)->toBe(7_065_000)
        ->and($item->gross_salary)->toBe(7_400_000)
        ->and($item->net_salary)->toBe(7_065_000);
});

test('payroll period moves from draft to processed and paid', function () {
    $admin = User::factory()->create(['role' => 6]);
    Karyawan::factory()->create(['status_karyawan' => KaryawanStatus::Pkwt->value]);
    $period = PayrollPeriod::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.payroll.process', $period))
        ->assertRedirect();

    expect($period->refresh()->status)->toBe('processed')
        ->and($period->processed_by)->toBe($admin->id);

    $this->actingAs($admin)
        ->post(route('admin.payroll.pay', $period))
        ->assertRedirect();

    expect($period->refresh()->status)->toBe('paid');
});

test('payroll cannot be created without active employees', function () {
    $admin = User::factory()->create(['role' => 6]);

    $this->actingAs($admin)
        ->from('/admin/payroll')
        ->post('/admin/payroll', [
            'name' => 'Empty Payroll',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'pay_date' => '2026-12-01',
        ])
        ->assertSessionHasErrors('name');

    $this->assertDatabaseCount('payroll_periods', 0);
});

test('payroll pages render period details for senior admins', function () {
    $admin = User::factory()->create(['role' => 6]);
    $period = PayrollPeriod::factory()->create(['name' => 'November Payroll']);
    Karyawan::factory()->create(['status_karyawan' => KaryawanStatus::Pkwt->value]);

    $this->actingAs($admin)
        ->get(route('admin.payroll.show', $period))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/Show')
            ->where('period.name', 'November Payroll'));
});

test('payroll forbids admin and lower roles', function (int $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get('/admin/payroll')->assertForbidden();
})->with([
    'admin' => [5],
    'supervisor' => [4],
]);
