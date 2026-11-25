<?php

namespace App\Models;

use Database\Factories\SpecialWorkScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'company_id',
    'directorate_id',
    'department_id',
    'starts_on',
    'ends_on',
    'schedule_profile',
    'day_type',
    'shift',
    'check_in',
    'check_out',
    'check_out_day_offset',
    'standard_work_hours',
    'expected_punch_count',
    'notes',
])]
class SpecialWorkSchedule extends Model
{
    /** @use HasFactory<SpecialWorkScheduleFactory> */
    use HasFactory;

    public const PROFILES = [
        'general' => 'Produksi / Umum',
        'security' => 'Security',
        'qc_aging' => 'QC Aging',
    ];

    public const DAY_TYPES = [
        'sunday_thursday' => 'Minggu - Kamis',
        'friday' => 'Jumat',
        'saturday' => 'Sabtu',
    ];

    public const SHIFTS = [
        'day' => 'Shift pagi',
        'night' => 'Shift malam',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'check_out_day_offset' => 'integer',
            'standard_work_hours' => 'decimal:1',
            'expected_punch_count' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
