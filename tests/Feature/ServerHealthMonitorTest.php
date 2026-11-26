<?php

use App\Models\User;
use App\Support\ServerHealth\ServerHealthMonitor;
use Symfony\Component\HttpFoundation\StreamedResponse;

test('server health stream requires an authenticated user', function () {
    $this->get(route('admin.developer-settings.server-health.stream'))
        ->assertRedirect(route('login'));
});

test('non developer roles cannot open the server health stream', function (int $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('admin.developer-settings.server-health.stream'))
        ->assertForbidden();
})->with([
    'supervisor' => [4],
    'admin' => [5],
    'senior admin' => [6],
    'super admin' => [7],
]);

test('developer receives an unbuffered server sent event stream', function () {
    $developer = User::factory()->create(['role' => 8]);

    $response = $this->actingAs($developer)
        ->get(route('admin.developer-settings.server-health.stream'));

    $response
        ->assertOk()
        ->assertHeader('content-type', 'text/event-stream; charset=utf-8')
        ->assertHeader('x-accel-buffering', 'no');

    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
});

test('server health snapshot exposes the required diagnostics', function () {
    $snapshot = app(ServerHealthMonitor::class)->snapshot();

    expect($snapshot)
        ->toHaveKeys([
            'generated_at',
            'hostname',
            'platform',
            'php_version',
            'cpu',
            'load_average',
            'memory',
            'disk',
            'uptime_seconds',
            'services',
            'failed_jobs',
            'processes',
            'ssl',
        ])
        ->and($snapshot['services'])->toHaveCount(4)
        ->and($snapshot['memory'])->toHaveKeys(['ram', 'swap'])
        ->and($snapshot['load_average'])->toHaveKeys(['one', 'five', 'fifteen'])
        ->and($snapshot['ssl'])->toHaveKeys(['domain', 'status', 'expires_at', 'days_remaining', 'message']);

    expect(count($snapshot['processes']))->toBeLessThanOrEqual(5);
});
