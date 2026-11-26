<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceImport;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Support\Attendance\AttendanceAnomalyScanner;
use App\Support\Attendance\AttendanceRecordEditor;
use App\Support\Attendance\NationalHolidayResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AttendanceController extends Controller
{
    private const SORT_COLUMNS = [
        'employee.nip' => 'employees.nip',
        'employee.name' => 'employees.nama',
        'status' => 'attendance_records.status',
        'schedule_profile' => 'attendance_records.schedule_profile',
        'first_in' => 'attendance_records.first_in',
        'first_out' => 'attendance_records.first_out',
        'second_in' => 'attendance_records.second_in',
        'second_out' => 'attendance_records.second_out',
        'overtime_in' => 'attendance_records.overtime_in',
        'overtime_out' => 'attendance_records.overtime_out',
        'effective_punch_count' => 'attendance_records.effective_punch_count',
        'late_minutes' => 'attendance_records.late_minutes',
        'worked_hours' => 'attendance_records.worked_hours',
        'overtime_hours' => 'attendance_records.overtime_hours',
        'holiday_worked_hours' => 'attendance_records.holiday_worked_hours',
        'holiday_overtime_hours' => 'attendance_records.holiday_overtime_hours',
        'notes' => 'attendance_records.notes',
        'has_missing_punch' => 'attendance_records.has_missing_punch',
        'monthly_review_count' => 'monthly_review_count',
    ];

    public function index(
        Request $request,
        NationalHolidayResolver $holidayResolver,
    ): Response {
        Gate::authorize('manage-payroll');

        $latestAttendanceDate = AttendanceRecord::query()->max('attendance_date')
            ?? now()->toDateString();
        $attendanceDate = $this->validAttendanceDate(
            (string) $request->query('date', $latestAttendanceDate),
        );
        $status = (string) $request->query('status', '');
        $companyId = (string) $request->query('company_id', '');
        $directorateId = (string) $request->query('directorate_id', '');
        $departmentId = (string) $request->query('department_id', '');
        $jabatanId = (string) $request->query('jabatan_id', '');
        $search = trim((string) $request->query('search', ''));
        $lateOnly = $request->boolean('late_only');
        $sort = (string) $request->query('sort', 'has_missing_punch');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';
        $sort = array_key_exists($sort, self::SORT_COLUMNS) ? $sort : 'has_missing_punch';
        $requestedPerPage = $request->integer('perPage', 5);
        $perPage = in_array($requestedPerPage, [5, 10, 15, 25, 50, 100], true)
            ? $requestedPerPage
            : 5;
        $attendanceDateValue = CarbonImmutable::parse($attendanceDate);
        $nationalHoliday = $holidayResolver->findFor($attendanceDateValue);

        $records = $this->filteredQuery(
            $attendanceDate,
            $status,
            $companyId,
            $directorateId,
            $departmentId,
            $jabatanId,
            $search,
            $lateOnly,
            $sort,
            $direction,
        )
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AttendanceRecord $record): array => $this->attendanceRow($record));

        $recentImports = AttendanceImport::query()
            ->with('importedBy:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (AttendanceImport $import): array => [
                'id' => $import->id,
                'filename' => $import->original_filename,
                'attendance_date' => $import->attendance_date->toDateString(),
                'imported_at' => $import->created_at?->toISOString(),
                'imported_by' => $import->importedBy?->name,
                'processed_employee_count' => $import->processed_employee_count,
                'skipped_without_punch_count' => $import->skipped_without_punch_count,
                'unmatched_employee_count' => $import->unmatched_employee_count,
                'missing_punch_count' => $import->missing_punch_count,
                'late_count' => $import->late_count,
                'unmatched_user_ids' => array_slice($import->unmatched_user_ids ?? [], 0, 10),
            ]);
        $directorateOptions = $this->directorateOptions($companyId);
        $departmentOptions = $this->departmentOptions($companyId, $directorateId);
        $jabatanOptions = $this->jabatanOptions($companyId, $directorateId, $departmentId);

        return Inertia::render('Attendance/Index', [
            'records' => $records,
            'recentImports' => $recentImports,
            'filters' => [
                'date' => $attendanceDate,
                'status' => $status,
                'companyId' => $companyId,
                'directorateId' => $directorateId,
                'departmentId' => $departmentId,
                'jabatanId' => $jabatanId,
                'search' => $search,
                'perPage' => $perPage,
                'lateOnly' => $lateOnly,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'options' => [
                'statuses' => collect(AttendanceRecord::STATUSES)
                    ->map(fn (string $label, string $value): array => compact('value', 'label'))
                    ->values()
                    ->all(),
                'companies' => Company::query()
                    ->whereNotNull('name')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Company $company): array => [
                        'value' => $company->id,
                        'label' => $company->name,
                    ])
                    ->all(),
                'directorates' => $directorateOptions,
                'departments' => $departmentOptions,
                'jabatans' => $jabatanOptions,
            ],
            'dateInfo' => [
                'isSunday' => $attendanceDateValue->isSunday(),
                'isHoliday' => $nationalHoliday !== null,
                'holidayName' => $nationalHoliday?->name,
            ],
            'stats' => [
                'present' => $this->dayQuery($attendanceDate)->whereIn('status', ['present', 'remote'])->count(),
                'late' => $this->dayQuery($attendanceDate)->where('status', 'late')->count(),
                'leave' => $this->dayQuery($attendanceDate)->whereIn('status', ['leave', 'sick'])->count(),
                'overtimeHours' => round(
                    (float) $this->dayQuery($attendanceDate)->sum('overtime_hours')
                    + (float) $this->dayQuery($attendanceDate)->sum('holiday_overtime_hours'),
                    1,
                ),
                'incomplete' => $this->dayQuery($attendanceDate)->where('has_missing_punch', true)->count(),
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

    public function scanAnomalies(
        Request $request,
        AttendanceAnomalyScanner $scanner,
    ): JsonResponse {
        Gate::authorize('manage-payroll');

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'short_work_minutes' => ['nullable', 'integer', 'between:0,1440'],
            'long_work_minutes' => ['nullable', 'integer', 'between:0,1440'],
            'overtime_threshold_hours' => ['nullable', 'numeric', 'between:0,24'],
        ]);
        $startsOn = CarbonImmutable::parse($validated['start_date'])->startOfDay();
        $endsOn = CarbonImmutable::parse($validated['end_date'])->startOfDay();
        $rangeDays = (int) $startsOn->diffInDays($endsOn) + 1;
        $maxRangeDays = (int) config('attendance.anomaly_scan.max_range_days', 31);

        if ($rangeDays > $maxRangeDays) {
            throw ValidationException::withMessages([
                'end_date' => "Rentang scan maksimal {$maxRangeDays} hari.",
            ]);
        }

        return response()->json($scanner->scan($startsOn, $endsOn, [
            'short_work_minutes' => (int) (
                $validated['short_work_minutes']
                ?? config('attendance.anomaly_scan.short_work_minutes', 60)
            ),
            'long_work_minutes' => (int) (
                $validated['long_work_minutes']
                ?? config('attendance.anomaly_scan.long_work_minutes', 120)
            ),
            'overtime_threshold_hours' => (float) (
                $validated['overtime_threshold_hours']
                ?? config('attendance.anomaly_scan.overtime_threshold_hours', 4)
            ),
        ]));
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

    public function destroyReview(
        Request $request,
        AttendanceRecord $attendance,
    ): RedirectResponse {
        Gate::authorize('manage-payroll');

        if (! $attendance->requires_review) {
            return back()->with('toast', [
                'type' => 'info',
                'title' => 'Review sudah dihapus',
                'message' => 'Attendance ini tidak memiliki status review aktif.',
            ]);
        }

        $attendance->loadMissing('karyawan:id,nama');
        $attendance->update([
            'requires_review' => false,
            'review_resolved_at' => now(),
            'review_resolved_by' => $request->user()->id,
            'review_resolution_note' => 'Review dihapus manual oleh admin.',
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Review dihapus',
            'message' => "Review attendance {$attendance->karyawan->nama} berhasil dihapus tanpa mengubah punch.",
        ]);
    }

    public function destroyDay(Request $request): RedirectResponse
    {
        Gate::authorize('manage-payroll');

        $validated = $request->validate([
            'attendance_date' => ['required', 'date'],
        ]);
        $deletedCount = $this->dayQuery($validated['attendance_date'])->delete();

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Attendance harian dihapus',
            'message' => $deletedCount.' catatan attendance berhasil dihapus.',
        ]);
    }

    public function updateInline(
        Request $request,
        AttendanceRecordEditor $recordEditor,
    ): RedirectResponse {
        Gate::authorize('manage-payroll');

        $validated = $request->validate([
            'karyawan_id' => ['required', 'integer', 'exists:karyawans,id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_keys(AttendanceRecord::STATUSES))],
            'first_in' => ['nullable', 'date_format:H:i'],
            'first_out' => ['nullable', 'date_format:H:i'],
            'second_in' => ['nullable', 'date_format:H:i'],
            'second_out' => ['nullable', 'date_format:H:i'],
            'overtime_in' => ['nullable', 'date_format:H:i'],
            'overtime_out' => ['nullable', 'date_format:H:i'],
        ]);
        $employee = Karyawan::query()
            ->with([
                'company:id,name',
                'directorate:id,name',
                'department:id,name',
                'jabatan:id,name',
            ])
            ->findOrFail($validated['karyawan_id']);
        $existingRecord = AttendanceRecord::query()
            ->where('karyawan_id', $employee->id)
            ->whereDate('attendance_date', $validated['attendance_date'])
            ->first();
        $recordEditor->save(
            $employee,
            CarbonImmutable::parse($validated['attendance_date']),
            $validated['status'],
            $validated,
            $request->user()->id,
            $existingRecord,
        );

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Attendance diperbarui',
            'message' => "Attendance {$employee->nama} berhasil dihitung ulang.",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceRow(AttendanceRecord $record): array
    {
        return [
            'id' => $record->id,
            'has_attendance' => true,
            'attendance_date' => $record->attendance_date->toDateString(),
            'status' => $record->status,
            'status_label' => AttendanceRecord::STATUSES[$record->status] ?? $record->status,
            'first_in' => $this->shortTime($record->first_in),
            'first_out' => $this->shortTime($record->first_out),
            'first_out_day_offset' => $record->first_out_day_offset,
            'second_in' => $this->shortTime($record->second_in),
            'second_out' => $this->shortTime($record->second_out),
            'overtime_in' => $this->shortTime($record->overtime_in),
            'overtime_out' => $this->shortTime($record->overtime_out),
            'second_in_day_offset' => $record->second_in_day_offset,
            'second_out_day_offset' => $record->second_out_day_offset,
            'overtime_in_day_offset' => $record->overtime_in_day_offset,
            'overtime_out_day_offset' => $record->overtime_out_day_offset,
            'last_out' => $this->shortTime(
                $record->overtime_out ?? $record->second_out ?? $record->first_out,
            ),
            'last_out_day_offset' => $record->last_out_day_offset,
            'clock_slots' => $this->clockSlots($record),
            'worked_hours' => (float) $record->worked_hours,
            'overtime_hours' => (float) $record->overtime_hours,
            'holiday_worked_hours' => (float) $record->holiday_worked_hours,
            'holiday_overtime_hours' => (float) $record->holiday_overtime_hours,
            'is_holiday_pay' => $record->is_holiday_pay,
            'calendar_override_id' => $record->calendar_override_id,
            'calendar_override_name' => $record->calendarOverride?->name,
            'shift' => $record->shift,
            'shift_label' => match ($record->shift) {
                'day' => 'Shift pagi',
                'night' => 'Shift malam',
                default => null,
            },
            'schedule_profile' => $record->schedule_profile,
            'schedule_label' => match ($record->schedule_profile) {
                'general' => 'Produksi / Umum',
                'security' => 'Security',
                'qc_aging' => 'QC Aging',
                default => null,
            },
            'expected_check_in' => $this->shortTime($record->expected_check_in),
            'expected_check_out' => $this->shortTime($record->expected_check_out),
            'raw_punch_count' => $record->raw_punch_count,
            'effective_punch_count' => $record->effective_punch_count,
            'duplicate_punch_count' => $record->duplicate_punch_count,
            'ignored_punch_count' => $record->ignored_punch_count,
            'late_minutes' => $record->late_minutes,
            'late_penalty_hours' => $record->late_penalty_hours,
            'early_leave_minutes' => $record->early_leave_minutes,
            'has_missing_punch' => $record->has_missing_punch,
            'has_duplicate_punch' => $record->has_duplicate_punch,
            'requires_review' => $record->requires_review,
            'comparison_notes' => $record->comparison_notes,
            'review_resolved_at' => $record->review_resolved_at?->toISOString(),
            'review_resolved_by' => $record->reviewResolvedBy?->name,
            'review_resolution_note' => $record->review_resolution_note,
            'monthly_review_count' => (int) ($record->monthly_review_count ?? 0),
            'review_penalty_hours' => max(
                0,
                (int) ($record->monthly_review_count ?? 0) - 3,
            ),
            'notes' => $record->notes,
            'employee' => [
                'id' => $record->karyawan->id,
                'name' => $record->karyawan->nama,
                'nip' => $record->karyawan->nip,
                'company' => $record->karyawan->company?->name,
                'directorate' => $record->karyawan->directorate?->name,
                'department' => $record->karyawan->department?->name,
                'position' => $record->karyawan->jabatan?->name,
            ],
        ];
    }

    private function filteredQuery(
        string $attendanceDate,
        string $status,
        string $companyId,
        string $directorateId,
        string $departmentId,
        string $jabatanId,
        string $search,
        bool $lateOnly,
        string $sort,
        string $direction,
    ): Builder {
        $monthStart = CarbonImmutable::parse($attendanceDate)->startOfMonth()->toDateString();
        $monthEnd = CarbonImmutable::parse($attendanceDate)->endOfMonth()->toDateString();
        $monthlyReviewCount = AttendanceRecord::query()
            ->from('attendance_records as monthly_reviews')
            ->selectRaw('COUNT(*)')
            ->whereColumn('monthly_reviews.karyawan_id', 'attendance_records.karyawan_id')
            ->where('monthly_reviews.requires_review', true)
            ->whereBetween('monthly_reviews.attendance_date', [$monthStart, $monthEnd]);

        return $this->dayQuery($attendanceDate)
            ->leftJoin('karyawans as employees', 'employees.id', '=', 'attendance_records.karyawan_id')
            ->select('attendance_records.*')
            ->selectSub($monthlyReviewCount, 'monthly_review_count')
            ->with([
                'karyawan:id,nip,nama,company_id,directorate_id,department_id,jabatan_id',
                'karyawan.company:id,name',
                'karyawan.directorate:id,name',
                'karyawan.department:id,name',
                'karyawan.jabatan:id,name',
                'calendarOverride:id,name',
                'reviewResolvedBy:id,name',
            ])
            ->when($status !== '', fn (Builder $query) => $query->where('attendance_records.status', $status))
            ->when($lateOnly, fn (Builder $query) => $query->where('attendance_records.late_minutes', '>', 3))
            ->when($companyId !== '', fn (Builder $query) => $query->where('employees.company_id', $companyId))
            ->when($directorateId !== '', fn (Builder $query) => $query->where('employees.directorate_id', $directorateId))
            ->when($departmentId !== '', fn (Builder $query) => $query->where('employees.department_id', $departmentId))
            ->when($jabatanId !== '', fn (Builder $query) => $query->where('employees.jabatan_id', $jabatanId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('employees.nama', 'like', "%{$search}%")
                        ->orWhere('employees.nip', 'like', "%{$search}%");
                });
            })
            ->orderBy(self::SORT_COLUMNS[$sort], $direction)
            ->orderBy('attendance_records.id');
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function directorateOptions(string $companyId): array
    {
        $employeeDirectorates = Karyawan::query()
            ->select('directorate_id')
            ->whereNotNull('directorate_id')
            ->when($companyId !== '', fn (Builder $query) => $query->where('company_id', $companyId));

        return Directorate::query()
            ->whereIn('id', $employeeDirectorates)
            ->whereNotNull('name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Directorate $directorate): array => [
                'value' => $directorate->id,
                'label' => $directorate->name,
            ])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function departmentOptions(string $companyId, string $directorateId): array
    {
        $employeeDepartments = Karyawan::query()
            ->select('department_id')
            ->whereNotNull('department_id')
            ->when($companyId !== '', fn (Builder $query) => $query->where('company_id', $companyId))
            ->when($directorateId !== '', fn (Builder $query) => $query->where('directorate_id', $directorateId));

        return Department::query()
            ->whereIn('id', $employeeDepartments)
            ->whereNotNull('name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department): array => [
                'value' => $department->id,
                'label' => $department->name,
            ])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function jabatanOptions(
        string $companyId,
        string $directorateId,
        string $departmentId,
    ): array {
        $employeePositions = Karyawan::query()
            ->select('jabatan_id')
            ->whereNotNull('jabatan_id')
            ->when($companyId !== '', fn (Builder $query) => $query->where('company_id', $companyId))
            ->when($directorateId !== '', fn (Builder $query) => $query->where('directorate_id', $directorateId))
            ->when($departmentId !== '', fn (Builder $query) => $query->where('department_id', $departmentId));

        return Jabatan::query()
            ->whereIn('id', $employeePositions)
            ->whereNotNull('name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Jabatan $jabatan): array => [
                'value' => $jabatan->id,
                'label' => $jabatan->name,
            ])
            ->all();
    }

    private function dayQuery(string $attendanceDate): Builder
    {
        return AttendanceRecord::query()->where('attendance_date', $attendanceDate);
    }

    private function validAttendanceDate(string $date): string
    {
        try {
            return CarbonImmutable::parse($date)->toDateString();
        } catch (Throwable) {
            return now()->toDateString();
        }
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
            'first_in' => ['nullable', 'date_format:H:i'],
            'first_out' => ['nullable', 'date_format:H:i'],
            'overtime_hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function shortTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    /**
     * @return list<array{label: string, time: string, day_offset: int}>
     */
    private function clockSlots(AttendanceRecord $record): array
    {
        $slots = [
            ['label' => 'Masuk 1', 'time' => $this->shortTime($record->first_in), 'day_offset' => 0],
            ['label' => 'Keluar 1', 'time' => $this->shortTime($record->first_out), 'day_offset' => $record->first_out_day_offset],
            ['label' => 'Masuk 2', 'time' => $this->shortTime($record->second_in), 'day_offset' => $record->second_in_day_offset],
            ['label' => 'Keluar 2', 'time' => $this->shortTime($record->second_out), 'day_offset' => $record->second_out_day_offset],
            ['label' => 'Masuk OT', 'time' => $this->shortTime($record->overtime_in), 'day_offset' => $record->overtime_in_day_offset],
            ['label' => 'Keluar OT', 'time' => $this->shortTime($record->overtime_out), 'day_offset' => $record->overtime_out_day_offset],
        ];

        return array_values(array_filter(
            $slots,
            fn (array $slot): bool => $slot['time'] !== null,
        ));
    }
}
