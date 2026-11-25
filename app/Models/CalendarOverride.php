<?php

namespace App\Models;

use Database\Factories\CalendarOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'directorate_id',
    'department_id',
    'calendar_date',
    'name',
    'is_holiday_pay',
    'schedule_day_type',
    'notes',
])]
class CalendarOverride extends Model
{
    /** @use HasFactory<CalendarOverrideFactory> */
    use HasFactory;

    public const DAY_TYPES = SpecialWorkSchedule::DAY_TYPES;

    protected function casts(): array
    {
        return [
            'calendar_date' => 'date',
            'is_holiday_pay' => 'boolean',
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

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
