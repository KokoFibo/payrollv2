<?php

use App\Models\Company;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

test('developer settings render the selected master data resource', function () {
    $developer = User::factory()->create(['role' => 8]);
    Company::factory()->create(['name' => 'Company Alpha']);

    $this->actingAs($developer)
        ->get('/admin/developer-settings?resource=companies')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DeveloperSettings/Index')
            ->where('resource', 'companies')
            ->has('records.data', 1));
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
