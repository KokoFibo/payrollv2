<?php

namespace Database\Factories;

use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-6 months', 'now');
        $endDate = (clone $startDate)->modify('+1 month -1 day');

        return [
            'name' => fake()->unique()->bothify('Payroll-####-####'),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'pay_date' => (clone $endDate)->modify('+1 day')->format('Y-m-d'),
            'status' => 'draft',
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
            'processed_by' => null,
            'processed_at' => null,
            'notes' => null,
        ];
    }
}
