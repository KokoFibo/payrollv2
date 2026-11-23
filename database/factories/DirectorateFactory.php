<?php

namespace Database\Factories;

use App\Models\Directorate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Directorate>
 */
class DirectorateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
