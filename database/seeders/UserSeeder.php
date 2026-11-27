<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $accounts = [
            ['email' => 'supervisor@gmail.com', 'name' => 'Supervisor', 'role' => 3, 'password' => 'Anton888'],
            ['email' => 'manager@gmail.com', 'name' => 'Manager', 'role' => 4, 'password' => 'Anton888'],
            ['email' => 'kokonacci@gmail.com', 'name' => 'Developer', 'role' => 8, 'password' => 'Anton888', 'must_change_password' => false],
            ['email' => 'super@gmail.com', 'name' => 'Super Admin', 'role' => 7, 'password' => 'Anton888', 'must_change_password' => false],
            ['email' => 'senior@gmail.com', 'name' => 'Senior Admin', 'role' => 6, 'password' => 'Anton888', 'must_change_password' => false],
            ['email' => 'admin@gmail.com', 'name' => 'Admin', 'role' => 5, 'password' => 'Anton888', 'must_change_password' => false],
            ['email' => 'funghuang@yahoo.com', 'name' => 'Rudy Chan', 'role' => 7, 'password' => '1234567890'],
            ['email' => 'viayifang25@gmail.com', 'name' => 'Novia Nurullita', 'role' => 6, 'password' => '1234567890'],
            ['email' => 'nukemutiarask@gmail.com', 'name' => 'Nuke Mutiara Sukma', 'role' => 5, 'password' => '1234567890'],
            ['email' => 'meggmrp@gmail.com', 'name' => 'Kwarti Artista Megawati Marpaung', 'role' => 5, 'password' => '1234567890'],
            ['email' => 'lulusyauqiyah@gmail.com', 'name' => 'Nila Vildakh Syauqiyah', 'role' => 5, 'password' => '1234567890'],
            ['email' => 'karistaaulia36@gmail.com', 'name' => 'Karista Aulia', 'role' => 5, 'password' => '1234567890'],
            ['email' => 'anggitaaag8@gmail.com', 'name' => 'Anggita Gustiaraningsih', 'role' => 5, 'password' => '1234567890'],
            ['email' => 'merievyzen14@gmail.com', 'name' => 'Suzen Merievy', 'role' => 6, 'password' => '1234567890'],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($account['password']),
                    'role' => $account['role'],
                    'language' => 'Id',
                    'email_verified_at' => now(),
                ],
            );

            if ($user->wasRecentlyCreated) {
                $user->forceFill([
                    'must_change_password' => $account['must_change_password'] ?? true,
                ])->save();
            } elseif (
                ($account['must_change_password'] ?? null) === false
                && $user->must_change_password
            ) {
                $user->forceFill(['must_change_password' => false])->save();
            }

            $user->forceFill([
                'name' => $account['name'],
                'role' => $account['role'],
                'language' => 'Id',
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
