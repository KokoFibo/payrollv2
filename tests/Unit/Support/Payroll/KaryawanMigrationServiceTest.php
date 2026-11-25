<?php

use App\Support\Payroll\KaryawanMigrationService;
use Tests\TestCase;

uses(TestCase::class);

test('source database comes from database configuration', function () {
    config()->set('database.source_database', ' legacy_payroll ');

    expect((new KaryawanMigrationService)->sourceDatabase())
        ->toBe('legacy_payroll');
});

test('source database must be configured', function () {
    config()->set('database.source_database', null);

    expect(fn () => (new KaryawanMigrationService)->sourceDatabase())
        ->toThrow(RuntimeException::class, 'DB_SOURCE_DATABASE belum dikonfigurasi.');
});
