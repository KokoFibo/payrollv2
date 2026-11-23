<?php

namespace App\Models;

use Database\Factories\AdminVisibilitySettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['admin_salary_limit'])]
class AdminVisibilitySetting extends Model
{
    /** @use HasFactory<AdminVisibilitySettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'admin_salary_limit' => 'integer',
        ];
    }
}
