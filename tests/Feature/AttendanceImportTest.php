<?php

use App\Models\AttendanceImport;
use App\Models\AttendanceRecord;
use App\Models\CalendarOverride;
use App\Models\Company;
use App\Models\Department;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\NationalHoliday;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

test('admin imports attendance with employee matching and schedule comparison', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $securityDepartment = Department::factory()->create(['name' => 'Security']);
    $quality = Department::factory()->create(['name' => 'Quality']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $security = Jabatan::factory()->create(['name' => 'Security']);
    $qcAging = Jabatan::factory()->create(['name' => 'QC Aging']);

    $employees = [
        1001 => Karyawan::factory()->create([
            'nip' => 1001,
            'nama' => 'Database Employee',
            'department_id' => $production->id,
            'jabatan_id' => $generalWorker->id,
            'metode_penggajian' => 'Bulanan',
        ]),
        1002 => Karyawan::factory()->create([
            'nip' => 1002,
            'nama' => 'Hourly Late Employee',
            'department_id' => $production->id,
            'jabatan_id' => $generalWorker->id,
            'metode_penggajian' => 'Per Jam',
        ]),
        1003 => Karyawan::factory()->create([
            'nip' => 1003,
            'nama' => 'No Punch Employee',
            'department_id' => $production->id,
            'jabatan_id' => $generalWorker->id,
        ]),
        1004 => Karyawan::factory()->create([
            'nip' => 1004,
            'nama' => 'Duplicate Punch Employee',
            'department_id' => $production->id,
            'jabatan_id' => $generalWorker->id,
        ]),
        1005 => Karyawan::factory()->create([
            'nip' => 1005,
            'nama' => 'Missing Punch Employee',
            'department_id' => $production->id,
            'jabatan_id' => $generalWorker->id,
        ]),
        2001 => Karyawan::factory()->create([
            'nip' => 2001,
            'nama' => 'Night Security',
            'department_id' => $securityDepartment->id,
            'jabatan_id' => $security->id,
        ]),
        3001 => Karyawan::factory()->create([
            'nip' => 3001,
            'nama' => 'QC Aging Employee',
            'department_id' => $quality->id,
            'jabatan_id' => $qcAging->id,
        ]),
    ];

    $file = UploadedFile::fake()->createWithContent(
        '19 november.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [1001, 'Excel Employee Name', 'PD', '7:55 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '5:05 PM'],
            [1002, 'Excel Late Employee', 'PD', '8:04 AM'],
            [null, null, null, '11:35 AM'],
            [null, null, null, '12:25 PM'],
            [null, null, null, '5:00 PM'],
            [1003, 'No Punch Employee', 'PD', null],
            [1004, 'Duplicate Punch Employee', 'PD', '7:58 AM'],
            [null, null, null, '7:58 AM'],
            [null, null, null, '7:58 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '5:00 PM'],
            [1005, 'Missing Punch Employee', 'PD', '7:55 AM'],
            [null, null, null, '12:00 PM'],
            [null, null, null, '5:00 PM'],
            [2001, 'Night Security', 'Security', '7:45 PM'],
            [null, null, null, '7:05 AM+'],
            [3001, 'QC Aging Employee', 'Quality', '8:00 AM'],
            [null, null, null, '11:00 AM'],
            [null, null, null, '12:00 PM'],
            [null, null, null, '5:00 PM'],
            [9999, 'Unknown Employee', 'PD', '7:45 AM'],
            [null, null, null, '12:00 PM'],
            [null, null, null, '1:00 PM'],
            [null, null, null, '5:00 PM'],
        ]),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $import = AttendanceImport::query()->firstOrFail();

    expect($import)
        ->attendance_date->toDateString()->toBe('2026-11-19')
        ->and($import->processed_employee_count)->toBe(6)
        ->and($import->skipped_without_punch_count)->toBe(1)
        ->and($import->unmatched_employee_count)->toBe(1)
        ->and($import->raw_punch_count)->toBe(23)
        ->and($import->duplicate_punch_count)->toBe(2)
        ->and($import->missing_punch_count)->toBe(1)
        ->and($import->late_count)->toBe(1)
        ->and($import->unmatched_user_ids)->toBe(['9999']);

    $records = AttendanceRecord::query()
        ->where('attendance_import_id', $import->id)
        ->get()
        ->keyBy('karyawan_id');

    expect($records)->toHaveCount(6)
        ->and($records->has($employees[1003]->id))->toBeFalse();

    expect($records[$employees[1001]->id])
        ->status->toBe('present')
        ->shift->toBe('day')
        ->schedule_profile->toBe('general')
        ->first_in->toBe('07:55:00')
        ->first_out->toBe('11:30:00')
        ->second_in->toBe('12:30:00')
        ->second_out->toBe('17:05:00')
        ->worked_hours->toBe('8.0')
        ->raw_punch_count->toBe(4)
        ->effective_punch_count->toBe(4)
        ->late_minutes->toBe(0)
        ->has_missing_punch->toBeFalse();

    expect($records[$employees[1002]->id])
        ->status->toBe('late')
        ->late_minutes->toBe(4)
        ->late_penalty_hours->toBe(1);

    expect($records[$employees[1004]->id])
        ->raw_punch_count->toBe(6)
        ->effective_punch_count->toBe(4)
        ->duplicate_punch_count->toBe(2)
        ->first_in->toBe('07:58:00')
        ->first_out->toBe('11:30:00')
        ->second_in->toBe('12:30:00')
        ->second_out->toBe('17:00:00')
        ->has_duplicate_punch->toBeTrue()
        ->requires_review->toBeFalse();

    expect($records[$employees[1005]->id])
        ->effective_punch_count->toBe(3)
        ->has_missing_punch->toBeTrue()
        ->early_leave_minutes->toBe(0);

    expect($records[$employees[2001]->id])
        ->schedule_profile->toBe('security')
        ->shift->toBe('night')
        ->effective_punch_count->toBe(2)
        ->has_missing_punch->toBeFalse()
        ->first_in->toBe('19:45:00')
        ->first_out->toBeNull()
        ->second_in->toBeNull()
        ->second_out->toBe('07:05:00')
        ->last_out_day_offset->toBe(1)
        ->worked_hours->toBe('8.0')
        ->requires_review->toBeFalse();

    expect($records[$employees[3001]->id])
        ->schedule_profile->toBe('qc_aging')
        ->requires_review->toBeFalse();

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', [
            'date' => '2026-11-19',
            'search' => 'Database Employee',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Index')
            ->where('records.data.0.employee.name', 'Database Employee')
            ->where('records.data.0.first_in', '07:55')
            ->where('records.data.0.clock_slots.1.time', '11:30')
            ->where('recentImports.0.filename', '19 november.xlsx')
            ->where('stats.incomplete', 1));
});

test('attendance import doubles regular and overtime hours on a national holiday', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 5001,
        'nama' => 'Holiday Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    NationalHoliday::factory()->create([
        'name' => 'Hari Libur Uji',
        'starts_on' => '2026-11-19',
        'ends_on' => '2026-11-19',
        'total_days' => 1,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'holiday.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Holiday Employee', 'Production', '8:00 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '6:30 PM'],
        ]),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->worked_hours->toBe('0.0')
        ->overtime_hours->toBe('0.0')
        ->holiday_worked_hours->toBe('16.0')
        ->holiday_overtime_hours->toBe('3.0')
        ->comparison_notes->toContain('dikali 2');
});

test('attendance import applies a company calendar override on a national holiday', function () {
    $admin = User::factory()->create(['role' => 5]);
    $company = Company::factory()->create(['name' => 'Company Hari Kerja']);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 5002,
        'nama' => 'Company Workday Employee',
        'company_id' => $company->id,
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    NationalHoliday::factory()->create([
        'name' => 'Libur Nasional Jumat',
        'starts_on' => '2026-11-20',
        'ends_on' => '2026-11-20',
    ]);
    $override = CalendarOverride::factory()->create([
        'company_id' => $company->id,
        'calendar_date' => '2026-11-20',
        'name' => 'Jumat tetap masuk',
        'is_holiday_pay' => false,
        'schedule_day_type' => 'friday',
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'company-workday.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '20'],
            [$employee->nip, 'Company Workday Employee', 'Production', '8:00 AM'],
            [null, null, null, '11:30 AM'],
            [null, null, null, '12:30 PM'],
            [null, null, null, '5:00 PM'],
        ], '2026-11-20'),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->is_holiday_pay->toBeFalse()
        ->calendar_override_id->toBe($override->id)
        ->worked_hours->toBe('7.5')
        ->holiday_worked_hours->toBe('0.0')
        ->comparison_notes->toContain('diperlakukan sebagai hari kerja');
});

test('attendance import maps a missing second checkout into overtime slots', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 2017,
        'nama' => 'Patmawati',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'patmawati.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Patmawati', 'Production', '7:41 AM'],
            [null, null, null, '12:05 PM'],
            [null, null, null, '12:54 PM'],
            [null, null, null, '5:49 PM'],
            [null, null, null, '8:06 PM'],
        ]),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->first_in->toBe('07:41:00')
        ->first_out->toBe('12:05:00')
        ->second_in->toBe('12:54:00')
        ->second_out->toBeNull()
        ->overtime_in->toBe('17:49:00')
        ->overtime_out->toBe('20:06:00')
        ->worked_hours->toBe('8.0')
        ->overtime_hours->toBe('2.0')
        ->requires_review->toBeFalse();
});

test('attendance import keeps a fourth punch inside fifty nine minutes as second checkout', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 7017,
        'nama' => 'Late Checkout Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'late-checkout.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Late Checkout Employee', 'Production', '7:20 AM'],
            [null, null, null, '12:05 PM'],
            [null, null, null, '12:31 PM'],
            [null, null, null, '5:50 PM'],
        ]),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->second_out->toBe('17:50:00')
        ->overtime_out->toBeNull()
        ->worked_hours->toBe('8.0')
        ->overtime_hours->toBe('0.5');
});

test('attendance import keeps a fourth punch after the overtime threshold as second checkout', function () {
    $admin = User::factory()->create(['role' => 5]);
    $production = Department::factory()->create(['name' => 'Production']);
    $generalWorker = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'nip' => 7018,
        'nama' => 'Long Work Employee',
        'department_id' => $production->id,
        'jabatan_id' => $generalWorker->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'long-work.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Long Work Employee', 'Production', '8:00 AM'],
            [null, null, null, '12:00 PM'],
            [null, null, null, '1:00 PM'],
            [null, null, null, '8:06 PM'],
        ]),
    );

    $this->actingAs($admin)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->where('karyawan_id', $employee->id)->firstOrFail();

    expect($record)
        ->second_out->toBe('20:06:00')
        ->overtime_out->toBeNull()
        ->worked_hours->toBe('8.0')
        ->overtime_hours->toBe('3.0');
});

test('attendance import rejects a multi-day workbook', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create(['nip' => 1001]);
    $file = UploadedFile::fake()->createWithContent(
        'multi-day.xlsx',
        attendanceImportWorkbook([
            ['User ID', 'Name', 'Department', '19'],
            [$employee->nip, 'Employee', 'Production', '8:00 AM'],
        ], '2026-11-19', '2026-11-20'),
    );

    $this->actingAs($admin)
        ->from(route('admin.attendance.index'))
        ->post(route('admin.attendance.import'), [
            'attendance_file' => $file,
        ])
        ->assertRedirect(route('admin.attendance.index'))
        ->assertSessionHasErrors('attendance_file');

    expect(AttendanceImport::query()->count())->toBe(0);
});

test('attendance import forbids users who cannot manage payroll', function () {
    $user = User::factory()->create(['role' => 4]);

    $this->actingAs($user)
        ->post(route('admin.attendance.import'), [
            'attendance_file' => UploadedFile::fake()->create('attendance.xlsx', 10),
        ])
        ->assertForbidden();
});

/**
 * @param  list<array{0: int|string|null, 1: string|null, 2: string|null, 3: string|null}>  $rows
 */
function attendanceImportWorkbook(
    array $rows,
    string $periodStart = '2026-11-19',
    ?string $periodEnd = null,
): string {
    $periodEnd ??= $periodStart;
    $directory = storage_path('framework/testing');
    File::ensureDirectoryExists($directory);
    $path = $directory.'/attendance-import-'.bin2hex(random_bytes(6)).'.xlsx';
    $archive = new ZipArchive;

    if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create attendance workbook fixture.');
    }

    try {
        $archive->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML);
        $archive->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
        $archive->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Attendance Logs" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML);
        $archive->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML);

        $sheetRows = [
            ['Attendance Logs', null, null, null],
            ["Period : {$periodStart} ~ {$periodEnd}", null, null, null],
        ];
        $sheetRows = [...$sheetRows, ...$rows];
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($sheetRows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $sheetXml .= '<row r="'.$rowNumber.'">';

            foreach (['A', 'B', 'C', 'D'] as $columnIndex => $column) {
                $value = $row[$columnIndex] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                $reference = $column.$rowNumber;

                if (is_int($value) || ctype_digit((string) $value)) {
                    $sheetXml .= '<c r="'.$reference.'"><v>'.htmlspecialchars(
                        (string) $value,
                        ENT_XML1 | ENT_QUOTES,
                        'UTF-8',
                    ).'</v></c>';

                    continue;
                }

                $sheetXml .= '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'
                    .htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    .'</t></is></c>';
            }

            $sheetXml .= '</row>';
        }

        $sheetXml .= '</sheetData></worksheet>';
        $archive->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    } finally {
        $archive->close();
    }

    $content = File::get($path);
    File::delete($path);

    return $content;
}
