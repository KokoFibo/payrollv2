<?php

use App\Models\Company;
use App\Models\User;
use App\Support\Payroll\KaryawanMigrationService;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

test('developer settings are available only to developers', function (int $role, int $status) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get('/admin/developer-settings')
        ->assertStatus($status);
})->with([
    'developer' => [8, 200],
    'super admin' => [7, 403],
    'admin' => [5, 403],
]);

test('developer settings explain when the source database is not configured', function () {
    $developer = User::factory()->create(['role' => 8]);
    config()->set('database.source_database', null);

    $this->actingAs($developer)
        ->get('/admin/developer-settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sourceDatabase', '')
            ->where('preview.error', 'DB_SOURCE_DATABASE belum diisi di file .env.'));
});

test('developer settings render the selected master data resource', function () {
    $developer = User::factory()->create(['role' => 8]);
    Company::factory()->create(['name' => 'Company Alpha']);
    config()->set('database.source_database', 'legacy_payroll');

    $this->actingAs($developer)
        ->get('/admin/developer-settings?resource=companies')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DeveloperSettings/Index')
            ->where('resource', 'companies')
            ->where('sourceDatabase', 'legacy_payroll')
            ->has('records.data', 1)
            ->has('nationalHolidays', 0)
            ->has('calendarOverrides', 0)
            ->has('specialWorkSchedules', 0));
});

test('developer can create update and delete master data', function () {
    $developer = User::factory()->create(['role' => 8]);

    $this->actingAs($developer)
        ->post(route('admin.developer-settings.master.store', 'companies'), ['name' => 'Company One'])
        ->assertRedirect(route('admin.developer-settings.index', ['resource' => 'companies']));

    $company = Company::query()->where('name', 'Company One')->firstOrFail();

    $this->actingAs($developer)
        ->put(route('admin.developer-settings.master.update', [
            'resource' => 'companies',
            'record' => $company->id,
        ]), ['name' => 'Company Updated'])
        ->assertRedirect(route('admin.developer-settings.index', ['resource' => 'companies']));

    expect($company->refresh()->name)->toBe('Company Updated');

    $this->actingAs($developer)
        ->delete(route('admin.developer-settings.master.destroy', [
            'resource' => 'companies',
            'record' => $company->id,
        ]))
        ->assertRedirect(route('admin.developer-settings.index', ['resource' => 'companies']));

    $this->assertModelMissing($company);
});

test('developer can copy five master tables in one request', function () {
    $developer = User::factory()->create(['role' => 8]);
    $result = [
        'statistics' => [
            [
                'key' => 'company',
                'label' => 'Company',
                'source_table' => 'legacy_payroll.companies',
                'target_table' => 'companies',
                'deleted_rows' => 2,
                'source_rows' => 2,
                'created_rows' => 1,
                'updated_rows' => 0,
                'skipped_rows' => 0,
                'target_rows' => 1,
            ],
        ],
        'totals' => [
            'deleted_rows' => 2,
            'source_rows' => 2,
            'created_rows' => 1,
            'updated_rows' => 0,
            'skipped_rows' => 0,
        ],
    ];

    $this->mock(KaryawanMigrationService::class, function (MockInterface $mock) use ($result): void {
        $mock->shouldReceive('copyMasterData')->once()->andReturn($result);
    });

    $this->actingAs($developer)
        ->post(route('admin.developer-settings.copy-master-data'))
        ->assertRedirect(route('admin.developer-settings.index', ['resource' => 'companies']))
        ->assertSessionHas('master_copy_result', $result)
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'success'
            && str_contains($toast['message'], '2 data lama dihapus'));
});

test('non developers cannot copy reference master data', function () {
    $admin = User::factory()->create(['role' => 5]);

    $this->actingAs($admin)
        ->post(route('admin.developer-settings.copy-master-data'))
        ->assertForbidden();
});

test('developer can replace all destination employees from source', function () {
    $developer = User::factory()->create(['role' => 8]);
    $result = [
        'statistics' => [
            'source_rows' => 3,
            'deleted_rows' => 2,
            'inserted' => 3,
            'updated' => 0,
            'user_rows_skipped' => 0,
            'emails_cleared' => 0,
            'accounts_cleared' => 0,
            'directorates_cleared' => 0,
        ],
        'reference_data' => [],
    ];

    $this->mock(KaryawanMigrationService::class, function (MockInterface $mock) use ($result): void {
        $mock->shouldReceive('copy')->once()->andReturn($result);
    });

    $this->actingAs($developer)
        ->post(route('admin.developer-settings.copy'))
        ->assertRedirect(route('admin.developer-settings.index', ['resource' => 'companies']))
        ->assertSessionHas('migration_result', $result)
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'success'
            && str_contains($toast['message'], '2 data lama dihapus')
            && str_contains($toast['message'], '3 data karyawan source'));
});

test('non developers cannot replace destination employees', function () {
    $admin = User::factory()->create(['role' => 5]);

    $this->actingAs($admin)
        ->post(route('admin.developer-settings.copy'))
        ->assertForbidden();
});
