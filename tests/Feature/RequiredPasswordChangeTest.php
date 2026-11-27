<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('users with a required password change are redirected away from admin pages', function () {
    $user = User::factory()->create([
        'role' => 5,
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(route('password.change-required'));
});

test('required password page can be rendered', function () {
    $user = User::factory()->create([
        'name' => 'Required Password User',
        'role' => 5,
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->get(route('password.change-required'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ChangeRequiredPassword')
            ->where('userName', 'Required Password User'));
});

test('user can replace the required password and regain admin access', function () {
    $user = User::factory()->create([
        'role' => 5,
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->put(route('password.change-required.update'), [
            'current_password' => 'password',
            'password' => 'PrivatePassword123!',
            'password_confirmation' => 'PrivatePassword123!',
        ])
        ->assertRedirect(route('admin.dashboard'));

    expect($user->refresh())
        ->must_change_password->toBeFalse()
        ->and(Hash::check('PrivatePassword123!', $user->password))->toBeTrue();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
});

test('required password change rejects the current password as the new password', function () {
    $user = User::factory()->create([
        'role' => 5,
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->from(route('password.change-required'))
        ->put(route('password.change-required.update'), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('password.change-required'))
        ->assertSessionHasErrors('password');

    expect($user->refresh()->must_change_password)->toBeTrue();
});
