<?php

namespace Database\Factories;

use App\Models\AttendanceImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceImport>
 */
class AttendanceImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'imported_by' => User::factory(),
            'original_filename' => 'attendance.xlsx',
            'file_size' => 1024,
            'attendance_date' => today(),
            'source_employee_count' => 1,
            'processed_employee_count' => 1,
            'skipped_without_punch_count' => 0,
            'unmatched_employee_count' => 0,
            'raw_punch_count' => 4,
            'duplicate_punch_count' => 0,
            'missing_punch_count' => 0,
            'late_count' => 0,
            'early_leave_count' => 0,
            'unmatched_user_ids' => [],
            'warnings' => [],
        ];
    }
}
