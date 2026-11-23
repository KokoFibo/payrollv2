<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use Database\Seeders\Traits\SeedsNamedRecords;
use Illuminate\Database\Seeder;

class JabatanSeeder extends Seeder
{
    use SeedsNamedRecords;

    public function run(): void
    {
        $this->seedNamedRecords(Jabatan::class, [
            1 => 'Admin', 2 => 'Asisten Direktur', 3 => 'Assistant Line Supervisor',
            4 => 'Deputy Manager', 5 => 'Asisten Pengawas', 6 => 'Secretary',
            7 => 'Design Grafis', 8 => 'Director', 9 => 'Line Supervisor',
            10 => 'Manager', 11 => 'Supervisor', 12 => 'President Director',
            13 => 'Senior Staff', 14 => 'General Worker', 15 => 'Section Chief',
            16 => 'Senior Vice-President', 17 => 'Security', 18 => 'Koki',
            19 => 'Office Cook', 20 => 'Factory Cook', 21 => 'QC Aging',
            22 => 'Driver', 23 => 'Translator', 24 => 'Senior Specialist', 100 => null,
        ]);
    }
}
