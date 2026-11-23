<?php

namespace Database\Seeders;

use App\Models\AdminVisibilitySetting;
use Illuminate\Database\Seeder;

class AdminVisibilitySettingSeeder extends Seeder
{
    public function run(): void
    {
        AdminVisibilitySetting::query()->updateOrCreate(
            ['id' => 1],
            ['admin_salary_limit' => 4_500_000],
        );
    }
}
