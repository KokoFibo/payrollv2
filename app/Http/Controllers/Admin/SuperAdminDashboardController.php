<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KaryawanStatus;
use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Support\Payroll\SalaryAdjustTemplateExporter;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SuperAdminDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('access-super-admin-dashboard');

        $query = $this->activeEmployeeQuery();

        return Inertia::render('SuperAdmin/Dashboard', [
            'readiness' => [
                'eligibleEmployees' => (clone $query)->count(),
                'missingDirectorate' => (clone $query)->whereNull('directorate_id')->count(),
                'missingDepartment' => (clone $query)->whereNull('department_id')->count(),
                'missingSalary' => (clone $query)
                    ->where(fn (Builder $query) => $query->whereNull('gaji_pokok')->orWhere('gaji_pokok', 0))
                    ->count(),
            ],
            'roleName' => $request->user()?->assignedRole?->name ?? 'Privileged User',
            'today' => now()->locale('id')->translatedFormat('d M Y'),
        ]);
    }

    public function exportDepartment(SalaryAdjustTemplateExporter $exporter): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('access-super-admin-dashboard');

        return $this->downloadExportedArchive(
            fn (): array => $exporter->exportDepartmentTemplates(),
        );
    }

    public function exportDirectorate(SalaryAdjustTemplateExporter $exporter): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('access-super-admin-dashboard');

        return $this->downloadExportedArchive(
            fn (): array => $exporter->exportDirectorateTemplates(),
        );
    }

    private function activeEmployeeQuery(): Builder
    {
        return Karyawan::query()
            ->whereIn('status_karyawan', KaryawanStatus::valuesForFilter('active'));
    }

    /**
     * @param  callable(): array{path: string, filename: string}  $export
     */
    private function downloadExportedArchive(callable $export): BinaryFileResponse|RedirectResponse
    {
        try {
            $result = $export();
        } catch (DomainException $exception) {
            return back()->with('toast', [
                'type' => 'error',
                'title' => 'Export gagal',
                'message' => $exception->getMessage(),
            ]);
        }

        return response()
            ->download($result['path'], $result['filename'], [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }
}
