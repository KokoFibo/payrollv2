<?php

namespace Database\Seeders;

use App\Models\JobGrade;
use Illuminate\Database\Seeder;

class JobGradeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            1 => ['P1', 'Non Staff'], 2 => ['P2', 'Non Staff'],
            3 => ['P3', 'Junior Staff'], 4 => ['P4', 'Staff'],
            5 => ['P5', 'Senior Staff'], 6 => ['P6', 'Junior Staff'],
            7 => ['P7', 'Officer'], 8 => ['P8', 'Senior Officer'],
            9 => ['M1', 'Junior Supervisor'], 10 => ['M2', 'Supervisor'],
            11 => ['M3', 'Senior Supervisor'], 12 => ['M4', 'Junior Manager'],
            13 => ['M5', 'Manager'], 14 => ['M6', 'Senior Manager'],
            15 => ['M7', 'Director'], 16 => ['M8', 'Senior Director'],
            17 => ['M9', 'Vice President'], 18 => ['M10', 'Chairman'],
        ] as $id => [$grade, $gradeName]) {
            JobGrade::query()->updateOrCreate(
                ['id' => $id],
                ['grade' => $grade, 'grade_name' => $gradeName],
            );
        }
    }
}
