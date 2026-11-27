<?php

namespace App\Models;

use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'karyawan_id',
    'attendance_date',
    'status',
    'first_in',
    'first_out',
    'first_out_day_offset',
    'second_in',
    'second_out',
    'overtime_in',
    'overtime_out',
    'second_in_day_offset',
    'second_out_day_offset',
    'overtime_in_day_offset',
    'overtime_out_day_offset',
    'last_out_day_offset',
    'worked_hours',
    'worked_days',
    'overtime_hours',
    'holiday_worked_hours',
    'holiday_overtime_hours',
    'night_shift_allowance',
    'notes',
    'recorded_by',
    'attendance_import_id',
    'calendar_override_id',
    'shift',
    'schedule_profile',
    'is_holiday_pay',
    'expected_check_in',
    'expected_check_out',
    'raw_punch_count',
    'effective_punch_count',
    'duplicate_punch_count',
    'ignored_punch_count',
    'late_minutes',
    'late_penalty_hours',
    'early_leave_minutes',
    'has_missing_punch',
    'has_duplicate_punch',
    'requires_review',
    'comparison_notes',
    'review_resolved_at',
    'review_resolved_by',
    'review_resolution_note',
])]
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    public const STATUSES = [
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'leave' => 'Cuti',
        'sick' => 'Sakit',
        'absent' => 'Tidak Hadir',
        'remote' => 'Remote',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'first_out_day_offset' => 'integer',
            'second_in_day_offset' => 'integer',
            'second_out_day_offset' => 'integer',
            'overtime_in_day_offset' => 'integer',
            'overtime_out_day_offset' => 'integer',
            'last_out_day_offset' => 'integer',
            'worked_hours' => 'decimal:1',
            'worked_days' => 'decimal:1',
            'overtime_hours' => 'decimal:1',
            'holiday_worked_hours' => 'decimal:1',
            'holiday_overtime_hours' => 'decimal:1',
            'night_shift_allowance' => 'decimal:1',
            'raw_punch_count' => 'integer',
            'effective_punch_count' => 'integer',
            'duplicate_punch_count' => 'integer',
            'ignored_punch_count' => 'integer',
            'late_minutes' => 'integer',
            'late_penalty_hours' => 'integer',
            'early_leave_minutes' => 'integer',
            'has_missing_punch' => 'boolean',
            'has_duplicate_punch' => 'boolean',
            'requires_review' => 'boolean',
            'is_holiday_pay' => 'boolean',
            'review_resolved_at' => 'datetime',
        ];
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reviewResolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'review_resolved_by');
    }

    public function attendanceImport(): BelongsTo
    {
        return $this->belongsTo(AttendanceImport::class);
    }

    public function calendarOverride(): BelongsTo
    {
        return $this->belongsTo(CalendarOverride::class);
    }
}
