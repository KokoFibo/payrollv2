<?php

namespace App\Support\ServerHealth;

use Illuminate\Support\Facades\DB;
use Throwable;

class ServerHealthMonitor
{
    private const GIGABYTE = 1024 ** 3;

    /** @var array{idle: float, total: float}|null */
    private ?array $previousCpuSample = null;

    /** @var array<string, mixed>|null */
    private ?array $windowsSystemInfo = null;

    /** @var array<string, mixed>|null */
    private ?array $sslStatus = null;

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $memory = $this->memoryMetrics();
        $diagnostics = $this->systemDiagnostics();

        return [
            'generated_at' => now()->toIso8601String(),
            'hostname' => gethostname() ?: 'unknown',
            'platform' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'cpu' => [
                'usage_percent' => $this->cpuUsagePercent(),
                'cores' => $this->cpuCoreCount(),
            ],
            'load_average' => $this->loadAverage(),
            'memory' => $memory,
            'disk' => $this->diskMetrics(),
            'uptime_seconds' => $this->uptimeSeconds(),
            'services' => $diagnostics['services'],
            'failed_jobs' => $this->failedJobsCount(),
            'processes' => $diagnostics['processes'],
            'ssl' => $this->sslCertificateStatus(),
        ];
    }

    private function cpuUsagePercent(): ?float
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = $this->runCommand([
                'powershell.exe',
                '-NoProfile',
                '-NonInteractive',
                '-Command',
                '(Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average',
            ], 2.0);

            return is_numeric($output)
                ? round(max(0, min(100, (float) $output)), 1)
                : null;
        }

        $sample = $this->linuxCpuSample();

        if ($sample === null) {
            return null;
        }

        if ($this->previousCpuSample === null) {
            usleep(200_000);
            $currentSample = $this->linuxCpuSample();

            if ($currentSample === null) {
                return null;
            }

            $this->previousCpuSample = $sample;
            $sample = $currentSample;
        }

        $idleDelta = $sample['idle'] - $this->previousCpuSample['idle'];
        $totalDelta = $sample['total'] - $this->previousCpuSample['total'];
        $this->previousCpuSample = $sample;

        if ($totalDelta <= 0) {
            return null;
        }

        return round(max(0, min(100, (1 - ($idleDelta / $totalDelta)) * 100)), 1);
    }

    /**
     * @return array{idle: float, total: float}|null
     */
    private function linuxCpuSample(): ?array
    {
        $stats = @file('/proc/stat');

        if (! is_array($stats) || ! isset($stats[0])) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($stats[0]));

        if ($parts === false || count($parts) < 5 || $parts[0] !== 'cpu') {
            return null;
        }

        $values = array_map(static fn (string $value): float => (float) $value, array_slice($parts, 1));

        return [
            'idle' => ($values[3] ?? 0) + ($values[4] ?? 0),
            'total' => array_sum($values),
        ];
    }

    private function cpuCoreCount(): int
    {
        $environmentCores = (int) ($_SERVER['NUMBER_OF_PROCESSORS'] ?? getenv('NUMBER_OF_PROCESSORS') ?: 0);

        if ($environmentCores > 0) {
            return $environmentCores;
        }

        $cpuInfo = @file('/proc/cpuinfo');

        if (is_array($cpuInfo)) {
            $processorCount = count(array_filter(
                $cpuInfo,
                static fn (string $line): bool => str_starts_with($line, 'processor'),
            ));

            if ($processorCount > 0) {
                return $processorCount;
            }
        }

        return 1;
    }

    /**
     * @return array{one: float|null, five: float|null, fifteen: float|null}
     */
    private function loadAverage(): array
    {
        if (! function_exists('sys_getloadavg')) {
            return ['one' => null, 'five' => null, 'fifteen' => null];
        }

        $load = @sys_getloadavg();

        if (! is_array($load) || count($load) < 3) {
            return ['one' => null, 'five' => null, 'fifteen' => null];
        }

        return [
            'one' => round((float) $load[0], 2),
            'five' => round((float) $load[1], 2),
            'fifteen' => round((float) $load[2], 2),
        ];
    }

    /**
     * @return array<string, array{total_gb: float, used_gb: float, percent: float|null}>
     */
    private function memoryMetrics(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $systemInfo = $this->windowsSystemInfo();
            $totalBytes = (float) ($systemInfo['total_memory_bytes'] ?? 0);
            $freeBytes = (float) ($systemInfo['free_memory_bytes'] ?? 0);
            $swapTotalBytes = (float) ($systemInfo['swap_total_bytes'] ?? 0);
            $swapFreeBytes = (float) ($systemInfo['swap_free_bytes'] ?? 0);

            return [
                'ram' => $this->memoryMetric($totalBytes, $totalBytes - $freeBytes),
                'swap' => $this->memoryMetric($swapTotalBytes, $swapTotalBytes - $swapFreeBytes),
            ];
        }

        $memoryInfo = $this->linuxMemoryInfo();

        if ($memoryInfo === null) {
            return [
                'ram' => $this->memoryMetric(null, null),
                'swap' => $this->memoryMetric(null, null),
            ];
        }

        $totalKb = $memoryInfo['MemTotal'] ?? 0;
        $availableKb = $memoryInfo['MemAvailable']
            ?? (($memoryInfo['MemFree'] ?? 0)
                + ($memoryInfo['Buffers'] ?? 0)
                + ($memoryInfo['Cached'] ?? 0));
        $swapTotalKb = $memoryInfo['SwapTotal'] ?? 0;
        $swapFreeKb = $memoryInfo['SwapFree'] ?? 0;

        return [
            'ram' => $this->memoryMetric(
                $totalKb * 1024,
                ($totalKb - $availableKb) * 1024,
            ),
            'swap' => $this->memoryMetric(
                $swapTotalKb * 1024,
                ($swapTotalKb - $swapFreeKb) * 1024,
            ),
        ];
    }

    /**
     * @return array<string, int>|null
     */
    private function linuxMemoryInfo(): ?array
    {
        $lines = @file('/proc/meminfo');

        if (! is_array($lines)) {
            return null;
        }

        $metrics = [];

        foreach ($lines as $line) {
            if (preg_match('/^([A-Za-z_]+):\s+(\d+)\s+kB$/', trim($line), $matches) === 1) {
                $metrics[$matches[1]] = (int) $matches[2];
            }
        }

        return $metrics === [] ? null : $metrics;
    }

    /**
     * @return array{total_gb: float, used_gb: float, percent: float|null}
     */
    private function memoryMetric(?float $totalBytes, ?float $usedBytes): array
    {
        $total = max(0, $totalBytes ?? 0);
        $used = max(0, min($total, $usedBytes ?? 0));

        return [
            'total_gb' => round($total / self::GIGABYTE, 2),
            'used_gb' => round($used / self::GIGABYTE, 2),
            'percent' => $total > 0 ? round(($used / $total) * 100, 1) : null,
        ];
    }

    /**
     * @return array{path: string, total_gb: float, used_gb: float, percent: float|null}|null
     */
    private function diskMetrics(): ?array
    {
        $totalBytes = @disk_total_space(base_path());
        $freeBytes = @disk_free_space(base_path());

        if ($totalBytes === false || $freeBytes === false || $totalBytes <= 0) {
            return null;
        }

        $usedBytes = max(0, $totalBytes - $freeBytes);

        return [
            'path' => base_path(),
            'total_gb' => round($totalBytes / self::GIGABYTE, 2),
            'used_gb' => round($usedBytes / self::GIGABYTE, 2),
            'percent' => round(($usedBytes / $totalBytes) * 100, 1),
        ];
    }

    private function uptimeSeconds(): ?int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $bootTimestamp = $this->windowsSystemInfo()['boot_timestamp'] ?? null;

            return is_int($bootTimestamp)
                ? max(0, time() - $bootTimestamp)
                : null;
        }

        $contents = @file_get_contents('/proc/uptime');

        if (! is_string($contents)) {
            return null;
        }

        return (int) floor((float) strtok($contents, ' '));
    }

    private function failedJobsCount(): ?int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *     services: list<array{key: string, label: string, active: bool, present: bool, detail: string}>,
     *     processes: list<array{pid: int|null, name: string, memory_mb: float}>
     * }
     */
    private function systemDiagnostics(): array
    {
        $processRows = PHP_OS_FAMILY === 'Windows'
            ? $this->windowsProcessRows()
            : $this->linuxProcessRows();
        $processNames = array_map(
            static fn (array $process): string => strtolower($process['name']),
            $processRows,
        );
        $pm2 = $this->pm2Status();

        usort(
            $processRows,
            static fn (array $left, array $right): int => $right['memory_bytes'] <=> $left['memory_bytes'],
        );

        return [
            'services' => [
                [
                    'key' => 'nginx',
                    'label' => 'Nginx',
                    'active' => $this->containsProcess($processNames, 'nginx'),
                    'present' => true,
                    'detail' => 'Web server',
                ],
                [
                    'key' => 'php-fpm',
                    'label' => 'PHP-FPM',
                    'active' => $this->containsProcess($processNames, 'php-fpm'),
                    'present' => true,
                    'detail' => 'PHP application server',
                ],
                [
                    'key' => 'database',
                    'label' => 'MySQL / MariaDB',
                    'active' => $this->containsProcess($processNames, 'mysql')
                        || $this->containsProcess($processNames, 'mariadb'),
                    'present' => true,
                    'detail' => 'Database server',
                ],
                [
                    'key' => 'pm2',
                    'label' => 'PM2',
                    'active' => $pm2['active'],
                    'present' => $pm2['present'],
                    'detail' => $pm2['detail'],
                ],
            ],
            'processes' => collect($processRows)
                ->take(5)
                ->map(fn (array $process): array => [
                    'pid' => $process['pid'],
                    'name' => $process['name'],
                    'memory_mb' => round($process['memory_bytes'] / 1024 / 1024, 1),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{pid: int|null, name: string, memory_bytes: int}>
     */
    private function linuxProcessRows(): array
    {
        $output = $this->runCommand(['ps', '-eo', 'pid=,comm=,rss='], 2.0);

        if ($output === null || trim($output) === '') {
            return $this->linuxProcessFallback();
        }

        $processes = [];

        foreach (preg_split('/\r?\n/', trim($output)) ?: [] as $line) {
            if (preg_match('/^\s*(\d+)\s+(\S+)\s+(\d+)\s*$/', $line, $matches) !== 1) {
                continue;
            }

            $processes[] = [
                'pid' => (int) $matches[1],
                'name' => $matches[2],
                'memory_bytes' => (int) $matches[3] * 1024,
            ];
        }

        return $processes;
    }

    /**
     * @return list<array{pid: int|null, name: string, memory_bytes: int}>
     */
    private function linuxProcessFallback(): array
    {
        $processes = [];

        foreach (glob('/proc/[0-9]*/status') ?: [] as $statusPath) {
            $status = @file_get_contents($statusPath);

            if (! is_string($status)
                || preg_match('/^Name:\s+(.+)$/m', $status, $nameMatch) !== 1
                || preg_match('/^VmRSS:\s+(\d+)\s+kB$/m', $status, $memoryMatch) !== 1) {
                continue;
            }

            $processes[] = [
                'pid' => (int) basename(dirname($statusPath)),
                'name' => trim($nameMatch[1]),
                'memory_bytes' => (int) $memoryMatch[1] * 1024,
            ];
        }

        return $processes;
    }

    /**
     * @return list<array{pid: int|null, name: string, memory_bytes: int}>
     */
    private function windowsProcessRows(): array
    {
        $output = $this->runCommand([
            'powershell.exe',
            '-NoProfile',
            '-NonInteractive',
            '-Command',
            'Get-Process | Select-Object Id, ProcessName, WorkingSet64 | ConvertTo-Json -Compress',
        ], 3.0);

        if (! is_string($output) || $output === '') {
            return [];
        }

        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            return [];
        }

        if (! array_is_list($decoded)) {
            $decoded = [$decoded];
        }

        return collect($decoded)
            ->map(fn (array $process): array => [
                'pid' => isset($process['Id']) ? (int) $process['Id'] : null,
                'name' => (string) ($process['ProcessName'] ?? 'unknown'),
                'memory_bytes' => (int) ($process['WorkingSet64'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $processNames
     */
    private function containsProcess(array $processNames, string $needle): bool
    {
        foreach ($processNames as $processName) {
            if (str_contains($processName, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{active: bool, present: bool, detail: string}
     */
    private function pm2Status(): array
    {
        $pm2Binary = $this->commandPath('pm2');

        if ($pm2Binary === null) {
            return [
                'active' => false,
                'present' => false,
                'detail' => 'Tidak terpasang',
            ];
        }

        $output = $this->runCommand([$pm2Binary, 'jlist'], 3.0);
        $decoded = is_string($output) ? json_decode($output, true) : null;

        if (! is_array($decoded)) {
            return [
                'active' => false,
                'present' => true,
                'detail' => 'Status PM2 tidak dapat dibaca',
            ];
        }

        if (! array_is_list($decoded)) {
            $decoded = [$decoded];
        }

        $onlineCount = collect($decoded)
            ->filter(fn (array $process): bool => ($process['pm2_env']['status'] ?? null) === 'online')
            ->count();

        return [
            'active' => $onlineCount > 0,
            'present' => true,
            'detail' => $onlineCount > 0
                ? $onlineCount.' process online'
                : count($decoded).' process terdaftar, tidak ada yang online',
        ];
    }

    private function commandPath(string $command): ?string
    {
        $extensions = PHP_OS_FAMILY === 'Windows'
            ? ['.exe', '.cmd', '.bat', '']
            : [''];
        $directories = explode(PATH_SEPARATOR, (string) (getenv('PATH') ?: ''));

        foreach ($directories as $directory) {
            foreach ($extensions as $extension) {
                $candidate = rtrim($directory, DIRECTORY_SEPARATOR)
                    .DIRECTORY_SEPARATOR
                    .$command
                    .$extension;

                if (is_file($candidate) && is_executable($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @return array{
     *     domain: string|null,
     *     status: string,
     *     expires_at: string|null,
     *     days_remaining: int|null,
     *     message: string
     * }
     */
    private function sslCertificateStatus(): array
    {
        if ($this->sslStatus !== null) {
            return $this->sslStatus;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($host)
            || $host === ''
            || in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)
            || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->sslStatus = [
                'domain' => is_string($host) ? $host : null,
                'status' => 'unavailable',
                'expires_at' => null,
                'days_remaining' => null,
                'message' => 'APP_URL belum memakai domain publik.',
            ];
        }

        if (! function_exists('openssl_x509_parse')) {
            return $this->sslStatus = [
                'domain' => $host,
                'status' => 'unavailable',
                'expires_at' => null,
                'days_remaining' => null,
                'message' => 'Ekstensi OpenSSL PHP tidak tersedia.',
            ];
        }

        $socketHost = str_contains($host, ':') ? '['.$host.']' : $host;
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);
        $socket = @stream_socket_client(
            'ssl://'.$socketHost.':443',
            $errorCode,
            $errorMessage,
            2.0,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (! is_resource($socket)) {
            return $this->sslStatus = [
                'domain' => $host,
                'status' => 'unavailable',
                'expires_at' => null,
                'days_remaining' => null,
                'message' => 'Koneksi TLS gagal: '.$errorMessage,
            ];
        }

        $options = stream_context_get_options($socket);
        $certificate = $options['ssl']['peer_certificate'] ?? null;
        fclose($socket);

        if ($certificate === null) {
            return $this->sslStatus = [
                'domain' => $host,
                'status' => 'unavailable',
                'expires_at' => null,
                'days_remaining' => null,
                'message' => 'Sertifikat TLS tidak dapat dibaca.',
            ];
        }

        $parsedCertificate = openssl_x509_parse($certificate);
        $validTo = $parsedCertificate['validTo_time_t'] ?? null;

        if (! is_int($validTo)) {
            return $this->sslStatus = [
                'domain' => $host,
                'status' => 'unavailable',
                'expires_at' => null,
                'days_remaining' => null,
                'message' => 'Tanggal kedaluwarsa sertifikat tidak tersedia.',
            ];
        }

        $daysRemaining = (int) floor(($validTo - time()) / 86400);
        $status = $daysRemaining <= 0 ? 'expired' : ($daysRemaining <= 30 ? 'expiring' : 'healthy');

        return $this->sslStatus = [
            'domain' => $host,
            'status' => $status,
            'expires_at' => date(DATE_ATOM, $validTo),
            'days_remaining' => $daysRemaining,
            'message' => match ($status) {
                'expired' => 'Sertifikat sudah kedaluwarsa.',
                'expiring' => 'Sertifikat akan kedaluwarsa dalam '.$daysRemaining.' hari.',
                default => 'Sertifikat masih valid.',
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function windowsSystemInfo(): array
    {
        if ($this->windowsSystemInfo !== null) {
            return $this->windowsSystemInfo;
        }

        $output = $this->runCommand([
            'powershell.exe',
            '-NoProfile',
            '-NonInteractive',
            '-Command',
            '$os = Get-CimInstance Win32_OperatingSystem; [pscustomobject]@{ TotalMemory = $os.TotalVisibleMemorySize; FreeMemory = $os.FreePhysicalMemory; SwapTotal = $os.TotalVirtualMemorySize; SwapFree = $os.FreeVirtualMemory; LastBoot = $os.LastBootUpTime.ToString("o") } | ConvertTo-Json -Compress',
        ], 3.0);
        $decoded = is_string($output) ? json_decode($output, true) : null;

        if (! is_array($decoded)) {
            return $this->windowsSystemInfo = [];
        }

        $bootTimestamp = isset($decoded['LastBoot'])
            ? strtotime((string) $decoded['LastBoot'])
            : false;

        return $this->windowsSystemInfo = [
            'total_memory_bytes' => (int) ($decoded['TotalMemory'] ?? 0) * 1024,
            'free_memory_bytes' => (int) ($decoded['FreeMemory'] ?? 0) * 1024,
            'swap_total_bytes' => (int) ($decoded['SwapTotal'] ?? 0) * 1024,
            'swap_free_bytes' => (int) ($decoded['SwapFree'] ?? 0) * 1024,
            'boot_timestamp' => $bootTimestamp === false ? null : $bootTimestamp,
        ];
    }

    /**
     * @param  list<string>  $command
     */
    private function runCommand(array $command, float $timeoutSeconds): ?string
    {
        if (! function_exists('proc_open')) {
            return null;
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open(
            $command,
            $descriptorSpec,
            $pipes,
            null,
            null,
            ['bypass_shell' => true],
        );

        if (! is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $deadline = microtime(true) + $timeoutSeconds;
        $exitCode = -1;

        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            @stream_select($read, $write, $except, 0, 100_000);

            foreach ($read as $stream) {
                $chunk = stream_get_contents($stream);

                if (is_string($chunk) && $chunk !== '') {
                    if ($stream === $pipes[1]) {
                        $stdout .= $chunk;
                    }
                }
            }

            $status = proc_get_status($process);

            if (! $status['running']) {
                $exitCode = (int) $status['exitcode'];
                break;
            }

            if (microtime(true) >= $deadline) {
                proc_terminate($process);
                break;
            }
        }

        $remainingOutput = stream_get_contents($pipes[1]);

        if (is_string($remainingOutput)) {
            $stdout .= $remainingOutput;
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $closedExitCode = proc_close($process);

        if ($exitCode < 0) {
            $exitCode = $closedExitCode;
        }

        return $exitCode === 0 ? trim($stdout) : null;
    }
}
