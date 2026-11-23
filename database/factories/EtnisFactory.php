<?php

namespace Database\Factories;

use App\Models\Etnis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Etnis>
 */
class EtnisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
