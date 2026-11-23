<?php

namespace Database\Factories;

use App\Models\AdminVisibilitySetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminVisibilitySetting>
 */
class AdminVisibilitySettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admin_salary_limit' => 4_500_000,
        ];
    }
}
