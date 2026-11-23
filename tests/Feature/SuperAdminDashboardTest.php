<?php

use App\Enums\KaryawanStatus;
use App\Models\Karyawan;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('super admin dashboard is available to privileged roles', function (int $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get('/admin/super-admin-dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Dashboard')
            ->has('readiness.eligibleEmployees')
            ->has('readiness.missingDirectorate'));
})->with([
    'super admin' => [7],
    'developer' => [8],
]);

test('super admin dashboard rejects ordinary admins', function () {
    $admin = User::factory()->create(['role' => 5]);

    $this->actingAs($admin)
        ->get('/admin/super-admin-dashboard')
        ->assertForbidden();
});

test('super admin can download salary adjustment templates', function (string $routeName) {
    $superAdmin = User::factory()->create(['role' => 7]);
    Karyawan::factory()->create([
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);

    $response = $this->actingAs($superAdmin)->get(route($routeName));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/zip');
})->with([
    'department' => ['admin.super-admin-dashboard.export-department'],
    'directorate' => ['admin.super-admin-dashboard.export-directorate'],
]);
