<?php

namespace Database\Factories;

use App\Models\SpecialWorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecialWorkSchedule>
 */
class SpecialWorkScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('-6 months', '+6 months');
        $endsOn = (clone $startsOn)->modify('+'.fake()->numberBetween(7, 30).' days');

        return [
            'name' => 'Ramadan',
            'company_id' => null,
            'directorate_id' => null,
            'department_id' => null,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'schedule_profile' => 'general',
            'day_type' => 'sunday_thursday',
            'shift' => 'day',
            'check_in' => '08:00:00',
            'check_out' => '16:00:00',
            'check_out_day_offset' => 0,
            'standard_work_hours' => 8,
            'expected_punch_count' => 4,
            'notes' => 'Jadwal khusus bulan puasa.',
        ];
    }
}
