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
        $checkIn = fake()->time('H:i:s', '08:30:00');

        return [
            'karyawan_id' => Karyawan::factory(),
            'attendance_date' => fake()->dateTimeBetween('-30 days', 'today')->format('Y-m-d'),
            'status' => fake()->randomElement(array_keys(AttendanceRecord::STATUSES)),
            'check_in' => $checkIn,
            'check_out' => fake()->time('H:i:s', '18:00:00'),
            'overtime_minutes' => fake()->randomElement([0, 0, 30, 60, 90]),
            'notes' => fake()->optional()->sentence(),
            'recorded_by' => null,
        ];
    }
}
