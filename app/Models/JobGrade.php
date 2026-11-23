<?php

namespace App\Models;

use Database\Factories\JobGradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['grade', 'grade_name'])]
class JobGrade extends Model
{
    /** @use HasFactory<JobGradeFactory> */
    use HasFactory;

    public function karyawans(): HasMany
    {
        return $this->hasMany(Karyawan::class);
    }
}
