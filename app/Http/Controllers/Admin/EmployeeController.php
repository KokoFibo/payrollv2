<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KaryawanStatus;
use App\Enums\PayrollMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveKaryawanRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Etnis;
use App\Models\Jabatan;
use App\Models\JobGrade;
use App\Models\Karyawan;
use App\Support\AdminSalaryVisibility;
use App\Support\KaryawanOptions;
use App\Support\Payroll\KaryawanExcelExporter;
use App\Support\Payroll\KaryawanExcelKhususExporter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeController extends Controller
{
    private const PER_PAGE_OPTIONS = [5, 10, 25, 50, 100];

    private const SORTABLE_FIELDS = [
        'nip',
        'nama',
        'status_karyawan',
        'company_id',
        'directorate_id',
        'department_id',
        'jabatan_id',
        'job_grade_id',
        'tanggal_bergabung',
        'gaji_pokok',
        'metode_penggajian',
        'gaji_bpjs',
        'gaji_overtime',
        'tanggal_update',
        'iuran_air',
        'iuran_locker',
    ];

    public function __construct(private readonly AdminSalaryVisibility $salaryVisibility) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Karyawan::class);

        $filters = $this->filterState($request);
        $karyawans = $this->filteredQuery($filters)
            ->paginate($filters['perPage'])
            ->withQueryString()
            ->through(function (Karyawan $karyawan) use ($request): array {
                $maskSalary = $this->salaryVisibility->shouldMaskSalary($karyawan, $request->user());

                return [
                    'id' => $karyawan->id,
                    'uuid' => $karyawan->uuid,
                    'nip' => $karyawan->nip,
                    'nama' => $karyawan->nama,
                    'email' => $karyawan->email,
                    'job_grade' => $karyawan->jobGrade ? [
                        'grade' => $karyawan->jobGrade->grade,
                        'name' => $karyawan->jobGrade->grade_name,
                    ] : null,
                    'company' => $karyawan->company?->name,
                    'directorate' => $karyawan->directorate?->name,
                    'department' => $karyawan->department?->name,
                    'jabatan' => $karyawan->jabatan?->name,
                    'etnis' => $karyawan->etnis?->name,
                    'status' => $karyawan->status_karyawan?->value,
                    'status_label' => $karyawan->status_karyawan?->displayLabel(),
                    'status_active' => $karyawan->status_karyawan?->usesActiveDot() ?? false,
                    'lama_bekerja' => $karyawan->lamaBekerja(),
                    'metode_penggajian' => $karyawan->metode_penggajian?->value,
                    'metode_penggajian_label' => $karyawan->metode_penggajian?->label(),
                    'salary_restricted' => $maskSalary,
                    'gaji_pokok' => $maskSalary ? null : $karyawan->gaji_pokok,
                    'gaji_bpjs' => $karyawan->gaji_bpjs,
                    'gaji_overtime' => $karyawan->gaji_overtime,
                    'tanggal_update' => $karyawan->tanggal_update?->format('Y-m-d'),
                    'iuran_air' => $karyawan->iuran_air,
                    'iuran_locker' => $karyawan->iuran_locker,
                ];
            });

        return Inertia::render('Employees/Index', [
            'karyawans' => $karyawans,
            'filters' => $filters,
            'filterOptions' => [
                'companies' => $this->options(Company::class),
                'directorates' => $this->relatedOptions(
                    Directorate::class,
                    'directorate_id',
                    $filters,
                    ['company_id' => 'companyId'],
                ),
                'departments' => $this->relatedOptions(
                    Department::class,
                    'department_id',
                    $filters,
                    ['company_id' => 'companyId', 'directorate_id' => 'directorateId'],
                ),
                'jabatans' => $this->relatedOptions(
                    Jabatan::class,
                    'jabatan_id',
                    $filters,
                    ['company_id' => 'companyId', 'directorate_id' => 'directorateId', 'department_id' => 'departmentId'],
                ),
                'statuses' => collect(KaryawanStatus::filterOptions())
                    ->map(fn (string $label, string $value): array => compact('value', 'label'))
                    ->values()
                    ->all(),
                'perPage' => self::PER_PAGE_OPTIONS,
            ],
        ]);
    }

    public function searchOptions(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Karyawan::class);

        $search = trim((string) $request->query('search', ''));

        $employees = Karyawan::query()
            ->select(['id', 'nip', 'nama'])
            ->whereIn('status_karyawan', KaryawanStatus::valuesForFilter('active'))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama')
            ->limit(20)
            ->get()
            ->map(fn (Karyawan $employee): array => [
                'value' => $employee->id,
                'label' => "{$employee->nip} - {$employee->nama}",
                'nama' => $employee->nama,
                'nip' => $employee->nip,
            ]);

        return response()->json($employees);
    }

    public function create(): Response
    {
        Gate::authorize('create', Karyawan::class);

        return Inertia::render('Employees/Form', [
            'employee' => null,
            'salaryRestricted' => false,
            'options' => $this->formOptions(),
        ]);
    }

    public function store(SaveKaryawanRequest $request): RedirectResponse
    {
        $karyawan = Karyawan::query()->create([
            ...$this->normalizedAttributes($request->validated()),
            'nip' => karyawan_next_nip(),
            'outsource' => karyawan_outsource(),
            'tanggal_update' => now()->toDateString(),
        ]);

        return redirect()
            ->route('admin.employees.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Karyawan ditambahkan',
                'message' => "{$karyawan->nama} berhasil disimpan.",
            ]);
    }

    public function edit(Request $request, Karyawan $karyawan): Response
    {
        Gate::authorize('update', $karyawan);
        $salaryRestricted = $this->salaryVisibility->shouldMaskSalary($karyawan, $request->user());
        $employee = [
            'id' => $karyawan->id,
            'uuid' => $karyawan->uuid,
            ...$karyawan->only($karyawan->getFillable()),
            'tanggal_lahir' => $karyawan->tanggal_lahir?->toDateString(),
            'tanggal_bergabung' => $karyawan->tanggal_bergabung?->toDateString(),
            'tanggal_resigned' => $karyawan->tanggal_resigned?->toDateString(),
            'tanggal_blacklist' => $karyawan->tanggal_blacklist?->toDateString(),
            'status_karyawan' => $karyawan->status_karyawan?->value,
            'outsource' => $karyawan->outsource?->value,
            'metode_penggajian' => $karyawan->metode_penggajian?->value,
        ];

        if ($salaryRestricted) {
            foreach (AdminSalaryVisibility::RESTRICTED_PAYROLL_FIELDS as $field) {
                $employee[$field] = null;
            }
        }

        return Inertia::render('Employees/Form', [
            'employee' => $employee,
            'salaryRestricted' => $salaryRestricted,
            'options' => $this->formOptions(),
        ]);
    }

    public function update(SaveKaryawanRequest $request, Karyawan $karyawan): RedirectResponse
    {
        $attributes = $this->normalizedAttributes($request->validated());

        if ($this->salaryVisibility->shouldMaskSalary($karyawan, $request->user())) {
            $attributes = [
                ...$attributes,
                ...$karyawan->only(AdminSalaryVisibility::RESTRICTED_PAYROLL_FIELDS),
            ];
        }

        $karyawan->update([
            ...$attributes,
            'outsource' => karyawan_outsource(),
            'tanggal_update' => now()->toDateString(),
        ]);

        return redirect()
            ->route('admin.employees.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Karyawan diperbarui',
                'message' => "Perubahan data {$karyawan->nama} berhasil disimpan.",
            ]);
    }

    public function destroy(Karyawan $karyawan): RedirectResponse
    {
        Gate::authorize('delete', $karyawan);

        $name = $karyawan->nama;
        $karyawan->delete();

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Karyawan dihapus',
            'message' => "{$name} berhasil dihapus.",
        ]);
    }

    public function export(Request $request, KaryawanExcelExporter $exporter): BinaryFileResponse
    {
        Gate::authorize('export-employees');

        $filename = 'karyawan-'.now()->format('Ymd-His').'.xlsx';
        $path = $exporter->export(
            $this->filteredQuery($this->filterState($request))->lazy(500),
            $filename,
        );

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function exportKhusus(
        Request $request,
        KaryawanExcelKhususExporter $exporter,
    ): BinaryFileResponse {
        Gate::authorize('export-employees-khusus');

        $validated = $request->validate([
            'semua_data' => ['required', 'boolean'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $includeAll = (bool) $validated['semua_data'];
        $monthStart = Carbon::create(
            (int) $validated['tahun'],
            (int) $validated['bulan'],
            1,
        )->startOfMonth();

        $query = Karyawan::query()
            ->with([
                'company:id,name',
                'directorate:id,name',
                'department:id,name',
                'jabatan:id,name',
                'jobGrade:id,grade,grade_name',
                'etnis:id,name',
            ])
            ->where(function (Builder $query) use ($includeAll, $monthStart): void {
                $query->whereIn('status_karyawan', [
                    KaryawanStatus::Pkwt->value,
                    KaryawanStatus::Pkwtt->value,
                    KaryawanStatus::Dirumahkan->value,
                ]);

                if ($includeAll) {
                    $query->orWhereIn('status_karyawan', [
                        KaryawanStatus::Resigned->value,
                        KaryawanStatus::Blacklist->value,
                    ]);

                    return;
                }

                $query->orWhere(function (Builder $query) use ($monthStart): void {
                    $query->where('status_karyawan', KaryawanStatus::Resigned->value)
                        ->whereNotNull('tanggal_resigned')
                        ->whereDate('tanggal_resigned', '>=', $monthStart);
                })->orWhere(function (Builder $query) use ($monthStart): void {
                    $query->where('status_karyawan', KaryawanStatus::Blacklist->value)
                        ->whereNotNull('tanggal_blacklist')
                        ->whereDate('tanggal_blacklist', '>=', $monthStart);
                });
            })
            ->orderBy('nip');

        $title = $includeAll
            ? 'Data Seluruh Karyawan'
            : 'Data Karyawan Mulai '.$monthStart->locale('id')->translatedFormat('F Y');
        $filename = 'Data_Karyawan_Khusus_'.now()->format('Ymd_His').'.xlsx';
        $path = $exporter->export($query->lazy(500), $title, $filename);

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * @return array{
     *     search: string,
     *     status: string,
     *     companyId: string,
     *     directorateId: string,
     *     departmentId: string,
     *     jabatanId: string,
     *     perPage: int,
     *     sortField: string,
     *     sortDirection: string
     * }
     */
    private function filterState(Request $request): array
    {
        $perPage = $request->integer('perPage', 10);
        $sortField = (string) $request->query('sortField', 'nip');
        $sortDirection = strtolower((string) $request->query('sortDirection', 'desc'));

        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => (string) $request->query('status', 'active'),
            'companyId' => (string) $request->query('companyId', ''),
            'directorateId' => (string) $request->query('directorateId', ''),
            'departmentId' => (string) $request->query('departmentId', ''),
            'jabatanId' => (string) $request->query('jabatanId', ''),
            'perPage' => in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 10,
            'sortField' => in_array($sortField, self::SORTABLE_FIELDS, true) ? $sortField : 'nip',
            'sortDirection' => in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'desc',
        ];
    }

    /**
     * @param  array<string, string|int>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return Karyawan::query()
            ->with(['company:id,name', 'directorate:id,name', 'department:id,name', 'jabatan:id,name', 'jobGrade:id,grade,grade_name', 'etnis:id,name'])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                $statusValues = KaryawanStatus::valuesForFilter((string) $filters['status']);

                if ($statusValues !== []) {
                    $query->whereIn('status_karyawan', $statusValues);
                }
            })
            ->when($filters['companyId'] !== '', fn (Builder $query) => $query->where('company_id', $filters['companyId']))
            ->when($filters['directorateId'] !== '', fn (Builder $query) => $query->where('directorate_id', $filters['directorateId']))
            ->when($filters['departmentId'] !== '', fn (Builder $query) => $query->where('department_id', $filters['departmentId']))
            ->when($filters['jabatanId'] !== '', fn (Builder $query) => $query->where('jabatan_id', $filters['jabatanId']))
            ->orderBy((string) $filters['sortField'], (string) $filters['sortDirection'])
            ->when(
                $filters['sortField'] !== 'nip',
                fn (Builder $query) => $query->orderBy('nip'),
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'companies' => $this->options(Company::class),
            'directorates' => $this->options(Directorate::class),
            'departments' => $this->options(Department::class),
            'jabatans' => $this->options(Jabatan::class),
            'etnis' => $this->options(Etnis::class),
            'jobGrades' => JobGrade::query()
                ->orderBy('id')
                ->get(['id', 'grade', 'grade_name'])
                ->map(fn (JobGrade $grade): array => [
                    'value' => $grade->id,
                    'label' => "{$grade->grade} - {$grade->grade_name}",
                ])
                ->all(),
            'statuses' => collect(KaryawanStatus::cases())
                ->map(fn (KaryawanStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->all(),
            'genders' => $this->simpleOptions(['Laki-laki', 'Perempuan']),
            'maritalStatuses' => $this->simpleOptions(KaryawanOptions::MARITAL_STATUSES),
            'bloodTypes' => $this->simpleOptions(['A', 'B', 'AB', 'O']),
            'identityTypes' => $this->simpleOptions(['KTP', 'SIM', 'Paspor']),
            'religions' => $this->simpleOptions(KaryawanOptions::AGAMA),
            'educations' => $this->simpleOptions(KaryawanOptions::PENDIDIKAN),
            'banks' => $this->simpleOptions(KaryawanOptions::BANKS),
            'payrollMethods' => PayrollMethod::options(),
            'ptkp' => KaryawanOptions::options(KaryawanOptions::PTKP),
        ];
    }

    /**
     * @param  class-string<Company|Department|Directorate|Etnis|Jabatan>  $modelClass
     * @return list<array{value: int, label: string}>
     */
    private function options(string $modelClass): array
    {
        return $modelClass::query()
            ->whereNotNull('name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($record): array => ['value' => $record->id, 'label' => $record->name])
            ->all();
    }

    /**
     * @param  class-string<Department|Directorate|Etnis|Jabatan>  $modelClass
     * @param  array<string, string|int>  $filters
     * @param  array<string, string>  $relationships
     * @return list<array{value: int, label: string}>
     */
    private function relatedOptions(
        string $modelClass,
        string $foreignKey,
        array $filters,
        array $relationships,
    ): array {
        $employeeIds = Karyawan::query()
            ->select($foreignKey)
            ->whereNotNull($foreignKey)
            ->when(
                $relationships !== [],
                function (Builder $query) use ($filters, $relationships): void {
                    foreach ($relationships as $column => $filterKey) {
                        $query->when(
                            $filters[$filterKey] !== '',
                            fn (Builder $query) => $query->where($column, $filters[$filterKey]),
                        );
                    }
                },
            );

        return $modelClass::query()
            ->whereIn('id', $employeeIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($record): array => ['value' => $record->id, 'label' => $record->name])
            ->all();
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, label: string}>
     */
    private function simpleOptions(array $values): array
    {
        return collect($values)
            ->map(fn (string $value): array => ['value' => $value, 'label' => $value])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizedAttributes(array $attributes): array
    {
        foreach ([
            'email', 'hp', 'telepon', 'status_pernikahan', 'golongan_darah',
            'agama', 'jenis_identitas', 'no_identitas', 'alamat_identitas',
            'alamat_tinggal_sekarang', 'nama_bank', 'nomor_rekening', 'no_npwp', 'ptkp',
            'nama_contact_darurat', 'nama_contact_darurat_2', 'contact_darurat_1',
            'contact_darurat_2', 'hubungan_1', 'hubungan_2', 'jurusan', 'nama_kampus',
        ] as $field) {
            if (isset($attributes[$field]) && trim((string) $attributes[$field]) === '') {
                $attributes[$field] = null;
            }
        }

        foreach ([
            'gaji_pokok', 'gaji_tetap', 'gaji_overtime', 'gaji_shift_malam_satpam',
            'bonus', 'tunjangan_jabatan', 'tunjangan_bahasa', 'tunjangan_skill',
            'tunjangan_lembur_sabtu', 'tunjangan_lama_kerja', 'tunjangan_housing',
            'iuran_air', 'denda', 'iuran_locker', 'gaji_bpjs',
        ] as $field) {
            $attributes[$field] = karyawan_money_to_int($attributes[$field] ?? null);
        }

        return $attributes;
    }
}
