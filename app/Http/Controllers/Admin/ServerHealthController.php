<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ServerHealth\ServerHealthMonitor;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ServerHealthController extends Controller
{
    private const SNAPSHOT_INTERVAL_SECONDS = 2.5;

    private const ABORT_CHECK_INTERVAL_MICROSECONDS = 250_000;

    public function __invoke(ServerHealthMonitor $monitor): StreamedResponse
    {
        Gate::authorize('access-developer-settings');

        return response()->stream(function () use ($monitor): void {
            $this->prepareStream();

            while (! connection_aborted()) {
                try {
                    $payload = json_encode(
                        $monitor->snapshot(),
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                    );
                    $this->sendEvent('health', $payload);
                } catch (Throwable $exception) {
                    report($exception);
                    $this->sendEvent('health-error', json_encode([
                        'message' => 'Metrik server gagal dibaca.',
                    ], JSON_THROW_ON_ERROR));
                }

                if (connection_aborted()) {
                    break;
                }

                $nextSnapshotAt = microtime(true) + self::SNAPSHOT_INTERVAL_SECONDS;

                while (microtime(true) < $nextSnapshotAt) {
                    usleep(self::ABORT_CHECK_INTERVAL_MICROSECONDS);

                    if (connection_aborted()) {
                        break 2;
                    }

                    echo ": heartbeat\n\n";
                    $this->flushOutput();

                    if (connection_aborted()) {
                        break 2;
                    }
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function prepareStream(): void
    {
        ignore_user_abort(false);
        set_time_limit(0);
        ini_set('zlib.output_compression', '0');
        ini_set('implicit_flush', '1');
    }

    private function sendEvent(string $event, string $data): void
    {
        echo "retry: 3000\n";
        echo "event: {$event}\n";
        echo "data: {$data}\n\n";
        $this->flushOutput();
    }

    private function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }
}
