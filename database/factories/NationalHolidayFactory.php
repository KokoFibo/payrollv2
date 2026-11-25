<?php

namespace Database\Factories;

use App\Models\NationalHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NationalHoliday>
 */
class NationalHolidayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('-1 year', '+1 year');
        $endsOn = (clone $startsOn)->modify('+'.fake()->numberBetween(0, 3).' days');

        return [
            'name' => fake()->randomElement([
                'Hari Kemerdekaan',
                'Hari Raya Idul Fitri',
                'Hari Raya Natal',
                'Tahun Baru',
            ]),
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'total_days' => $startsOn->diff($endsOn)->days + 1,
        ];
    }
}
