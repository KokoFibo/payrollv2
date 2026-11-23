<?php

use App\Enums\KaryawanStatus;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Support\Facades\File;

test('admin can download the special employee export', function () {
    $admin = User::factory()->create(['role' => 5]);
    Karyawan::factory()->create([
        'nip' => 910001,
        'nama' => 'Active Export Employee',
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'jenis_identitas' => 'KTP',
        'no_identitas' => '3273010101010001',
        'tempat_lahir' => 'Jakarta',
        'alamat_identitas' => 'Jl. Identitas',
        'alamat_tinggal' => 'Jl. Tinggal',
        'email' => 'active.export@example.com',
        'hp' => '081234567890',
        'nomor_rekening' => '001234567890',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.employees.export-khusus', [
        'semua_data' => 0,
        'bulan' => 11,
        'tahun' => 2026,
    ]));

    $response->assertOk();

    expect($response->headers->get('content-type'))
        ->toContain('spreadsheetml.sheet');

    $worksheet = worksheetXmlFromResponse($response);

    expect($worksheet)
        ->toContain('NIK/Passport')
        ->toContain('Nomor Identitas')
        ->toContain('Alamat ID')
        ->toContain('Alamat Tinggal')
        ->toContain('No HP')
        ->toContain('Placement')
        ->toContain('Tanggal Resigned/Blacklist')
        ->toContain('No Rekening')
        ->toContain('3273010101010001')
        ->toContain('001234567890')
        ->not->toContain('&apos;3273010101010001')
        ->not->toContain('&apos;001234567890')
        ->not->toContain('Gaji Pokok')
        ->not->toContain('NPWP');
});

test('special export applies resigned and blacklist date filtering', function () {
    $admin = User::factory()->create(['role' => 5]);

    Karyawan::factory()->create([
        'nip' => 920001,
        'nama' => 'Active Employee',
        'status_karyawan' => KaryawanStatus::Pkwt->value,
    ]);
    Karyawan::factory()->create([
        'nip' => 920002,
        'nama' => 'Recent Resigned',
        'status_karyawan' => KaryawanStatus::Resigned->value,
        'tanggal_resigned' => '2026-11-10',
    ]);
    Karyawan::factory()->create([
        'nip' => 920003,
        'nama' => 'Old Resigned',
        'status_karyawan' => KaryawanStatus::Resigned->value,
        'tanggal_resigned' => '2026-09-10',
    ]);
    Karyawan::factory()->create([
        'nip' => 920004,
        'nama' => 'Recent Blacklist',
        'status_karyawan' => KaryawanStatus::Blacklist->value,
        'tanggal_blacklist' => '2026-11-05',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.employees.export-khusus', [
        'semua_data' => 0,
        'bulan' => 11,
        'tahun' => 2026,
    ]));
    $response->assertOk();

    $worksheet = worksheetXmlFromResponse($response);

    expect($worksheet)
        ->toContain('920001')
        ->toContain('920002')
        ->toContain('920004')
        ->not->toContain('920003');
});

test('special export includes all historical statuses when all data is selected', function () {
    $admin = User::factory()->create(['role' => 5]);

    Karyawan::factory()->create([
        'nip' => 930001,
        'nama' => 'Old Resigned',
        'status_karyawan' => KaryawanStatus::Resigned->value,
        'tanggal_resigned' => '2024-01-10',
    ]);
    Karyawan::factory()->create([
        'nip' => 930002,
        'nama' => 'Old Blacklist',
        'status_karyawan' => KaryawanStatus::Blacklist->value,
        'tanggal_blacklist' => '2024-02-10',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.employees.export-khusus', [
        'semua_data' => 1,
        'bulan' => 11,
        'tahun' => 2026,
    ]));
    $response->assertOk();

    $worksheet = worksheetXmlFromResponse($response);

    expect($worksheet)
        ->toContain('930001')
        ->toContain('930002')
        ->toContain('Data Seluruh Karyawan');
});

test('special export validates period parameters', function () {
    $admin = User::factory()->create(['role' => 5]);

    $this->actingAs($admin)
        ->from('/admin/employees')
        ->get(route('admin.employees.export-khusus', [
            'semua_data' => 0,
            'bulan' => 13,
            'tahun' => 1999,
        ]))
        ->assertSessionHasErrors(['bulan', 'tahun']);
});

function worksheetXmlFromResponse($response): string
{
    $path = $response->baseResponse->getFile()->getPathname();
    $archive = new ZipArchive;

    expect($archive->open($path))->toBeTrue();

    $worksheet = $archive->getFromName('xl/worksheets/sheet1.xml');
    $archive->close();
    File::delete($path);

    return (string) $worksheet;
}
