<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum PayrollMethod: string
{
    case Hourly = 'hourly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::Monthly => 'Monthly',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $method): array => [
                'value' => $method->value,
                'label' => $method->label(),
            ])
            ->all();
    }

    public static function fromLegacy(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return match (Str::lower(trim($value))) {
            'hourly', 'per hour', 'perjam', 'per jam' => self::Hourly,
            'monthly', 'per month', 'perbulan', 'per bulan', 'bulanan' => self::Monthly,
            default => null,
        };
    }
}
