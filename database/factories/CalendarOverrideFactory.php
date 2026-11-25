<?php

namespace Database\Factories;

use App\Models\CalendarOverride;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarOverride>
 */
class CalendarOverrideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'directorate_id' => null,
            'department_id' => null,
            'calendar_date' => fake()->dateTimeBetween('-1 year', '+1 year'),
            'name' => fake()->randomElement([
                'Libur perusahaan',
                'Hari kerja pengganti',
                'Cuti bersama',
            ]),
            'is_holiday_pay' => true,
            'schedule_day_type' => null,
            'notes' => 'Pengecualian kalender kerja perusahaan.',
        ];
    }
}
