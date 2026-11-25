<?php

namespace App\Models;

use Database\Factories\NationalHolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'starts_on',
    'ends_on',
    'total_days',
])]
class NationalHoliday extends Model
{
    /** @use HasFactory<NationalHolidayFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'total_days' => 'integer',
        ];
    }
}
