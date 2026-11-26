<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guide page is available to payroll admin roles', function (int $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('admin.guide.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Panduan/Index'));
})->with([
    'admin' => [5],
    'senior admin' => [6],
    'super admin' => [7],
    'developer' => [8],
]);

test('guide page requires an authenticated user', function () {
    $this->get(route('admin.guide.index'))
        ->assertRedirect(route('login'));
});

test('guide page rejects roles without payroll access', function () {
    $user = User::factory()->create(['role' => 4]);

    $this->actingAs($user)
        ->get(route('admin.guide.index'))
        ->assertForbidden();
});
