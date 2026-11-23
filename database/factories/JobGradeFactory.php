<?php

namespace Database\Factories;

use App\Models\JobGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobGrade>
 */
class JobGradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'grade' => fake()->unique()->bothify('?#'),
            'grade_name' => fake()->randomElement(['Non Staff', 'Staff', 'Supervisor', 'Manager', 'Director']),
        ];
    }
}
