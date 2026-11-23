<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KaryawanStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Karyawan;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $canManagePayroll = $request->user()->can('manage-payroll');

        $stats = [
            'employees' => 0,
            'activeEmployees' => 0,
            'departments' => 0,
            'directorates' => 0,
            'payrollTotal' => 0,
            'attendanceToday' => 0,
            'attendanceLateToday' => 0,
            'payrollPeriods' => 0,
            'latestPayrollNet' => 0,
        ];
        $recentPayrollPeriods = [];

        if ($canManagePayroll) {
            $stats = [
                'employees' => Karyawan::query()->count(),
                'activeEmployees' => Karyawan::query()
                    ->whereIn('status_karyawan', KaryawanStatus::valuesForFilter('active'))
                    ->count(),
                'departments' => Department::query()->count(),
                'directorates' => Directorate::query()->count(),
                'payrollTotal' => (int) Karyawan::query()
                    ->whereIn('status_karyawan', KaryawanStatus::valuesForFilter('active'))
                    ->sum('gaji_pokok'),
                'attendanceToday' => AttendanceRecord::query()
                    ->whereDate('attendance_date', today())
                    ->whereIn('status', ['present', 'late', 'remote'])
                    ->count(),
                'attendanceLateToday' => AttendanceRecord::query()
                    ->whereDate('attendance_date', today())
                    ->where('status', 'late')
                    ->count(),
                'payrollPeriods' => PayrollPeriod::query()->count(),
                'latestPayrollNet' => (int) (PayrollPeriod::query()
                    ->whereIn('status', ['processed', 'paid'])
                    ->latest('pay_date')
                    ->value('total_net') ?? 0),
            ];
            $recentPayrollPeriods = PayrollPeriod::query()
                ->latest('start_date')
                ->limit(5)
                ->get(['id', 'name', 'status', 'pay_date', 'total_net'])
                ->map(fn (PayrollPeriod $period): array => [
                    'id' => $period->id,
                    'name' => $period->name,
                    'status' => $period->status,
                    'pay_date' => $period->pay_date->format('d/m/Y'),
                    'total_net' => $period->total_net,
                ])
                ->all();
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentPayrollPeriods' => $recentPayrollPeriods,
            'today' => now()->locale('id')->translatedFormat('d M Y'),
            'permissions' => [
                'superAdmin' => $request->user()->can('access-super-admin-dashboard'),
                'managePayroll' => $canManagePayroll,
                'developer' => $request->user()->can('access-developer-settings'),
            ],
        ]);
    }
}
