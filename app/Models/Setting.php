<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['group', 'key', 'value', 'type', 'label'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    /**
     * @return array<string, string|null>
     */
    public static function values(string $group): array
    {
        return self::query()
            ->where('group', $group)
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function putMany(string $group, array $values): void
    {
        foreach ($values as $key => $value) {
            self::query()->updateOrCreate(
                ['key' => $key],
                [
                    'group' => $group,
                    'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                ],
            );
        }
    }
}
