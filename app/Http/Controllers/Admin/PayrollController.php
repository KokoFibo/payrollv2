<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KaryawanStatus;
use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Support\Payroll\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-payroll');

        $periods = PayrollPeriod::query()
            ->withCount('items')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (PayrollPeriod $period): array => [
                'id' => $period->id,
                'name' => $period->name,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'pay_date' => $period->pay_date->toDateString(),
                'status' => $period->status,
                'status_label' => PayrollPeriod::STATUSES[$period->status] ?? $period->status,
                'total_gross' => $period->total_gross,
                'total_deductions' => $period->total_deductions,
                'total_net' => $period->total_net,
                'items_count' => $period->items_count,
            ]);

        return Inertia::render('Payroll/Index', [
            'periods' => $periods,
            'stats' => [
                'activeEmployees' => Karyawan::query()
                    ->whereIn('status_karyawan', KaryawanStatus::valuesForFilter('active'))
                    ->count(),
                'draftPeriods' => PayrollPeriod::query()->where('status', 'draft')->count(),
                'processedPeriods' => PayrollPeriod::query()->where('status', 'processed')->count(),
                'paidThisYear' => PayrollPeriod::query()
                    ->where('status', 'paid')
                    ->whereYear('pay_date', now()->year)
                    ->sum('total_net'),
            ],
        ]);
    }

    public function store(Request $request, PayrollService $payrollService): RedirectResponse
    {
        Gate::authorize('manage-payroll');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:payroll_periods,name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pay_date' => ['required', 'date', 'after_or_equal:end_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $period = $payrollService->createPeriod($validated, $request->user());

        return redirect()
            ->route('admin.payroll.show', $period)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Periode payroll dibuat',
                'message' => "{$period->items()->count()} karyawan aktif masuk ke periode {$period->name}.",
            ]);
    }

    public function show(Request $request, PayrollPeriod $payrollPeriod): Response
    {
        Gate::authorize('manage-payroll');

        $items = $payrollPeriod->items()
            ->with([
                'karyawan:id,nip,nama,department_id,jabatan_id',
                'karyawan.department:id,name',
                'karyawan.jabatan:id,name',
            ])
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PayrollItem $item): array => [
                'id' => $item->id,
                'employee' => [
                    'name' => $item->karyawan->nama,
                    'nip' => $item->karyawan->nip,
                    'department' => $item->karyawan->department?->name,
                    'position' => $item->karyawan->jabatan?->name,
                ],
                'base_salary' => $item->base_salary,
                'overtime_salary' => $item->overtime_salary,
                'allowances' => $item->allowances,
                'bonus' => $item->bonus,
                'gross_salary' => $item->gross_salary,
                'total_deductions' => $item->total_deductions,
                'net_salary' => $item->net_salary,
            ]);

        return Inertia::render('Payroll/Show', [
            'period' => [
                'id' => $payrollPeriod->id,
                'name' => $payrollPeriod->name,
                'start_date' => $payrollPeriod->start_date->toDateString(),
                'end_date' => $payrollPeriod->end_date->toDateString(),
                'pay_date' => $payrollPeriod->pay_date->toDateString(),
                'status' => $payrollPeriod->status,
                'status_label' => PayrollPeriod::STATUSES[$payrollPeriod->status] ?? $payrollPeriod->status,
                'total_gross' => $payrollPeriod->total_gross,
                'total_deductions' => $payrollPeriod->total_deductions,
                'total_net' => $payrollPeriod->total_net,
                'notes' => $payrollPeriod->notes,
            ],
            'items' => $items,
        ]);
    }

    public function process(Request $request, PayrollPeriod $payrollPeriod, PayrollService $payrollService): RedirectResponse
    {
        Gate::authorize('manage-payroll');

        try {
            $payrollService->markProcessed($payrollPeriod, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Payroll diproses',
            'message' => 'Periode payroll siap dibayarkan.',
        ]);
    }

    public function markPaid(PayrollPeriod $payrollPeriod, PayrollService $payrollService): RedirectResponse
    {
        Gate::authorize('manage-payroll');

        try {
            $payrollService->markPaid($payrollPeriod);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Payroll dibayar',
            'message' => 'Periode payroll telah ditandai sebagai dibayar.',
        ]);
    }

    public function destroy(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        Gate::authorize('manage-payroll');

        if ($payrollPeriod->status !== 'draft') {
            return back()->withErrors(['status' => 'Hanya periode draft yang dapat dihapus.']);
        }

        $payrollPeriod->delete();

        return redirect()
            ->route('admin.payroll.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Periode dihapus',
                'message' => 'Periode payroll draft berhasil dihapus.',
            ]);
    }
}
