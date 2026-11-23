<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KaryawanStatus;
use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Support\AdminSalaryVisibility;
use App\Support\MasterDataResources;
use App\Support\Payroll\KaryawanMigrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DeveloperSettingsController extends Controller
{
    private const RESOURCE_OPTIONS = ['companies', 'directorates', 'departments', 'jabatans', 'etnis', 'job-grades'];

    public function index(
        Request $request,
        KaryawanMigrationService $migrationService,
        AdminSalaryVisibility $salaryVisibility,
    ): Response {
        Gate::authorize('access-developer-settings');

        $resource = (string) $request->query('resource', 'companies');

        if (! in_array($resource, self::RESOURCE_OPTIONS, true)) {
            $resource = 'companies';
        }

        $config = MasterDataResources::find($resource);
        $modelClass = $config['model'];
        $search = trim((string) $request->query('search', ''));
        $perPage = $request->integer('perPage', 10);
        $perPage = in_array($perPage, [5, 10, 25, 50, 100], true) ? $perPage : 10;

        $query = $modelClass::query()->withCount('karyawans');

        if ($search !== '') {
            $query->where(function ($query) use ($config, $search): void {
                foreach ($config['search'] as $field) {
                    $query->orWhere($field, 'like', "%{$search}%");
                }
            });
        }

        $records = $query
            ->orderBy($config['order_by'])
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($record): array => [
                'id' => $record->getKey(),
                'name' => MasterDataResources::displayName($record, $config),
                'values' => collect($config['fields'])
                    ->mapWithKeys(fn (array $field): array => [$field['name'] => $record->getAttribute($field['name'])])
                    ->all(),
                'karyawans_count' => (int) ($record->karyawans_count ?? 0),
            ]);

        $preview = [
            'data' => null,
            'error' => null,
        ];

        try {
            $preview['data'] = $migrationService->preview();
        } catch (Throwable $exception) {
            report($exception);
            $preview['error'] = 'Database sumber payrollok tidak dapat dibaca saat ini.';
        }

        return Inertia::render('DeveloperSettings/Index', [
            'resource' => $resource,
            'config' => $config,
            'resources' => collect(MasterDataResources::all())
                ->map(fn (array $item, string $key): array => [
                    'value' => $key,
                    'label' => $item['title'],
                ])
                ->values()
                ->all(),
            'records' => $records,
            'filters' => ['search' => $search, 'perPage' => $perPage],
            'preview' => $preview,
            'migrationResult' => session('migration_result'),
            'adminSalaryLimit' => $salaryVisibility->limit(),
            'counts' => [
                'employees' => Karyawan::query()->count(),
                'activeEmployees' => Karyawan::query()
                    ->whereIn('status_karyawan', KaryawanStatus::valuesForFilter('active'))
                    ->count(),
            ],
        ]);
    }

    public function updateAdminSalaryLimit(
        Request $request,
        AdminSalaryVisibility $salaryVisibility,
    ): RedirectResponse {
        Gate::authorize('access-developer-settings');

        $validated = $request->validate([
            'admin_salary_limit' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ]);

        $salaryVisibility->updateLimit($validated['admin_salary_limit']);

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Batas gaji Admin diperbarui',
            'message' => 'Role Admin sekarang hanya dapat melihat karyawan sampai batas gaji yang baru.',
        ]);
    }

    public function copy(KaryawanMigrationService $migrationService): RedirectResponse
    {
        Gate::authorize('access-developer-settings');

        try {
            $result = $migrationService->copy();

            return redirect()
                ->route('admin.developer-settings.index', ['resource' => 'companies'])
                ->with('migration_result', $result)
                ->with('toast', [
                    'type' => 'success',
                    'title' => 'Copy karyawan selesai',
                    'message' => $result['statistics']['source_rows'].' data karyawan berhasil diproses.',
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast', [
                'type' => 'error',
                'title' => 'Copy karyawan gagal',
                'message' => 'Transaksi dibatalkan. Periksa koneksi payrollok dan log aplikasi.',
            ]);
        }
    }
}
