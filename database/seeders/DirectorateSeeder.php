<?php

namespace Database\Seeders;

use App\Models\Directorate;
use Database\Seeders\Traits\SeedsNamedRecords;
use Illuminate\Database\Seeder;

class DirectorateSeeder extends Seeder
{
    use SeedsNamedRecords;

    public function run(): void
    {
        $this->seedNamedRecords(Directorate::class, [
            1 => 'Headquarter Strategic Center', 2 => 'Investment Center',
            3 => 'Finance Center', 4 => 'Procure Center',
            5 => 'Asset Management Services Center', 6 => 'Business Center',
            7 => 'YEV', 8 => '7th Factory', 9 => '3rd Factory', 10 => '2nd Factory',
            11 => 'SUNRA Factory', 12 => 'YIG', 13 => 'Integrated Business Services',
            100 => null, 101 => '4th Factory', 102 => '1st Factory', 103 => 'YNE',
            104 => 'STI', 105 => 'Yifang Academy', 106 => '5th Factory',
            107 => 'XIN XUN', 108 => 'Import & Export Center', 109 => 'BAI',
        ]);
    }
}
