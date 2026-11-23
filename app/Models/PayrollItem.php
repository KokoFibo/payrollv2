<?php

namespace App\Models;

use Database\Factories\PayrollItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payroll_period_id',
    'karyawan_id',
    'base_salary',
    'fixed_salary',
    'overtime_salary',
    'night_shift_salary',
    'allowances',
    'bonus',
    'gross_salary',
    'bpjs_deduction',
    'water_fee',
    'locker_fee',
    'penalty',
    'other_deduction',
    'total_deductions',
    'net_salary',
])]
class PayrollItem extends Model
{
    /** @use HasFactory<PayrollItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'base_salary' => 'integer',
            'fixed_salary' => 'integer',
            'overtime_salary' => 'integer',
            'night_shift_salary' => 'integer',
            'allowances' => 'integer',
            'bonus' => 'integer',
            'gross_salary' => 'integer',
            'bpjs_deduction' => 'integer',
            'water_fee' => 'integer',
            'locker_fee' => 'integer',
            'penalty' => 'integer',
            'other_deduction' => 'integer',
            'total_deductions' => 'integer',
            'net_salary' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }
}
