<?php

namespace Database\Factories;

use App\Models\Karyawan;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollItem>
 */
class PayrollItemFactory extends Factory
{
    public function definition(): array
    {
        $baseSalary = fake()->numberBetween(4_000_000, 12_000_000);
        $fixedSalary = fake()->numberBetween(0, 1_000_000);
        $overtimeSalary = fake()->numberBetween(0, 750_000);
        $nightShiftSalary = fake()->numberBetween(0, 500_000);
        $allowances = fake()->numberBetween(0, 1_500_000);
        $bonus = fake()->numberBetween(0, 1_000_000);
        $grossSalary = $baseSalary + $fixedSalary + $overtimeSalary + $nightShiftSalary + $allowances + $bonus;
        $bpjsDeduction = fake()->numberBetween(0, 300_000);
        $waterFee = fake()->numberBetween(0, 100_000);
        $lockerFee = fake()->numberBetween(0, 50_000);
        $penalty = fake()->numberBetween(0, 100_000);
        $otherDeduction = fake()->numberBetween(0, 250_000);
        $totalDeductions = $bpjsDeduction + $waterFee + $lockerFee + $penalty + $otherDeduction;

        return [
            'payroll_period_id' => PayrollPeriod::factory(),
            'karyawan_id' => Karyawan::factory(),
            'base_salary' => $baseSalary,
            'fixed_salary' => $fixedSalary,
            'overtime_salary' => $overtimeSalary,
            'night_shift_salary' => $nightShiftSalary,
            'allowances' => $allowances,
            'bonus' => $bonus,
            'gross_salary' => $grossSalary,
            'bpjs_deduction' => $bpjsDeduction,
            'water_fee' => $waterFee,
            'locker_fee' => $lockerFee,
            'penalty' => $penalty,
            'other_deduction' => $otherDeduction,
            'total_deductions' => $totalDeductions,
            'net_salary' => $grossSalary - $totalDeductions,
        ];
    }
}
