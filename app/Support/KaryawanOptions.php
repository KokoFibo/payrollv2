<?php

namespace App\Support;

final class KaryawanOptions
{
    public const AGAMA = [
        'Islam',
        'Kristen',
        'Hindu',
        'Budha',
        'Katolik',
        'Konghucu',
    ];

    public const PENDIDIKAN = [
        'Tidak Bersekolah',
        'SD',
        'SMP',
        'SMA/SMK',
        'D1',
        'D2',
        'D3',
        'D4',
        'S1',
        'S2',
        'S3',
    ];

    public const PTKP = [
        'TK0' => 'TK/0',
        'TK1' => 'TK/1',
        'TK2' => 'TK/2',
        'TK3' => 'TK/3',
        'K0' => 'K/0',
        'K1' => 'K/1',
        'K2' => 'K/2',
        'K3' => 'K/3',
    ];

    public const BANKS = [
        'BRI',
        'BCA',
        'Mandiri',
        'BNI',
        'Permata',
    ];

    /**
     * @param  array<int|string, string>  $values
     * @return list<array{value: int|string, label: string}>
     */
    public static function options(array $values): array
    {
        return collect($values)
            ->map(fn (string $label, int|string $value): array => [
                'value' => is_int($value) ? $label : $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }
}
