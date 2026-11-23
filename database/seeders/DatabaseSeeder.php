<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CompanySeeder::class,
            DirectorateSeeder::class,
            DepartmentSeeder::class,
            JabatanSeeder::class,
            JobGradeSeeder::class,
            SettingsSeeder::class,
            AdminVisibilitySettingSeeder::class,
            UserSeeder::class,
        ]);
    }
}
