<?php

namespace App\Models;

use Database\Factories\AttendanceImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'imported_by',
    'original_filename',
    'file_size',
    'attendance_date',
    'source_employee_count',
    'processed_employee_count',
    'skipped_without_punch_count',
    'unmatched_employee_count',
    'raw_punch_count',
    'duplicate_punch_count',
    'missing_punch_count',
    'late_count',
    'early_leave_count',
    'unmatched_user_ids',
    'warnings',
])]
class AttendanceImport extends Model
{
    /** @use HasFactory<AttendanceImportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'file_size' => 'integer',
            'source_employee_count' => 'integer',
            'processed_employee_count' => 'integer',
            'skipped_without_punch_count' => 'integer',
            'unmatched_employee_count' => 'integer',
            'raw_punch_count' => 'integer',
            'duplicate_punch_count' => 'integer',
            'missing_punch_count' => 'integer',
            'late_count' => 'integer',
            'early_leave_count' => 'integer',
            'unmatched_user_ids' => 'array',
            'warnings' => 'array',
        ];
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
