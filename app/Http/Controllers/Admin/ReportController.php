<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KaryawanStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Karyawan;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('manage-payroll');

        $activeStatuses = KaryawanStatus::valuesForFilter('active');
        $activeEmployees = Karyawan::query()->whereIn('status_karyawan', $activeStatuses);
        $attendanceQuery = AttendanceRecord::query()
            ->where('attendance_date', '>=', now()->subDays(29)->toDateString());

        $latestPayroll = PayrollPeriod::query()
            ->whereIn('status', ['processed', 'paid'])
            ->latest('pay_date')
            ->first();

        return Inertia::render('Reports/Index', [
            'summary' => [
                'headcount' => Karyawan::query()->count(),
                'activeHeadcount' => (clone $activeEmployees)->count(),
                'attendanceRecords' => (clone $attendanceQuery)->count(),
                'attendanceRate' => $this->attendanceRate(clone $activeEmployees, clone $attendanceQuery),
                'payrollNet' => (int) ($latestPayroll?->total_net ?? 0),
                'payrollPeriod' => $latestPayroll?->name,
                'averageSalary' => (int) round((float) (clone $activeEmployees)->avg('gaji_pokok')),
            ],
            'statusBreakdown' => Karyawan::query()
                ->selectRaw('status_karyawan, COUNT(*) as total')
                ->groupBy('status_karyawan')
                ->orderByDesc('total')
                ->get()
                ->map(function (Karyawan $row): array {
                    $status = KaryawanStatus::tryFrom((string) $row->getRawOriginal('status_karyawan'));

                    return [
                        'label' => $status?->displayLabel() ?? (string) $row->getRawOriginal('status_karyawan'),
                        'value' => (int) $row->getAttribute('total'),
                    ];
                })
                ->all(),
            'departmentBreakdown' => Department::query()
                ->withCount(['karyawans as employees_count' => fn ($query) => $query->whereIn('status_karyawan', $activeStatuses)])
                ->withSum(['karyawans as payroll_total' => fn ($query) => $query->whereIn('status_karyawan', $activeStatuses)], 'gaji_pokok')
                ->orderByDesc('employees_count')
                ->orderBy('name')
                ->limit(10)
                ->get()
                ->map(fn (Department $department): array => [
                    'id' => $department->id,
                    'label' => $department->name,
                    'employees' => $department->employees_count,
                    'payroll' => (int) ($department->payroll_total ?? 0),
                ])
                ->all(),
            'directorateBreakdown' => Directorate::query()
                ->withCount(['karyawans as employees_count' => fn ($query) => $query->whereIn('status_karyawan', $activeStatuses)])
                ->orderByDesc('employees_count')
                ->orderBy('name')
                ->limit(8)
                ->get()
                ->map(fn (Directorate $directorate): array => [
                    'id' => $directorate->id,
                    'label' => $directorate->name,
                    'employees' => $directorate->employees_count,
                ])
                ->all(),
            'attendanceBreakdown' => (clone $attendanceQuery)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->get()
                ->map(fn (AttendanceRecord $row): array => [
                    'label' => AttendanceRecord::STATUSES[$row->getRawOriginal('status')] ?? $row->getRawOriginal('status'),
                    'value' => (int) $row->getAttribute('total'),
                ])
                ->all(),
            'period' => [
                'from' => now()->subDays(29)->toDateString(),
                'to' => now()->toDateString(),
            ],
        ]);
    }

    private function attendanceRate(Builder $employees, Builder $attendance): float
    {
        $activeCount = (clone $employees)->count();

        if ($activeCount === 0) {
            return 0;
        }

        $expected = $activeCount * 30;
        $present = $attendance->whereIn('status', ['present', 'late', 'remote'])->count();

        return round(min(100, ($present / $expected) * 100), 1);
    }
}
