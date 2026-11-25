<?php

use App\Enums\KaryawanStatus;
use App\Models\Karyawan;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can update application settings', function () {
    $admin = User::factory()->create(['role' => 5]);
    $this->seed(SettingsSeeder::class);

    $this->actingAs($admin)
        ->put('/admin/settings', [
            'general' => [
                'company_name' => 'PT Modern Payroll',
                'locale' => 'id',
                'timezone' => 'Asia/Jakarta',
            ],
            'payroll' => [
                'cutoff_start_day' => 25,
                'cutoff_end_day' => 24,
                'pay_date' => 25,
            ],
            'attendance' => [
                'work_start_time' => '08:00',
                'late_tolerance_minutes' => 15,
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('settings', [
        'key' => 'company_name',
        'value' => 'PT Modern Payroll',
    ]);
});

test('reports render analytics for admins', function () {
    $admin = User::factory()->create(['role' => 6]);
    Karyawan::factory()->create([
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'gaji_pokok' => 4_500_000,
    ]);

    $this->actingAs($admin)
        ->get('/admin/reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('summary')
            ->where('summary.headcount', 1)
            ->where('summary.activeHeadcount', 1)
            ->has('statusBreakdown', 1)
            ->has('departmentBreakdown', 1));
});

test('reports are forbidden for admin role', function () {
    $admin = User::factory()->create(['role' => 5]);

    $this->actingAs($admin)->get('/admin/reports')->assertForbidden();
});
