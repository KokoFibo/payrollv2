<?php

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
