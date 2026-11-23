<?php

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

test('user seeder creates the configured development accounts', function () {
    $this->seed(UserSeeder::class);

    $accounts = [
        'supervisor@gmail.com' => ['Supervisor', 3],
        'manager@gmail.com' => ['Manager', 4],
        'kokonacci@gmail.com' => ['Developer', 8],
        'super@gmail.com' => ['Super Admin', 7],
        'senior@gmail.com' => ['Senior Admin', 6],
        'admin@gmail.com' => ['Admin', 5],
    ];

    expect(User::query()->count())->toBe(6);

    foreach ($accounts as $email => [$name, $role]) {
        $user = User::query()->where('email', $email)->firstOrFail();

        expect($user->name)->toBe($name)
            ->and($user->role)->toBe($role)
            ->and($user->language)->toBe('Id')
            ->and($user->email_verified_at)->not->toBeNull()
            ->and(Hash::check('Anton888', $user->password))->toBeTrue();
    }
});

test('user seeder does not duplicate accounts when run repeatedly', function () {
    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::query()->count())->toBe(6);
});

test('seeded account can authenticate with the configured password', function (string $email) {
    $this->seed(UserSeeder::class);
    $user = User::query()->where('email', $email)->firstOrFail();

    $this->post('/login', [
        'email' => $email,
        'password' => 'Anton888',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
})->with([
    'developer' => ['kokonacci@gmail.com'],
    'super admin' => ['super@gmail.com'],
    'senior admin' => ['senior@gmail.com'],
    'admin' => ['admin@gmail.com'],
]);

test('supervisor and manager accounts cannot authenticate', function (string $email) {
    $this->seed(UserSeeder::class);

    $this->post('/login', [
        'email' => $email,
        'password' => 'Anton888',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
})->with([
    'manager' => ['manager@gmail.com'],
    'supervisor' => ['supervisor@gmail.com'],
]);
