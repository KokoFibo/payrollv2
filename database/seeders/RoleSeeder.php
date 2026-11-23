<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (
            [
                3 => 'Supervisor',
                4 => 'Manager',
                5 => 'Admin',
                6 => 'Senior Admin',
                7 => 'Super Admin',
                8 => 'Developer',
            ] as $id => $name
        ) {
            Role::query()->updateOrCreate(['id' => $id], ['name' => $name]);
        }
    }
}
