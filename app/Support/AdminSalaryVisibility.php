<?php

namespace App\Support;

use App\Models\AdminVisibilitySetting;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AdminSalaryVisibility
{
    public const CACHE_KEY = 'admin_salary_visibility_limit';

    public const RESTRICTED_PAYROLL_FIELDS = [
        'gaji_pokok',
        'gaji_tetap',
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
