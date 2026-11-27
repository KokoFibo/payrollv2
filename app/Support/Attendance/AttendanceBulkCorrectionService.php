<?php

namespace App\Support\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Karyawan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceBulkCorrectionService
{
    public const MAX_DATE_RANGE_DAYS = 31;

    public const MAX_TARGETS = 2000;

    public const CONDITIONS = [
        'all' => 'Semua data',
        'incomplete' => 'Punch tidak lengkap',
        'review_required' => 'Perlu review',
        'reviewed' => 'Sudah reviewed',
        'complete' => 'Punch lengkap',
    ];

    private const SLOT_NAMES = [
        'first_in',
        'first_out',
        'second_in',
        'second_out',
        'overtime_in',
        'overtime_out',
    ];

    public function __construct(
        private AttendanceRecordEditor $recordEditor,
    ) {}

    /**
     * @param  array{
     *     start_date: string,
     *     end_date: string,
     *     company_id: int|null,
     *     directorate_id: int|null,
     *     department_id: int|null,
     *     jabatan_id: int|null,
     *     shift: string,
     *     status: string,
     *     schedule_profile: string,
     *     condition: string,
     *     search: string,
     *     page: int,
     *     per_page: int
     * }  $filters
     * @return array<string, mixed>
     */
    public function preview(array $filters): array
    {
        $startsOn = CarbonImmutable::parse($filters['start_date'])->startOfDay();
        $endsOn = CarbonImmutable::parse($filters['end_date'])->startOfDay();
        $attendanceQuery = $this->filteredAttendanceQuery($filters, $startsOn, $endsOn);
        $attendanceSummary = (clone $attendanceQuery)
            ->selectRaw(
                'COUNT(*) as total, '
                .'COALESCE(SUM(CASE WHEN attendance.has_missing_punch = 1 THEN 1 ELSE 0 END), 0) as incomplete, '
                .'COALESCE(SUM(CASE WHEN attendance.requires_review = 1 THEN 1 ELSE 0 END), 0) as review_required',
            )
            ->first();
        $total = (int) ($attendanceSummary->total ?? 0);
        $incompleteCount = (int) ($attendanceSummary->incomplete ?? 0);
        $reviewRequiredCount = (int) ($attendanceSummary->review_required ?? 0);
        $perPage = (int) $filters['per_page'];
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min((int) $filters['page'], $lastPage);
        $filters['page'] = $currentPage;
        $rows = (clone $attendanceQuery)
            ->leftJoin('companies', 'companies.id', '=', 'employees.company_id')
            ->leftJoin('directorates', 'directorates.id', '=', 'employees.directorate_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->leftJoin('jabatans', 'jabatans.id', '=', 'employees.jabatan_id')
            ->select($this->targetColumns())
            ->orderBy('attendance.attendance_date')
            ->orderBy('employees.nip')
            ->orderBy('attendance.id')
            ->forPage($currentPage, $perPage)
            ->get()
            ->map(fn (object $row): array => $this->targetRow($row))
            ->all();

        return [
            'data' => $rows,
            'filters' => $filters,
            'summary' => [
                'total' => $total,
                'incomplete' => $incompleteCount,
                'review_required' => $reviewRequiredCount,
            ],
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'total' => $total,
                'from' => $total === 0 ? null : (($currentPage - 1) * $perPage) + 1,
                'to' => $total === 0 ? null : min($currentPage * $perPage, $total),
            ],
        ];
    }

    /**
     * @param  array{
     *     start_date: string,
     *     end_date: string,
     *     company_id: int|null,
     *     directorate_id: int|null,
     *     department_id: int|null,
     *     jabatan_id: int|null,
     *     shift: string,
     *     status: string,
     *     schedule_profile: string,
     *     condition: string,
     *     search: string
     * }  $filters
     * @param  array<string, string|null>  $slotTimes
     * @return array{requested: int, created: int, updated: int, skipped: int}
     */
    public function applyAll(
        array $filters,
        array $slotTimes,
        bool $fillEmptyOnly,
        int $recordedBy,
    ): array {
        $startsOn = CarbonImmutable::parse($filters['start_date'])->startOfDay();
        $endsOn = CarbonImmutable::parse($filters['end_date'])->startOfDay();
        $targets = $this->filteredAttendanceQuery($filters, $startsOn, $endsOn)
            ->select([
                'attendance.karyawan_id',
                'attendance.attendance_date',
            ])
            ->orderBy('attendance.id')
            ->get()
            ->map(fn (object $row): array => [
                'karyawan_id' => (int) $row->karyawan_id,
                'attendance_date' => (string) $row->attendance_date,
            ])
            ->all();
        $result = [
            'requested' => count($targets),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        if ($targets === []) {
            return $result;
        }

        DB::transaction(function () use (
            $targets,
            $slotTimes,
            $fillEmptyOnly,
            $recordedBy,
            &$result,
        ): void {
            foreach (array_chunk($targets, 500) as $targetChunk) {
                $chunkResult = $this->apply(
                    $targetChunk,
                    $slotTimes,
                    $fillEmptyOnly,
                    $recordedBy,
                );
                $result['created'] += $chunkResult['created'];
                $result['updated'] += $chunkResult['updated'];
                $result['skipped'] += $chunkResult['skipped'];
            }
        });

        return $result;
    }

    /**
     * @param  list<array{karyawan_id: int, attendance_date: string}>  $targets
     * @param  array<string, string|null>  $slotTimes
     * @return array{requested: int, created: int, updated: int, skipped: int}
     */
    public function apply(
        array $targets,
        array $slotTimes,
        bool $fillEmptyOnly,
        int $recordedBy,
    ): array {
        $targets = collect($targets)
            ->map(fn (array $target): array => [
                'karyawan_id' => (int) $target['karyawan_id'],
                'attendance_date' => CarbonImmutable::parse($target['attendance_date'])->toDateString(),
            ])
            ->unique(fn (array $target): string => $this->targetKey(
                $target['karyawan_id'],
                $target['attendance_date'],
            ))
            ->values()
            ->all();
        $employeeIds = array_values(array_unique(array_column($targets, 'karyawan_id')));
        $attendanceDates = array_values(array_unique(array_column($targets, 'attendance_date')));
        $employees = Karyawan::query()
            ->whereIn('id', $employeeIds)
            ->with([
                'company:id,name',
                'directorate:id,name',
                'department:id,name',
                'jabatan:id,name',
            ])
            ->get()
            ->keyBy('id');
        $records = AttendanceRecord::query()
            ->whereIn('karyawan_id', $employeeIds)
            ->whereIn('attendance_date', $attendanceDates)
            ->get()
            ->keyBy(fn (AttendanceRecord $record): string => $this->targetKey(
                $record->karyawan_id,
                $record->attendance_date->toDateString(),
            ));
        $operations = [];
        $invalidMissingFirstIn = [];
        $skipped = 0;

        foreach ($targets as $target) {
            $employee = $employees->get($target['karyawan_id']);

            if (! $employee instanceof Karyawan) {
                $skipped++;

                continue;
            }

            $key = $this->targetKey($employee->id, $target['attendance_date']);
            $existingRecord = $records->get($key);
            $updatedSlots = $this->slotValues($existingRecord);
            $hasChanges = false;

            foreach (self::SLOT_NAMES as $slotName) {
                $submittedTime = $slotTimes[$slotName] ?? null;

                if ($submittedTime === null || $submittedTime === '') {
                    continue;
                }

                if ($fillEmptyOnly && $this->hasTime($updatedSlots[$slotName])) {
                    continue;
                }

                if ($updatedSlots[$slotName] !== $submittedTime) {
                    $updatedSlots[$slotName] = $submittedTime;
                    $hasChanges = true;
                }
            }

            if (! $existingRecord instanceof AttendanceRecord && ! $this->hasTime($updatedSlots['first_in'])) {
                $invalidMissingFirstIn[] = (string) $employee->nip;

                continue;
            }

            if ($existingRecord instanceof AttendanceRecord && ! $hasChanges) {
                $skipped++;

                continue;
            }

            $operations[] = [
                'employee' => $employee,
                'existing_record' => $existingRecord,
                'attendance_date' => $target['attendance_date'],
                'slots' => $updatedSlots,
            ];
        }

        if ($invalidMissingFirstIn !== []) {
            throw ValidationException::withMessages([
                'times.first_in' => count($invalidMissingFirstIn)
                    .' record baru membutuhkan jam masuk. NIP: '
                    .implode(', ', array_slice($invalidMissingFirstIn, 0, 5)),
            ]);
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use (
            $operations,
            $recordedBy,
            &$created,
            &$updated,
        ): void {
            foreach ($operations as $operation) {
                /** @var Karyawan $employee */
                $employee = $operation['employee'];
                /** @var AttendanceRecord|null $existingRecord */
                $existingRecord = $operation['existing_record'];
                $this->recordEditor->save(
                    $employee,
                    CarbonImmutable::parse($operation['attendance_date'])->startOfDay(),
                    $existingRecord?->status ?? 'present',
                    $operation['slots'],
                    $recordedBy,
                    $existingRecord,
                );

                if ($existingRecord instanceof AttendanceRecord) {
                    $updated++;
                } else {
                    $created++;
                }
            }
        });

        return [
            'requested' => count($targets),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array{
     *     company_id: int|null,
     *     directorate_id: int|null,
     *     department_id: int|null,
     *     jabatan_id: int|null,
     *     search: string,
     *     shift: string,
     *     status: string,
     *     schedule_profile: string,
     *     condition: string
     * }  $filters
     */
    private function filteredAttendanceQuery(
        array $filters,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
    ): Builder {
        return DB::table('attendance_records as attendance')
            ->join('karyawans as employees', 'employees.id', '=', 'attendance.karyawan_id')
            ->whereBetween('attendance.attendance_date', [
                $startsOn->toDateString(),
                $endsOn->toDateString(),
            ])
            ->when(
                $filters['company_id'] !== null,
                fn (Builder $query): Builder => $query->where('employees.company_id', $filters['company_id']),
            )
            ->when(
                $filters['directorate_id'] !== null,
                fn (Builder $query): Builder => $query->where('employees.directorate_id', $filters['directorate_id']),
            )
            ->when(
                $filters['department_id'] !== null,
                fn (Builder $query): Builder => $query->where('employees.department_id', $filters['department_id']),
            )
            ->when(
                $filters['jabatan_id'] !== null,
                fn (Builder $query): Builder => $query->where('employees.jabatan_id', $filters['jabatan_id']),
            )
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('employees.nama', 'like', '%'.$filters['search'].'%')
                        ->orWhere('employees.nip', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when(
                $filters['shift'] !== '',
                fn (Builder $query): Builder => $query->where('attendance.shift', $filters['shift']),
            )
            ->when(
                $filters['status'] !== '',
                fn (Builder $query): Builder => $query->where('attendance.status', $filters['status']),
            )
            ->when(
                $filters['schedule_profile'] !== '',
                fn (Builder $query): Builder => $query->where(
                    'attendance.schedule_profile',
                    $filters['schedule_profile'],
                ),
            )
            ->when(
                $filters['condition'] === 'incomplete',
                fn (Builder $query): Builder => $query->where('attendance.has_missing_punch', true),
            )
            ->when(
                $filters['condition'] === 'review_required',
                fn (Builder $query): Builder => $query->where('attendance.requires_review', true),
            )
            ->when(
                $filters['condition'] === 'reviewed',
                fn (Builder $query): Builder => $query->whereNotNull('attendance.review_resolved_at'),
            )
            ->when(
                $filters['condition'] === 'complete',
                fn (Builder $query): Builder => $query->where('attendance.has_missing_punch', false),
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function targetRow(object $row): array
    {
        $attendanceDate = (string) $row->attendance_date;
        $employeeId = (int) $row->employee_id;
        $shift = $row->shift ?? null;
        $status = $row->status ?? null;

        return [
            'key' => $this->targetKey($employeeId, $attendanceDate),
            'attendance_id' => (int) $row->attendance_id,
            'karyawan_id' => $employeeId,
            'attendance_date' => $attendanceDate,
            'has_attendance' => true,
            'employee' => [
                'nip' => (int) $row->employee_nip,
                'name' => $row->employee_name,
                'company' => $row->company_name,
                'directorate' => $row->directorate_name,
                'department' => $row->department_name,
                'position' => $row->jabatan_name,
            ],
            'shift' => $shift,
            'shift_label' => match ($shift) {
                'day' => 'Shift pagi',
                'night' => 'Shift malam',
                default => null,
            },
            'status' => $status,
            'status_label' => $status === null
                ? null
                : (AttendanceRecord::STATUSES[$status] ?? $status),
            'schedule_profile' => $row->schedule_profile ?? null,
            'has_missing_punch' => (bool) $row->has_missing_punch,
            'requires_review' => (bool) $row->requires_review,
            'reviewed' => $row->review_resolved_at !== null,
            'first_in' => $this->shortTime($row->first_in),
            'first_out' => $this->shortTime($row->first_out),
            'second_in' => $this->shortTime($row->second_in),
            'second_out' => $this->shortTime($row->second_out),
            'overtime_in' => $this->shortTime($row->overtime_in),
            'overtime_out' => $this->shortTime($row->overtime_out),
            'first_out_day_offset' => (int) $row->first_out_day_offset,
            'second_in_day_offset' => (int) $row->second_in_day_offset,
            'second_out_day_offset' => (int) $row->second_out_day_offset,
            'overtime_in_day_offset' => (int) $row->overtime_in_day_offset,
            'overtime_out_day_offset' => (int) $row->overtime_out_day_offset,
        ];
    }

    /**
     * @return list<string>
     */
    private function employeeColumns(): array
    {
        return [
            'employees.id as employee_id',
            'employees.nip as employee_nip',
            'employees.nama as employee_name',
            'companies.name as company_name',
            'directorates.name as directorate_name',
            'departments.name as department_name',
            'jabatans.name as jabatan_name',
        ];
    }

    /**
     * @return list<string>
     */
    private function targetColumns(): array
    {
        return [
            ...$this->employeeColumns(),
            'attendance.id as attendance_id',
            'attendance.attendance_date',
            'attendance.status',
            'attendance.shift',
            'attendance.schedule_profile',
            'attendance.has_missing_punch',
            'attendance.requires_review',
            'attendance.review_resolved_at',
            'attendance.first_in',
            'attendance.first_out',
            'attendance.second_in',
            'attendance.second_out',
            'attendance.overtime_in',
            'attendance.overtime_out',
            'attendance.first_out_day_offset',
            'attendance.second_in_day_offset',
            'attendance.second_out_day_offset',
            'attendance.overtime_in_day_offset',
            'attendance.overtime_out_day_offset',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function slotValues(?AttendanceRecord $record): array
    {
        return collect(self::SLOT_NAMES)
            ->mapWithKeys(fn (string $slotName): array => [
                $slotName => $this->shortTime($record?->{$slotName}),
            ])
            ->all();
    }

    private function targetKey(int $employeeId, string $attendanceDate): string
    {
        return $employeeId.'|'.$attendanceDate;
    }

    private function hasTime(?string $time): bool
    {
        return $time !== null && $time !== '';
    }

    private function shortTime(?string $time): ?string
    {
        return $this->hasTime($time) ? substr((string) $time, 0, 5) : null;
    }
}
