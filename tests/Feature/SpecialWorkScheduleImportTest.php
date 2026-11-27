<?php

use App\Http\Controllers\Admin\AttendanceScheduleGuideController;
use App\Models\SpecialWorkSchedule;
use App\Models\User;
use App\Support\Attendance\SpecialWorkScheduleWorkbookReader;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can download the attendance schedule guide', function () {
    $admin = User::factory()->create(['role' => 5]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.schedule-guide.download'))
        ->assertOk()
        ->assertDownload('Jadwal Kerja Regular Yifang - Final + Jam Kerja Maks.xlsx');
});

test('schedule guide workbook contains rules and an import tab', function () {
    $archive = new ZipArchive;
    $archive->open(AttendanceScheduleGuideController::templatePath(), ZipArchive::RDONLY);

    try {
        $workbookXml = $archive->getFromName('xl/workbook.xml');
        $rulesXml = $archive->getFromName('xl/worksheets/sheet2.xml');
        $importXml = $archive->getFromName('xl/worksheets/sheet1.xml');
    } finally {
        $archive->close();
    }

    expect($workbookXml)
        ->toContain('name="Jadwal Kerja"')
        ->toContain('name="Ketentuan"')
        ->not->toContain('name="Jadwal Khusus"');
    expect($rulesXml)
        ->toContain('Security Sabtu malam')
        ->toContain('17:00-06:29 = 6 jam kerja + 11 jam lembur');
    expect($importXml)
        ->toContain('4. JADWAL PERUBAHAN / RAMADAN')
        ->toContain('Nama Periode')
        ->toContain('Tanggal Mulai')
        ->toContain('Akhir Shift');
});

test('attendance page renders schedule rules and stored special schedules', function () {
    $admin = User::factory()->create(['role' => 5]);
    SpecialWorkSchedule::factory()->create([
        'name' => 'Ramadan 2027',
        'starts_on' => '2027-02-10',
        'ends_on' => '2027-03-10',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.index', ['date' => '2027-02-10']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Index')
            ->has('specialWorkSchedules', 1)
            ->where('specialWorkSchedules.0.name', 'Ramadan 2027')
            ->has('scheduleGuide.profiles', 3)
            ->has('scheduleGuide.requirements', 7)
            ->where('scheduleGuide.profiles.0.key', 'general')
            ->has('scheduleGuide.profiles.0.days', 3)
            ->has('scheduleGuide.profiles.0.days.0.shifts', 2)
            ->where('scheduleGuide.profiles.0.days.0.shifts.0.time', '08:00 - 17:00')
            ->where('scheduleGuide.profiles.0.days.0.shifts.0.standard_hours', 8)
            ->where('scheduleGuide.downloadAvailable', true));
});

test('developer imports new schedules and updates matching schedules without deleting others', function () {
    $developer = User::factory()->create(['role' => 8]);
    $existingSchedule = SpecialWorkSchedule::factory()->create([
        'name' => 'Ramadan 2027',
        'starts_on' => '2027-02-10',
        'ends_on' => '2027-03-10',
        'schedule_profile' => 'general',
        'day_type' => 'sunday_thursday',
        'shift' => 'day',
        'check_in' => '10:00:00',
        'check_out' => '17:00:00',
        'standard_work_hours' => 7,
    ]);
    $unchangedSchedule = SpecialWorkSchedule::factory()->create([
        'name' => 'Jadwal Lama',
        'starts_on' => '2026-12-01',
        'ends_on' => '2026-12-10',
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'jadwal-ramadan.xlsx',
        specialScheduleWorkbook([
            [
                'Ramadan 2027',
                excelSerial('2027-02-10'),
                excelSerial('2027-03-10'),
                'Produksi / Umum',
                'Minggu - Kamis',
                'Shift pagi',
                0.375,
                0.625,
                0,
                7.5,
                4,
                '',
                'Jadwal Ramadan diperbarui',
            ],
            [
                'Ramadan 2027',
                '2027-02-10',
                '2027-03-10',
                'Security',
                'Minggu - Kamis',
                'Shift malam',
                '20:00',
                '08:00',
                1,
                8,
                2,
                '',
                'Security Ramadan',
            ],
        ]),
    );
    $parsedRows = app(SpecialWorkScheduleWorkbookReader::class)
        ->read($file->getRealPath());

    expect($parsedRows[1]['check_out_day_offset'])->toBe('1');

    $this->actingAs($developer)
        ->post(route('admin.developer-settings.special-work-schedules.import'), [
            'schedule_file' => $file,
        ])
        ->assertRedirect(route('admin.developer-settings.index', ['section' => 'work-schedules']))
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'success'
            && str_contains($toast['message'], '1 jadwal baru ditambahkan')
            && str_contains($toast['message'], '1 jadwal diperbarui')
            && str_contains($toast['message'], 'Jadwal lain tetap tersimpan'));

    expect($existingSchedule->refresh())
        ->check_in->toBe('09:00:00')
        ->check_out->toBe('15:00:00')
        ->standard_work_hours->toBe('7.5');
    $this->assertModelExists($unchangedSchedule);
    $this->assertDatabaseHas('special_work_schedules', [
        'name' => 'Ramadan 2027',
        'schedule_profile' => 'security',
        'shift' => 'night',
        'check_in' => '20:00:00',
        'check_out' => '08:00:00',
        'check_out_day_offset' => 1,
    ]);
    $this->assertDatabaseCount('special_work_schedules', 3);
});

test('non admins cannot download or import the schedule guide', function () {
    $nonAdmin = User::factory()->create(['role' => 4]);
    $admin = User::factory()->create(['role' => 5]);

    $this->actingAs($nonAdmin)
        ->get(route('admin.attendance.schedule-guide.download'))
        ->assertForbidden();
    $this->actingAs($admin)
        ->post(route('admin.developer-settings.special-work-schedules.import'))
        ->assertForbidden();
});

/**
 * @param  list<array<int, string|int|float>>  $rows
 */
function specialScheduleWorkbook(array $rows): string
{
    $headers = [
        'Nama Periode',
        'Tanggal Mulai',
        'Tanggal Selesai',
        'Profile',
        'Tipe Hari',
        'Shift',
        'Jam Masuk',
        'Jam Keluar',
        'Akhir Shift',
        'Jam Kerja',
        'Jumlah Punch',
        'Company',
        'Catatan',
    ];
    $sheetRows = [
        specialScheduleWorkbookRow(1, ['JADWAL PERUBAHAN / RAMADAN']),
        specialScheduleWorkbookRow(2, ['Isi jadwal khusus pada tabel di bawah.']),
        specialScheduleWorkbookRow(5, $headers),
    ];

    foreach ($rows as $index => $row) {
        $sheetRows[] = specialScheduleWorkbookRow($index + 6, $row);
    }

    $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<sheetData>'.implode('', $sheetRows).'</sheetData></worksheet>';
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="Jadwal Kerja" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $relationships = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
        .'Target="worksheets/sheet1.xml"/></Relationships>';
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        .'</Types>';
    $rootRelationships = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" '
        .'Target="xl/workbook.xml"/></Relationships>';
    $path = tempnam(sys_get_temp_dir(), 'schedule-guide-');
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('[Content_Types].xml', $contentTypes);
    $archive->addFromString('_rels/.rels', $rootRelationships);
    $archive->addFromString('xl/workbook.xml', $workbook);
    $archive->addFromString('xl/_rels/workbook.xml.rels', $relationships);
    $archive->addFromString('xl/worksheets/sheet1.xml', $worksheet);
    $archive->close();
    $content = file_get_contents($path);
    unlink($path);

    return $content;
}

/**
 * @param  array<int, string|int|float>  $values
 */
function specialScheduleWorkbookRow(int $rowNumber, array $values): string
{
    $cells = '';

    foreach ($values as $index => $value) {
        $reference = specialScheduleColumn($index + 1).$rowNumber;
        $escaped = htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $cells .= is_int($value) || is_float($value)
            ? '<c r="'.$reference.'"><v>'.$value.'</v></c>'
            : '<c r="'.$reference.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
    }

    return '<row r="'.$rowNumber.'">'.$cells.'</row>';
}

function specialScheduleColumn(int $number): string
{
    $column = '';

    while ($number > 0) {
        $number--;
        $column = chr(65 + ($number % 26)).$column;
        $number = intdiv($number, 26);
    }

    return $column;
}

function excelSerial(string $date): float
{
    return (float) CarbonImmutable::parse('1899-12-30')->diffInDays(
        CarbonImmutable::parse($date),
    );
}
