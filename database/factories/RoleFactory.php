<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 9999),
            'name' => fake()->unique()->jobTitle(),
        ];
    }
}
