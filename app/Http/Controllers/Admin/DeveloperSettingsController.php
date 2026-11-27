<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KaryawanStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\CalendarOverride;
use App\Models\Company;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\NationalHoliday;
use App\Models\SpecialWorkSchedule;
use App\Support\AdminSalaryVisibility;
use App\Support\Attendance\AttendanceBulkCorrectionService;
use App\Support\Attendance\AttendanceCleanupService;
use App\Support\Attendance\AttendancePolicySettings;
use App\Support\MasterDataResources;
use App\Support\Payroll\KaryawanMigrationService;
use App\Support\PayrollAuditService;
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
        AttendanceCleanupService $attendanceCleanupService,
        AdminSalaryVisibility $salaryVisibility,
        AttendancePolicySettings $attendancePolicySettings,
    ): Response {
        Gate::authorize('access-developer-settings');

        $resource = (string) $request->query('resource', 'companies');
        $initialSection = (string) $request->query('section', 'master');

        if (! in_array($resource, self::RESOURCE_OPTIONS, true)) {
            $resource = 'companies';
        }

        if (! in_array($initialSection, ['work-schedules', 'attendance-bulk'], true)) {
            $initialSection = 'master';
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

        $sourceDatabase = trim((string) config('database.source_database', ''));
        $preview = [
            'data' => null,
            'error' => null,
        ];

        if ($sourceDatabase === '') {
            $preview['error'] = 'DB_SOURCE_DATABASE belum diisi di file .env.';
        } else {
            try {
                $preview['data'] = $migrationService->preview();
            } catch (Throwable $exception) {
                report($exception);
                $preview['error'] = 'Database sumber tidak dapat dibaca. Periksa nama database dan akses koneksi database.';
            }
        }

        $companyOptions = Company::query()
            ->whereNotNull('name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Company $company): array => [
                'value' => $company->id,
                'label' => $company->name,
            ])
            ->all();
        $directorateOptions = Directorate::query()
            ->whereNotNull('name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Directorate $directorate): array => [
                'value' => $directorate->id,
                'label' => $directorate->name,
            ])
            ->all();
        $departmentOptions = Department::query()
            ->whereNotNull('name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department): array => [
                'value' => $department->id,
                'label' => $department->name,
            ])
            ->all();
        $jabatanOptions = Jabatan::query()
            ->whereNotNull('name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Jabatan $jabatan): array => [
                'value' => $jabatan->id,
                'label' => $jabatan->name,
            ])
            ->all();
        $nationalHolidays = NationalHoliday::query()
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get()
            ->map(fn (NationalHoliday $holiday): array => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'starts_on' => $holiday->starts_on?->toDateString(),
                'ends_on' => ($holiday->ends_on ?? $holiday->starts_on)?->toDateString(),
                'total_days' => $holiday->total_days,
            ]);
        $specialWorkSchedules = SpecialWorkSchedule::query()
            ->with([
                'company:id,name',
                'directorate:id,name',
                'department:id,name',
            ])
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get()
            ->map(fn (SpecialWorkSchedule $schedule): array => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'company_id' => $schedule->company_id,
                'company_name' => $schedule->company?->name,
                'directorate_id' => $schedule->directorate_id,
                'directorate_name' => $schedule->directorate?->name,
                'department_id' => $schedule->department_id,
                'department_name' => $schedule->department?->name,
                'scope_label' => collect([
                    $schedule->company?->name,
                    $schedule->directorate?->name,
                    $schedule->department?->name,
                ])->filter()->implode(' / ') ?: 'Global',
                'starts_on' => $schedule->starts_on->toDateString(),
                'ends_on' => $schedule->ends_on->toDateString(),
                'schedule_profile' => $schedule->schedule_profile,
                'schedule_profile_label' => $schedule->schedule_profile
                    ? (SpecialWorkSchedule::PROFILES[$schedule->schedule_profile] ?? $schedule->schedule_profile)
                    : 'Semua profile',
                'day_type' => $schedule->day_type,
                'day_type_label' => $schedule->day_type
                    ? (SpecialWorkSchedule::DAY_TYPES[$schedule->day_type] ?? $schedule->day_type)
                    : 'Semua hari',
                'shift' => $schedule->shift,
                'shift_label' => $schedule->shift
                    ? (SpecialWorkSchedule::SHIFTS[$schedule->shift] ?? $schedule->shift)
                    : 'Semua shift',
                'check_in' => substr($schedule->check_in, 0, 5),
                'check_out' => substr($schedule->check_out, 0, 5),
                'check_out_day_offset' => $schedule->check_out_day_offset,
                'standard_work_hours' => (float) $schedule->standard_work_hours,
                'expected_punch_count' => $schedule->expected_punch_count,
                'notes' => $schedule->notes,
            ]);
        $calendarOverrides = CalendarOverride::query()
            ->with([
                'company:id,name',
                'directorate:id,name',
                'department:id,name',
            ])
            ->orderBy('calendar_date')
            ->orderBy('id')
            ->get()
            ->map(fn (CalendarOverride $override): array => [
                'id' => $override->id,
                'company_id' => $override->company_id,
                'company_name' => $override->company?->name,
                'directorate_id' => $override->directorate_id,
                'directorate_name' => $override->directorate?->name,
                'department_id' => $override->department_id,
                'department_name' => $override->department?->name,
                'scope_label' => collect([
                    $override->company?->name,
                    $override->directorate?->name,
                    $override->department?->name,
                ])->filter()->implode(' / '),
                'calendar_date' => $override->calendar_date->toDateString(),
                'name' => $override->name,
                'is_holiday_pay' => $override->is_holiday_pay,
                'schedule_day_type' => $override->schedule_day_type,
                'schedule_day_type_label' => $override->schedule_day_type
                    ? (CalendarOverride::DAY_TYPES[$override->schedule_day_type] ?? $override->schedule_day_type)
                    : null,
                'notes' => $override->notes,
            ]);

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
            'sourceDatabase' => $sourceDatabase,
            'migrationResult' => session('migration_result'),
            'masterCopyResult' => session('master_copy_result'),
            'attendanceCleanupMonths' => $attendanceCleanupService->monthOptions(),
            'attendanceCleanupResult' => session('attendance_cleanup_result'),
            'nationalHolidays' => $nationalHolidays,
            'calendarOverrides' => $calendarOverrides,
            'specialWorkSchedules' => $specialWorkSchedules,
            'scheduleGuide' => [
                'filename' => AttendanceScheduleGuideController::TemplateFilename,
                'downloadAvailable' => is_file(AttendanceScheduleGuideController::templatePath()),
            ],
            'attendanceBulkOptions' => [
                'companies' => $companyOptions,
                'directorates' => $directorateOptions,
                'departments' => $departmentOptions,
                'jabatans' => $jabatanOptions,
                'statuses' => collect(AttendanceRecord::STATUSES)
                    ->map(fn (string $label, string $value): array => compact('value', 'label'))
                    ->values()
                    ->all(),
                'shifts' => [
                    ['value' => 'day', 'label' => 'Shift pagi'],
                    ['value' => 'night', 'label' => 'Shift malam'],
                ],
                'profiles' => collect((array) config('attendance.profiles'))
                    ->map(fn (array $profile, string $value): array => [
                        'value' => $value,
                        'label' => (string) ($profile['label'] ?? $value),
                    ])
                    ->values()
                    ->all(),
                'conditions' => collect(AttendanceBulkCorrectionService::CONDITIONS)
                    ->map(fn (string $label, string $value): array => compact('value', 'label'))
                    ->values()
                    ->all(),
            ],
            'initialSection' => $initialSection,
            'workCalendarOptions' => [
                'companies' => $companyOptions,
                'profiles' => collect(SpecialWorkSchedule::PROFILES)
                    ->map(fn (string $label, string $value): array => compact('value', 'label'))
                    ->values()
                    ->all(),
                'dayTypes' => collect(SpecialWorkSchedule::DAY_TYPES)
                    ->map(fn (string $label, string $value): array => compact('value', 'label'))
                    ->values()
                    ->all(),
                'shifts' => collect(SpecialWorkSchedule::SHIFTS)
                    ->map(fn (string $label, string $value): array => compact('value', 'label'))
                    ->values()
                    ->all(),
            ],
            'adminSalaryLimit' => $salaryVisibility->limit(),
            'attendanceToleranceMinutes' => $attendancePolicySettings->toleranceMinutes(),
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
            'message' => 'Role Admin sekarang hanya dapat melihat gaji pokok dan gaji tetap sampai batas gaji yang baru.',
        ]);
    }

    public function updateAttendanceTolerance(
        Request $request,
        AttendancePolicySettings $attendancePolicySettings,
    ): RedirectResponse {
        Gate::authorize('access-developer-settings');

        $validated = $request->validate([
            'attendance_tolerance_minutes' => ['required', 'integer', 'between:0,120'],
        ]);

        $attendancePolicySettings->updateToleranceMinutes(
            $validated['attendance_tolerance_minutes'],
        );

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Toleransi attendance diperbarui',
            'message' => 'Toleransi keterlambatan punch masuk berhasil diubah.',
        ]);
    }

    public function copy(
        KaryawanMigrationService $migrationService,
        PayrollAuditService $payrollAuditService,
    ): RedirectResponse {
        Gate::authorize('access-developer-settings');

        try {
            $result = $migrationService->copy();
            $payrollAuditService->record(
                eventType: 'employee_migrated',
                subjectType: 'database_migration',
                subjectId: null,
                subjectLabel: 'Copy karyawan dari '.config('database.source_database'),
                changes: [],
                metadata: [
                    'statistics' => $result['statistics'],
                    'source_database' => config('database.source_database'),
                ],
            );

            return redirect()
                ->route('admin.developer-settings.index', ['resource' => 'companies'])
                ->with('migration_result', $result)
                ->with('toast', [
                    'type' => 'success',
                    'title' => 'Copy karyawan selesai',
                    'message' => $result['statistics']['deleted_rows']
                        .' data lama dihapus dan '
                        .$result['statistics']['inserted']
                        .' data karyawan source berhasil disalin.',
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast', [
                'type' => 'error',
                'title' => 'Copy karyawan gagal',
                'message' => trim((string) config('database.source_database', '')) === ''
                    ? 'DB_SOURCE_DATABASE belum diisi di file .env.'
                    : 'Transaksi dibatalkan. Periksa nama database dan akses koneksi database.',
            ]);
        }
    }

    public function copyMasterData(KaryawanMigrationService $migrationService): RedirectResponse
    {
        Gate::authorize('access-developer-settings');

        try {
            $result = $migrationService->copyMasterData();

            return redirect()
                ->route('admin.developer-settings.index', ['resource' => 'companies'])
                ->with('master_copy_result', $result)
                ->with('toast', [
                    'type' => 'success',
                    'title' => 'Copy master data selesai',
                    'message' => $result['totals']['deleted_rows']
                        .' data lama dihapus dan '
                        .$result['totals']['created_rows']
                        .' data source berhasil disalin.',
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast', [
                'type' => 'error',
                'title' => 'Copy master data gagal',
                'message' => trim((string) config('database.source_database', '')) === ''
                    ? 'DB_SOURCE_DATABASE belum diisi di file .env.'
                    : 'Transaksi dibatalkan. Periksa nama database dan akses koneksi database.',
            ]);
        }
    }
}
