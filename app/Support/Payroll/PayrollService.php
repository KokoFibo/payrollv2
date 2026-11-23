<?php

namespace App\Support\Payroll;

use App\Enums\KaryawanStatus;
use App\Models\Karyawan;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    /**
     * @param  array{name: string, start_date: string, end_date: string, pay_date: string, notes?: string|null}  $data
     */
    public function createPeriod(array $data, User $actor): PayrollPeriod
    {
        $overlapExists = PayrollPeriod::query()
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'start_date' => 'Rentang periode payroll bertabrakan dengan periode lain.',
            ]);
        }

        $activeEmployees = Karyawan::query()
            ->whereIn('status_karyawan', KaryawanStatus::valuesForFilter('active'))
            ->count();

        if ($activeEmployees === 0) {
            throw ValidationException::withMessages([
                'name' => 'Belum ada karyawan aktif. Lengkapi atau import data karyawan terlebih dahulu.',
            ]);
        }

        return DB::transaction(function () use ($data): PayrollPeriod {
            $period = PayrollPeriod::query()->create([
                ...$data,
                'status' => 'draft',
                'processed_by' => null,
                'processed_at' => null,
            ]);

            $this->generateItems($period);

            return $period->refresh()->load('items.karyawan');
        });
    }

    public function markProcessed(PayrollPeriod $period, User $actor): PayrollPeriod
    {
        if ($period->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya periode draft yang dapat diproses.',
            ]);
        }

        $period->update([
            'status' => 'processed',
            'processed_by' => $actor->id,
            'processed_at' => now(),
        ]);

        return $period->refresh();
    }

    public function markPaid(PayrollPeriod $period): PayrollPeriod
    {
        if ($period->status !== 'processed') {
            throw ValidationException::withMessages([
                'status' => 'Periode harus diproses sebelum ditandai dibayar.',
            ]);
        }

        $period->update(['status' => 'paid']);

        return $period->refresh();
    }

    private function generateItems(PayrollPeriod $period): void
    {
        $totalGross = 0;
        $totalDeductions = 0;
        $totalNet = 0;

        Karyawan::query()
            ->whereIn('status_karyawan', KaryawanStatus::valuesForFilter('active'))
            ->orderBy('nip')
            ->chunkById(500, function ($employees) use (
                $period,
                &$totalGross,
                &$totalDeductions,
                &$totalNet,
            ): void {
                $items = $employees->map(function (Karyawan $karyawan) use (
                    $period,
                    &$totalGross,
                    &$totalDeductions,
                    &$totalNet,
                ): array {
                    $item = $this->calculateItem($karyawan);
                    $totalGross += $item['gross_salary'];
                    $totalDeductions += $item['total_deductions'];
                    $totalNet += $item['net_salary'];

                    return [
                        'payroll_period_id' => $period->id,
                        'karyawan_id' => $karyawan->id,
                        ...$item,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                });

                PayrollItem::query()->insert($items->all());
            });

        $period->update([
            'total_gross' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net' => $totalNet,
        ]);
    }

    /**
     * @return array{
     *     base_salary: int,
     *     fixed_salary: int,
     *     overtime_salary: int,
     *     night_shift_salary: int,
     *     allowances: int,
     *     bonus: int,
     *     gross_salary: int,
     *     bpjs_deduction: int,
     *     water_fee: int,
     *     locker_fee: int,
     *     penalty: int,
     *     other_deduction: int,
     *     total_deductions: int,
     *     net_salary: int
     * }
     */
    private function calculateItem(Karyawan $karyawan): array
    {
        $baseSalary = (int) ($karyawan->gaji_pokok ?? 0);
        $fixedSalary = (int) ($karyawan->gaji_tetap ?? 0);
        $overtimeSalary = (int) ($karyawan->gaji_overtime ?? 0);
        $nightShiftSalary = (int) ($karyawan->gaji_shift_malam_satpam ?? 0);
        $allowances = (int) (
            ($karyawan->tunjangan_jabatan ?? 0)
            + ($karyawan->tunjangan_bahasa ?? 0)
            + ($karyawan->tunjangan_skill ?? 0)
            + ($karyawan->tunjangan_lembur_sabtu ?? 0)
            + ($karyawan->tunjangan_lama_kerja ?? 0)
            + ($karyawan->tunjangan_housing ?? 0)
        );
        $bonus = (int) ($karyawan->bonus ?? 0);
        $grossSalary = $baseSalary + $fixedSalary + $overtimeSalary + $nightShiftSalary + $allowances + $bonus;
        $bpjsDeduction = (int) ($karyawan->gaji_bpjs ?? 0);
        $waterFee = (int) ($karyawan->iuran_air ?? 0);
        $lockerFee = (int) ($karyawan->iuran_locker ?? 0);
        $penalty = (int) ($karyawan->denda ?? 0);
        $otherDeduction = 0;
        $totalDeductions = $bpjsDeduction + $waterFee + $lockerFee + $penalty + $otherDeduction;

        return [
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
            'net_salary' => max(0, $grossSalary - $totalDeductions),
        ];
    }
}
