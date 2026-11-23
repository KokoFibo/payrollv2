<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('renders the payroll dashboard with live summary data', function () {
    $user = User::factory()->create(['role' => 5, 'email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('stats')
            ->has('today')
            ->where('auth.can.superAdmin', false));
});

test('redirects guests from the payroll workspace to login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});
