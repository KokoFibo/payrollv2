<?php

namespace App\Enums;

enum KaryawanStatus: string
{
    case Pkwt = 'PKWT';
    case Pkwtt = 'PKWTT';
    case Dirumahkan = 'Dirumahkan';
    case Resigned = 'Resigned';
    case Blacklist = 'Blacklist';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * @return array<string, string>
     */
    public static function filterOptions(): array
    {
        return [
            'active' => 'Aktif',
            self::Dirumahkan->value => self::Dirumahkan->label(),
            self::Resigned->value => self::Resigned->label(),
            self::Blacklist->value => self::Blacklist->label(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function valuesForFilter(string $filter): array
    {
        return match ($filter) {
            'active' => [self::Pkwt->value, self::Pkwtt->value],
            self::Dirumahkan->value => [self::Dirumahkan->value],
            self::Resigned->value => [self::Resigned->value],
            self::Blacklist->value => [self::Blacklist->value],
            default => [],
        };
    }

    public function displayLabel(): string
    {
        return match ($this) {
            self::Pkwt, self::Pkwtt => 'Aktif',
            default => $this->label(),
        };
    }

    public function usesActiveDot(): bool
    {
        return match ($this) {
            self::Pkwt, self::Pkwtt, self::Dirumahkan => true,
            default => false,
        };
    }
}
