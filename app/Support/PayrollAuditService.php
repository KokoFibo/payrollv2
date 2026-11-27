<?php

namespace App\Support;

use App\Models\Karyawan;
use App\Models\PayrollAuditLog;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class PayrollAuditService
{
    public const EVENT_LABELS = [
        'employee_created' => 'Karyawan dibuat',
        'employee_updated' => 'Data gaji karyawan diubah',
        'employee_deleted' => 'Karyawan dihapus',
        'employee_migrated' => 'Data karyawan dimigrasikan',
        'attendance_created' => 'Attendance dibuat',
        'attendance_updated' => 'Attendance diubah',
        'attendance_deleted' => 'Attendance dihapus',
        'attendance_imported' => 'Import attendance',
        'payroll_period_created' => 'Periode payroll dibuat',
        'payroll_period_updated' => 'Periode payroll diubah',
        'payroll_period_deleted' => 'Periode payroll dihapus',
        'payroll_item_created' => 'Item payroll dibuat',
        'payroll_item_updated' => 'Item payroll diubah',
        'payroll_item_deleted' => 'Item payroll dihapus',
        'payroll_setting_created' => 'Konfigurasi payroll dibuat',
        'payroll_setting_updated' => 'Konfigurasi payroll diubah',
        'payroll_setting_deleted' => 'Konfigurasi payroll dihapus',
    ];

    public const EMPLOYEE_FIELDS = [
        'status_karyawan' => 'Status karyawan',
        'tanggal_bergabung' => 'Tanggal bergabung',
        'tanggal_resigned' => 'Tanggal resigned',
        'tanggal_blacklist' => 'Tanggal blacklist',
        'nama_bank' => 'Nama bank',
        'nomor_rekening' => 'Nomor rekening',
        'metode_penggajian' => 'Metode penggajian',
        'gaji_pokok' => 'Gaji pokok',
        'gaji_tetap' => 'Gaji tetap',
        'gaji_overtime' => 'Gaji overtime',
        'gaji_shift_malam_satpam' => 'Gaji shift malam',
        'bonus' => 'Bonus',
        'tunjangan_jabatan' => 'Tunjangan jabatan',
        'tunjangan_bahasa' => 'Tunjangan bahasa',
        'tunjangan_skill' => 'Tunjangan skill',
        'tunjangan_lembur_sabtu' => 'Tunjangan lembur Sabtu',
        'tunjangan_lama_kerja' => 'Tunjangan lama kerja',
        'tunjangan_housing' => 'Tunjangan housing',
        'iuran_air' => 'Iuran air',
        'denda' => 'Denda',
        'iuran_locker' => 'Iuran locker',
        'gaji_bpjs' => 'Potongan BPJS',
        'potongan_jht' => 'Potongan JHT',
        'potongan_jp' => 'Potongan JP',
        'potongan_jkk' => 'Potongan JKK',
        'potongan_jkm' => 'Potongan JKM',
        'potongan_kesehatan' => 'Potongan kesehatan',
        'no_npwp' => 'NPWP',
        'ptkp' => 'PTKP',
        'tanggungan' => 'Jumlah tanggungan',
    ];

    public const ATTENDANCE_FIELDS = [
        'status' => 'Status attendance',
        'shift' => 'Shift',
        'schedule_profile' => 'Profile jadwal',
        'first_in' => 'Masuk 1',
        'first_out' => 'Keluar 1',
        'second_in' => 'Masuk 2',
        'second_out' => 'Keluar 2',
        'overtime_in' => 'Masuk overtime',
        'overtime_out' => 'Keluar overtime',
        'first_out_day_offset' => 'Offset keluar 1',
        'second_in_day_offset' => 'Offset masuk 2',
        'second_out_day_offset' => 'Offset keluar 2',
        'overtime_in_day_offset' => 'Offset masuk overtime',
        'overtime_out_day_offset' => 'Offset keluar overtime',
        'worked_hours' => 'Jam kerja',
        'overtime_hours' => 'Jam overtime',
        'holiday_worked_hours' => 'Jam kerja libur',
        'holiday_overtime_hours' => 'Overtime libur',
        'night_shift_allowance' => 'Allowance shift malam',
        'late_minutes' => 'Menit terlambat',
        'late_penalty_hours' => 'Denda keterlambatan',
        'early_leave_minutes' => 'Menit pulang awal',
        'has_missing_punch' => 'Punch tidak lengkap',
        'requires_review' => 'Perlu review',
        'review_resolved_at' => 'Waktu review selesai',
        'review_resolved_by' => 'Review oleh',
        'review_resolution_note' => 'Catatan review',
    ];

    public const PAYROLL_PERIOD_FIELDS = [
        'name' => 'Nama periode',
        'start_date' => 'Tanggal mulai',
        'end_date' => 'Tanggal selesai',
        'pay_date' => 'Tanggal bayar',
        'status' => 'Status payroll',
        'total_gross' => 'Total gross',
        'total_deductions' => 'Total potongan',
        'total_net' => 'Total net',
        'processed_by' => 'Diproses oleh',
        'processed_at' => 'Waktu diproses',
        'notes' => 'Catatan',
    ];

    public const PAYROLL_ITEM_FIELDS = [
        'base_salary' => 'Gaji pokok',
        'fixed_salary' => 'Gaji tetap',
        'overtime_salary' => 'Gaji overtime',
        'night_shift_salary' => 'Gaji shift malam',
        'allowances' => 'Tunjangan',
        'bonus' => 'Bonus',
        'gross_salary' => 'Gross salary',
        'bpjs_deduction' => 'Potongan BPJS',
        'water_fee' => 'Iuran air',
        'locker_fee' => 'Iuran locker',
        'penalty' => 'Denda',
        'other_deduction' => 'Potongan lain',
        'total_deductions' => 'Total potongan',
        'net_salary' => 'Net salary',
    ];

    public const VISIBILITY_FIELDS = [
        'admin_salary_limit' => 'Batas sensor gaji Admin',
        'attendance_tolerance_minutes' => 'Toleransi attendance',
    ];

    /**
     * @param  list<array{field: string, label: string, old: mixed, new: mixed}>  $changes
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $eventType,
        string $subjectType,
        int|string|null $subjectId,
        string $subjectLabel,
        array $changes = [],
        array $metadata = [],
        ?Karyawan $employee = null,
        ?User $actor = null,
    ): ?PayrollAuditLog {
        $actor ??= Auth::user();

        if (! $actor instanceof User) {
            return null;
        }

        return PayrollAuditLog::query()->create([
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role,
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => $subjectLabel,
            'karyawan_id' => $employee?->id,
            'employee_nip' => $employee?->nip,
            'employee_name' => $employee?->nama,
            'changes' => array_values($changes),
            'metadata' => array_filter([
                ...$metadata,
                'route' => request()->route()?->getName(),
                'method' => request()->method(),
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            'ip_address' => request()->ip(),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  list<array{
     *     event_type: string,
     *     subject_type: string,
     *     subject_id: int|string|null,
     *     subject_label: string,
     *     changes?: array<int, array<string, mixed>>,
     *     metadata?: array<string, mixed>,
     *     employee?: Karyawan|null
     * }>  $events
     */
    public function recordMany(array $events, ?User $actor = null): int
    {
        $actor ??= Auth::user();

        if (! $actor instanceof User || $events === []) {
            return 0;
        }

        $occurredAt = now();
        $route = request()->route()?->getName();
        $method = request()->method();
        $ipAddress = request()->ip();
        $rows = [];

        foreach ($events as $event) {
            $employee = $event['employee'] ?? null;
            $metadata = array_filter([
                ...($event['metadata'] ?? []),
                'route' => $route,
                'method' => $method,
            ], fn (mixed $value): bool => $value !== null && $value !== '');

            $rows[] = [
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'actor_role' => $actor->role,
                'event_type' => $event['event_type'],
                'subject_type' => $event['subject_type'],
                'subject_id' => $event['subject_id'],
                'subject_label' => $event['subject_label'],
                'karyawan_id' => $employee?->id,
                'employee_nip' => $employee?->nip,
                'employee_name' => $employee?->nama,
                'changes' => json_encode(array_values($event['changes'] ?? []), JSON_THROW_ON_ERROR),
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'ip_address' => $ipAddress,
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('payroll_audit_logs')->insert($chunk);
        }

        return count($rows);
    }

    /**
     * @param  array<string, string>  $fieldLabels
     * @return list<array{field: string, label: string, old: mixed, new: mixed}>
     */
    public function changesFor(
        Model $model,
        array $fieldLabels,
        string $mode,
    ): array {
        $changedAttributes = $model->getChanges();
        $changes = [];

        foreach ($fieldLabels as $field => $label) {
            if ($mode === 'updated' && ! array_key_exists($field, $changedAttributes)) {
                continue;
            }

            $oldValue = $mode === 'created' ? null : $model->getOriginal($field);
            $newValue = $mode === 'deleted' ? null : $model->getAttribute($field);
            $oldValue = $this->auditValue($field, $oldValue);
            $newValue = $this->auditValue($field, $newValue);

            if ($oldValue === $newValue) {
                continue;
            }

            $changes[] = compact('field', 'label', 'oldValue', 'newValue');
        }

        return array_map(
            fn (array $change): array => [
                'field' => $change['field'],
                'label' => $change['label'],
                'old' => $change['oldValue'],
                'new' => $change['newValue'],
            ],
            $changes,
        );
    }

    private function auditValue(string $field, mixed $value): mixed
    {
        if (in_array($field, ['no_npwp', 'nomor_rekening'], true)) {
            return $value === null || $value === '' ? 'Kosong' : 'Terisi';
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_float($value)) {
            return round($value, 2);
        }

        return $value;
    }
}
