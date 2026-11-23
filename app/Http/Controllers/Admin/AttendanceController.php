<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-payroll');

        $dateFrom = (string) $request->query('date_from', now()->startOfMonth()->toDateString());
        $dateTo = (string) $request->query('date_to', now()->toDateString());
        $status = (string) $request->query('status', '');
        $departmentId = (string) $request->query('department_id', '');
        $search = trim((string) $request->query('search', ''));

        $records = $this->filteredQuery(
            $dateFrom,
            $dateTo,
            $status,
            $departmentId,
            $search,
        )
            ->paginate(15)
            ->withQueryString()
            ->through(fn (AttendanceRecord $record): array => [
                'id' => $record->id,
                'attendance_date' => $record->attendance_date->toDateString(),
                'status' => $record->status,
                'status_label' => AttendanceRecord::STATUSES[$record->status] ?? $record->status,
                'check_in' => $record->check_in ? substr($record->check_in, 0, 5) : null,
                'check_out' => $record->check_out ? substr($record->check_out, 0, 5) : null,
                'overtime_minutes' => $record->overtime_minutes,
                'notes' => $record->notes,
                'employee' => [
                    'id' => $record->karyawan->id,
                    'name' => $record->karyawan->nama,
                    'nip' => $record->karyawan->nip,
                    'department' => $record->karyawan->department?->name,
                ],
            ]);

        return Inertia::render('Attendance/Index', [
            'records' => $records,
            'filters' => [
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'status' => $status,
                'departmentId' => $departmentId,
                'search' => $search,
            ],
            'options' => [
                'statuses' => collect(AttendanceRecord::STATUSES)
                    ->map(fn (string $label, string $value): array => compact('value', 'label'))
                    ->values()
                    ->all(),
                'departments' => Department::query()
                    ->whereNotNull('name')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Department $department): array => [
                        'value' => $department->id,
                        'label' => $department->name,
                    ])
                    ->all(),
            ],
            'stats' => [
                'present' => $this->rangeQuery($dateFrom, $dateTo)->whereIn('status', ['present', 'remote'])->count(),
                'late' => $this->rangeQuery($dateFrom, $dateTo)->where('status', 'late')->count(),
                'leave' => $this->rangeQuery($dateFrom, $dateTo)->whereIn('status', ['leave', 'sick'])->count(),
                'overtimeHours' => round((int) $this->rangeQuery($dateFrom, $dateTo)->sum('overtime_minutes') / 60, 1),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-payroll');

        $validated = $this->validateRecord($request);
        AttendanceRecord::query()->create([
            ...$validated,
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Absensi disimpan',
            'message' => 'Catatan absensi berhasil ditambahkan.',
        ]);
    }

    public function update(Request $request, AttendanceRecord $attendance): RedirectResponse
    {
        Gate::authorize('manage-payroll');

        $validated = $this->validateRecord($request, $attendance);
        $attendance->update([
            ...$validated,
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Absensi diperbarui',
            'message' => 'Perubahan absensi berhasil disimpan.',
        ]);
    }

    public function destroy(AttendanceRecord $attendance): RedirectResponse
    {
        Gate::authorize('manage-payroll');

        $attendance->delete();

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Absensi dihapus',
            'message' => 'Catatan absensi berhasil dihapus.',
        ]);
    }

    private function filteredQuery(
        string $dateFrom,
        string $dateTo,
        string $status,
        string $departmentId,
        string $search,
    ): Builder {
        return $this->rangeQuery($dateFrom, $dateTo)
            ->with([
                'karyawan:id,nip,nama,department_id',
                'karyawan.department:id,name',
            ])
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($departmentId !== '', function (Builder $query) use ($departmentId): void {
                $query->whereHas('karyawan', fn (Builder $query) => $query->where('department_id', $departmentId));
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('karyawan', function (Builder $query) use ($search): void {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('attendance_date')
            ->orderByDesc('id');
    }

    private function rangeQuery(string $dateFrom, string $dateTo): Builder
    {
        return AttendanceRecord::query()->whereBetween('attendance_date', [$dateFrom, $dateTo]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRecord(Request $request, ?AttendanceRecord $attendance = null): array
    {
        return $request->validate([
            'karyawan_id' => [
                'required',
                'integer',
                'exists:karyawans,id',
                Rule::unique('attendance_records')
                    ->where('attendance_date', $request->input('attendance_date'))
                    ->ignore($attendance?->id),
            ],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_keys(AttendanceRecord::STATUSES))],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after_or_equal:check_in'],
            'overtime_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
