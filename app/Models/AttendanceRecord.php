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
    'check_in',
    'check_out',
    'overtime_minutes',
    'notes',
    'recorded_by',
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
            'overtime_minutes' => 'integer',
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
}
