<?php

namespace App\Models;

use Database\Factories\DirectorateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Directorate extends Model
{
    /** @use HasFactory<DirectorateFactory> */
    use HasFactory;

    public function karyawans(): HasMany
    {
        return $this->hasMany(Karyawan::class);
    }
}
