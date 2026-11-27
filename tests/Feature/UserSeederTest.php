<?php

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

test('user seeder creates the configured development accounts', function () {
    $this->seed(UserSeeder::class);

    $accounts = [
        'supervisor@gmail.com' => ['Supervisor', 3, 'Anton888', true],
        'manager@gmail.com' => ['Manager', 4, 'Anton888', true],
        'kokonacci@gmail.com' => ['Developer', 8, 'Anton888', false],
        'super@gmail.com' => ['Super Admin', 7, 'Anton888', false],
        'senior@gmail.com' => ['Senior Admin', 6, 'Anton888', false],
        'admin@gmail.com' => ['Admin', 5, 'Anton888', false],
        'funghuang@yahoo.com' => ['Rudy Chan', 7, '1234567890', true],
        'viayifang25@gmail.com' => ['Novia Nurullita', 6, '1234567890', true],
        'nukemutiarask@gmail.com' => ['Nuke Mutiara Sukma', 5, '1234567890', true],
        'meggmrp@gmail.com' => ['Kwarti Artista Megawati Marpaung', 5, '1234567890', true],
        'lulusyauqiyah@gmail.com' => ['Nila Vildakh Syauqiyah', 5, '1234567890', true],
        'karistaaulia36@gmail.com' => ['Karista Aulia', 5, '1234567890', true],
        'anggitaaag8@gmail.com' => ['Anggita Gustiaraningsih', 5, '1234567890', true],
        'merievyzen14@gmail.com' => ['Suzen Merievy', 6, '1234567890', true],
    ];

    expect(User::query()->count())->toBe(14);

    foreach ($accounts as $email => [$name, $role, $password, $mustChangePassword]) {
        $user = User::query()->where('email', $email)->firstOrFail();

        expect($user->name)->toBe($name)
            ->and($user->role)->toBe($role)
            ->and($user->language)->toBe('Id')
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->must_change_password)->toBe($mustChangePassword)
            ->and(Hash::check($password, $user->password))->toBeTrue();
    }
});

test('user seeder does not duplicate accounts when run repeatedly', function () {
    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::query()->count())->toBe(14);
});

test('user seeder does not reset passwords or force flags after initial creation', function () {
    $this->seed(UserSeeder::class);
    $user = User::query()->where('email', 'funghuang@yahoo.com')->firstOrFail();
    $user->forceFill([
        'password' => Hash::make('PrivatePassword123!'),
        'must_change_password' => false,
    ])->save();

    $this->seed(UserSeeder::class);

    expect($user->refresh())
        ->must_change_password->toBeFalse()
        ->and(Hash::check('PrivatePassword123!', $user->password))->toBeTrue();
});

test('user seeder keeps legacy admin accounts exempt from forced password changes', function () {
    $this->seed(UserSeeder::class);
    $user = User::query()->where('email', 'admin@gmail.com')->firstOrFail();
    $user->forceFill(['must_change_password' => true])->save();

    $this->seed(UserSeeder::class);

    expect($user->refresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('Anton888', $user->password))->toBeTrue();
});

test('seeded account can authenticate with the configured password', function (string $email, string $password) {
    $this->seed(UserSeeder::class);
    $user = User::query()->where('email', $email)->firstOrFail();

    $this->post('/login', [
        'email' => $email,
        'password' => $password,
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
})->with([
    'developer' => ['kokonacci@gmail.com', 'Anton888'],
    'super admin' => ['super@gmail.com', 'Anton888'],
    'senior admin' => ['senior@gmail.com', 'Anton888'],
    'admin' => ['admin@gmail.com', 'Anton888'],
    'new super admin' => ['funghuang@yahoo.com', '1234567890'],
    'new senior admin' => ['merievyzen14@gmail.com', '1234567890'],
]);

test('legacy seeded admins are not forced to change their password', function (string $email) {
    $this->seed(UserSeeder::class);
    $user = User::query()->where('email', $email)->firstOrFail();

    $this->post('/login', [
        'email' => $email,
        'password' => 'Anton888',
    ])->assertRedirect(route('admin.dashboard'));

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
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
