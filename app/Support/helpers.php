<?php

use App\Enums\OutsourceType;
use Illuminate\Support\Facades\DB;

if (! function_exists('karyawan_next_nip')) {
    function karyawan_next_nip(): int
    {
        $latestNumericNip = (int) DB::table('karyawans')
            ->selectRaw('MAX(CAST(nip AS UNSIGNED)) AS max_nip')
            ->value('max_nip');

        return $latestNumericNip + 1;
    }
}

if (! function_exists('karyawan_money_to_int')) {
    function karyawan_money_to_int(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9\-]/', '', (string) $value);

        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        return (int) $normalized;
    }
}

if (! function_exists('karyawan_outsource')) {
    function karyawan_outsource(): string
    {
        return OutsourceType::Os->value;
    }
}

if (! function_exists('karyawan_blind_index')) {
    function karyawan_blind_index(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::lower(trim((string) $value));

        return $normalized === '' ? null : hash('sha256', $normalized);
    }
}
