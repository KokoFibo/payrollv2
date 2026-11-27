<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'actor_id',
    'actor_name',
    'actor_role',
    'event_type',
    'subject_type',
    'subject_id',
    'subject_label',
    'karyawan_id',
    'employee_nip',
    'employee_name',
    'changes',
    'metadata',
    'ip_address',
    'occurred_at',
])]
class PayrollAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'actor_role' => 'integer',
            'subject_id' => 'integer',
            'karyawan_id' => 'integer',
            'changes' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Payroll audit logs are immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Payroll audit logs are immutable.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
