<?php

namespace App\Support;

use App\Models\AdminVisibilitySetting;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AdminSalaryVisibility
{
    public const CACHE_KEY = 'admin_salary_visibility_limit';

    public const PAYROLL_FIELDS = [
        'metode_penggajian',
        'gaji_pokok',
        'gaji_tetap',
        'gaji_overtime',
        'gaji_shift_malam_satpam',
        'bonus',
        'tunjangan_jabatan',
        'tunjangan_bahasa',
        'tunjangan_skill',
        'tunjangan_lembur_sabtu',
        'tunjangan_lama_kerja',
        'tunjangan_housing',
        'iuran_air',
        'denda',
        'iuran_locker',
        'gaji_bpjs',
        'potongan_jht',
        'potongan_jp',
        'potongan_jkk',
        'potongan_jkm',
        'potongan_kesehatan',
        'no_npwp',
        'ptkp',
        'tanggungan',
    ];

    public function limit(): int
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(10),
            fn (): int => (int) (
                AdminVisibilitySetting::query()->value('admin_salary_limit')
                ?? 4_500_000
            ),
        );
    }

    public function updateLimit(int $limit): void
    {
        AdminVisibilitySetting::query()->updateOrCreate(
            ['id' => 1],
            ['admin_salary_limit' => $limit],
        );

        Cache::forget(self::CACHE_KEY);
    }

    public function shouldMaskSalary(Karyawan $karyawan, User $user): bool
    {
        return (int) $user->role === 5
            && (int) ($karyawan->gaji_pokok ?? 0) > $this->limit();
    }
}
