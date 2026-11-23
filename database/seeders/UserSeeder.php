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
            ['email' => 'supervisor@gmail.com', 'name' => 'Supervisor', 'role' => 3],
            ['email' => 'manager@gmail.com', 'name' => 'Manager', 'role' => 4],
            ['email' => 'kokonacci@gmail.com', 'name' => 'Developer', 'role' => 8],
            ['email' => 'super@gmail.com', 'name' => 'Super Admin', 'role' => 7],
            ['email' => 'senior@gmail.com', 'name' => 'Senior Admin', 'role' => 6],
            ['email' => 'admin@gmail.com', 'name' => 'Admin', 'role' => 5],
        ];

        foreach ($accounts as $account) {
            $password = Hash::make('Anton888');

            $user = User::query()->firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $password,
                    'role' => $account['role'],
                    'language' => 'Id',
                    'email_verified_at' => now(),
                ],
            );

            $user->update([
                'name' => $account['name'],
                'password' => $password,
                'role' => $account['role'],
                'language' => 'Id',
                'email_verified_at' => now(),
            ]);
        }
    }
}
