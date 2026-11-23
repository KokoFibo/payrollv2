<?php

namespace App\Models;

use Database\Factories\PayrollPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'start_date',
    'end_date',
    'pay_date',
    'status',
    'total_gross',
    'total_deductions',
    'total_net',
    'processed_by',
    'processed_at',
    'notes',
])]
class PayrollPeriod extends Model
{
    /** @use HasFactory<PayrollPeriodFactory> */
    use HasFactory;

    public const STATUSES = [
        'draft' => 'Draft',
        'processed' => 'Diproses',
        'paid' => 'Dibayar',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'pay_date' => 'date',
            'processed_at' => 'datetime',
            'total_gross' => 'integer',
            'total_deductions' => 'integer',
            'total_net' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
