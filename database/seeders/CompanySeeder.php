<?php

namespace Database\Seeders;

use App\Models\Company;
use Database\Seeders\Traits\SeedsNamedRecords;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    use SeedsNamedRecords;

    public function run(): void
    {
        $this->seedNamedRecords(Company::class, [
            1 => 'ASB', 2 => 'DPA', 3 => 'YCME', 4 => 'YEV', 5 => 'YIG',
            6 => 'YSM', 7 => 'YAM', 8 => 'GAMA', 9 => 'WAS', 100 => null,
            101 => 'STI', 102 => 'TDB', 103 => 'YNE', 104 => 'ASKARA',
            105 => 'GYOKAI', 106 => 'TWI',
        ]);
    }
}
