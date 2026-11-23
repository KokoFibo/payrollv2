<?php

namespace Database\Seeders;

use App\Models\Department;
use Database\Seeders\Traits\SeedsNamedRecords;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    use SeedsNamedRecords;

    public function run(): void
    {
        $this->seedNamedRecords(Department::class, [
            1 => 'Region 1 BD', 2 => 'Engineering', 3 => 'Customs Affairs',
            4 => 'Finance Accounting', 5 => 'GA', 6 => 'Warehouse',
            7 => 'Human Capital', 8 => 'Legal & Compliance', 9 => 'Procurement',
            10 => 'Production', 11 => 'Quality', 12 => 'Chairman Office',
            100 => null, 101 => 'Industrial Property', 102 => 'Investment',
            103 => 'Corporate Media', 104 => 'Office Management', 105 => 'Region 2 BD',
            106 => 'Labor Dispatch', 107 => 'Tax Advisory', 108 => 'Canteen',
            109 => 'Dormitory', 110 => 'Maintenance', 111 => 'Logistic',
        ]);
    }
}
