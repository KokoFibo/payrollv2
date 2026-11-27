<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KaryawanStatus;
use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\PayrollAuditLog;
use App\Support\Payroll\SalaryAdjustTemplateExporter;
use App\Support\PayrollAuditService;
use Carbon\CarbonImmutable;
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
        $auditSearch = trim((string) $request->query('audit_search', ''));
        $auditEvent = (string) $request->query('audit_event', '');
        $auditFrom = $this->validDate($request->query('audit_from'));
        $auditTo = $this->validDate($request->query('audit_to'));
        $auditLogs = PayrollAuditLog::query()
            ->when($auditSearch !== '', function (Builder $query) use ($auditSearch): void {
                $query->where(function (Builder $query) use ($auditSearch): void {
                    $query->where('actor_name', 'like', "%{$auditSearch}%")
                        ->orWhere('employee_nip', 'like', "%{$auditSearch}%")
                        ->orWhere('employee_name', 'like', "%{$auditSearch}%")
                        ->orWhere('subject_label', 'like', "%{$auditSearch}%");
                });
            })
            ->when(
                array_key_exists($auditEvent, PayrollAuditService::EVENT_LABELS),
                fn (Builder $query): Builder => $query->where('event_type', $auditEvent),
            )
            ->when($auditFrom !== '', fn (Builder $query): Builder => $query->whereDate('occurred_at', '>=', $auditFrom))
            ->when($auditTo !== '', fn (Builder $query): Builder => $query->whereDate('occurred_at', '<=', $auditTo))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PayrollAuditLog $auditLog): array => [
                'id' => $auditLog->id,
                'actor_name' => $auditLog->actor_name,
                'actor_role' => $auditLog->actor_role,
                'event_type' => $auditLog->event_type,
                'event_label' => PayrollAuditService::EVENT_LABELS[$auditLog->event_type]
                    ?? $auditLog->event_type,
                'subject_type' => $auditLog->subject_type,
                'subject_id' => $auditLog->subject_id,
                'subject_label' => $auditLog->subject_label,
                'employee_nip' => $auditLog->employee_nip,
                'employee_name' => $auditLog->employee_name,
                'changes' => $auditLog->changes ?? [],
                'metadata' => $auditLog->metadata ?? [],
                'ip_address' => $auditLog->ip_address,
                'occurred_at' => $auditLog->occurred_at?->toISOString(),
            ]);

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
            'payrollAuditLogs' => $auditLogs,
            'payrollAuditFilters' => [
                'search' => $auditSearch,
                'event' => $auditEvent,
                'from' => $auditFrom,
                'to' => $auditTo,
            ],
            'payrollAuditEventOptions' => collect(PayrollAuditService::EVENT_LABELS)
                ->map(fn (string $label, string $value): array => compact('value', 'label'))
                ->values()
                ->all(),
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

    private function validDate(mixed $date): string
    {
        if (! is_string($date) || trim($date) === '') {
            return '';
        }

        try {
            return CarbonImmutable::parse($date)->toDateString();
        } catch (\Throwable) {
            return '';
        }
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
