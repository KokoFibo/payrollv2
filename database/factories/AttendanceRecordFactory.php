<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\Karyawan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    public function definition(): array
    {
        $firstIn = fake()->time('H:i:s', '08:30:00');
        $firstOut = fake()->time('H:i:s', '18:00:00');

        return [
            'karyawan_id' => Karyawan::factory(),
            'attendance_date' => fake()->dateTimeBetween('-30 days', 'today')->format('Y-m-d'),
            'status' => fake()->randomElement(array_keys(AttendanceRecord::STATUSES)),
            'first_in' => $firstIn,
            'first_out' => $firstOut,
            'worked_hours' => 8,
            'worked_days' => 1,
            'overtime_hours' => fake()->randomElement([0, 0, 0.5, 1, 1.5]),
            'notes' => fake()->optional()->sentence(),
            'recorded_by' => null,
        ];
    }
}
