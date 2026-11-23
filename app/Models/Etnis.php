<?php

namespace App\Models;

use Database\Factories\EtnisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Etnis extends Model
{
    /** @use HasFactory<EtnisFactory> */
    use HasFactory;

    protected $table = 'etnis';

    public function karyawans(): HasMany
    {
        return $this->hasMany(Karyawan::class);
    }
}
