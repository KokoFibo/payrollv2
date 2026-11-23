<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('user management is available to privileged roles', function (int $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data')
            ->has('roles'));
})->with([
    'super admin' => [7],
    'developer' => [8],
]);

test('user management forbids admin roles below privileged level', function (int $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get('/admin/users')->assertForbidden();
})->with([
    'supervisor' => [3],
    'manager' => [4],
    'admin' => [5],
    'senior admin' => [6],
]);

test('privileged user can create update and delete an internal user', function () {
    $this->seed(RoleSeeder::class);
    $developer = User::factory()->create(['role' => 8]);

    $this->actingAs($developer)
        ->post('/admin/users', [
            'name' => 'Internal Admin',
            'email' => 'internal@example.com',
            'username' => 7001,
            'role' => 5,
            'language' => 'Id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertRedirect();

    $user = User::query()->where('email', 'internal@example.com')->firstOrFail();

    expect($user->role)->toBe(5)
        ->and($user->email_verified_at)->not->toBeNull();

    $this->actingAs($developer)
        ->put(route('admin.users.update', $user), [
            'name' => 'Internal Senior Admin',
            'email' => 'internal@example.com',
            'username' => 7001,
            'role' => 6,
            'language' => 'En',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect();

    expect($user->refresh()->name)->toBe('Internal Senior Admin')
        ->and($user->role)->toBe(6)
        ->and($user->language)->toBe('En');

    $this->actingAs($developer)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect();

    $this->assertModelMissing($user);
});

test('privileged user cannot delete their own account from user management', function () {
    $developer = User::factory()->create(['role' => 8]);

    $this->actingAs($developer)
        ->from('/admin/users')
        ->delete(route('admin.users.destroy', $developer))
        ->assertSessionHasErrors('user');

    $this->assertModelExists($developer);
});

test('registration route remains removed', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});
