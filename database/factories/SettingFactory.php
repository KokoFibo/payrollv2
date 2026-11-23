<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group' => 'general',
            'key' => fake()->unique()->slug(2).'_'.fake()->randomNumber(3),
            'value' => fake()->word(),
            'type' => 'string',
            'label' => fake()->words(2, true),
        ];
    }
}
