<?php

use App\Enums\PayrollMethod;
use App\Support\Payroll\KaryawanMigrationService;

test('legacy employee id is mapped to nip during copy preview', function () {
    $service = new KaryawanMigrationService;

    $mappings = collect($service->buildFieldMappings(
        [['column_name' => 'id_karyawan', 'column_type' => 'int unsigned']],
        [['column_name' => 'nip', 'column_type' => 'bigint unsigned']],
    ));

    expect($mappings->firstWhere('source_field', 'id_karyawan'))
        ->toMatchArray([
            'target_field' => 'nip',
            'status' => 'renamed',
        ]);
});

test('legacy employee fields are mapped to renamed employee columns', function (
    string $legacyField,
    string $targetField,
    string $value,
) {
    $service = new KaryawanMigrationService;

    $mappings = collect($service->buildFieldMappings(
        [['column_name' => $legacyField, 'column_type' => 'varchar(255)']],
        [['column_name' => $targetField, 'column_type' => 'varchar(255)']],
    ));

    expect($mappings->firstWhere('source_field', $legacyField))
        ->toMatchArray([
            'target_field' => $targetField,
            'status' => 'renamed',
        ])
        ->and($service->mapSourceRow([$legacyField => $value], [])[$targetField])
        ->toBe($value);
})->with([
    'alamat tinggal' => ['alamat_tinggal', 'alamat_tinggal_sekarang', 'Jl. Baru'],
    'nama kontak darurat 1' => ['kontak_darurat', 'nama_contact_darurat', 'Ibu'],
    'nama kontak darurat 2' => ['kontak_darurat2', 'nama_contact_darurat_2', 'Saudara'],
    'contact darurat 1' => ['hp1', 'contact_darurat_1', '081111111111'],
    'contact darurat 2' => ['hp2', 'contact_darurat_2', '082222222222'],
    'hubungan 1' => ['hubungan1', 'hubungan_1', 'Orang tua'],
    'hubungan 2' => ['hubungan2', 'hubungan_2', 'Saudara'],
]);

test('source row maps legacy employee id to nip without a uuid id', function () {
    $service = new KaryawanMigrationService;

    $row = $service->mapSourceRow([
        'id' => 'legacy-uuid-value',
        'id_karyawan' => '42',
        'nama' => 'Budi Santoso',
        'status_karyawan' => 'PKWT',
        'outsource' => 1,
    ], []);

    expect($row)
        ->not->toHaveKey('id')
        ->toHaveKey('nip', 42)
        ->and($row['nama'])->toBe('Budi Santoso');
});

test('source row normalizes legacy payroll method values', function (string $legacyValue, PayrollMethod $method) {
    $service = new KaryawanMigrationService;

    $row = $service->mapSourceRow([
        'metode_penggajian' => $legacyValue,
    ], []);

    expect($row['metode_penggajian'])->toBe($method->value);
})->with([
    'legacy hourly compact' => ['Perjam', PayrollMethod::Hourly],
    'legacy hourly spaced' => ['Per Jam', PayrollMethod::Hourly],
    'legacy monthly compact' => ['Perbulan', PayrollMethod::Monthly],
    'legacy monthly label' => ['Bulanan', PayrollMethod::Monthly],
]);

test('copy skips application user email until employee already exists', function () {
    $service = new KaryawanMigrationService;
    $applicationEmails = ['kokonacci@gmail.com' => true];

    expect($service->shouldSkipSourceRowForApplicationUser(
        email: 'KOKONACCI@gmail.com',
        karyawanExists: false,
        applicationEmails: $applicationEmails,
    ))->toBeTrue()
        ->and($service->shouldSkipSourceRowForApplicationUser(
            email: 'kokonacci@gmail.com',
            karyawanExists: true,
            applicationEmails: $applicationEmails,
        ))->toBeFalse();
});
