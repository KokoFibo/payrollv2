<?php

namespace App\Support\Attendance;

use App\Models\AttendanceImport;
use App\Models\AttendanceRecord;
use App\Models\Karyawan;
use App\Models\User;
use App\Support\PayrollAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceDatabaseImportService
{
    public const SourceTable = 'yfrekappresensis';

    private const ChunkSize = 250;

    private const SlotNames = [
        'first_in',
        'first_out',
        'second_in',
        'second_out',
        'overtime_in',
        'overtime_out',
    ];

    private const UpsertColumns = [
        'attendance_import_id',
        'status',
        'shift',
        'schedule_profile',
        'first_in',
        'first_out',
        'first_out_day_offset',
        'second_in',
        'second_out',
        'overtime_in',
        'overtime_out',
        'second_in_day_offset',
        'second_out_day_offset',
        'overtime_in_day_offset',
        'overtime_out_day_offset',
        'expected_check_in',
        'expected_check_out',
        'last_out_day_offset',
        'worked_hours',
        'worked_days',
        'overtime_hours',
        'holiday_worked_hours',
        'holiday_overtime_hours',
        'night_shift_allowance',
        'raw_punch_count',
        'effective_punch_count',
        'duplicate_punch_count',
        'ignored_punch_count',
        'late_minutes',
        'late_penalty_hours',
        'early_leave_minutes',
        'has_missing_punch',
        'has_duplicate_punch',
        'requires_review',
        'review_resolved_at',
        'review_resolved_by',
        'review_resolution_note',
        'comparison_notes',
        'recorded_by',
        'calendar_override_id',
        'is_holiday_pay',
        'updated_at',
    ];

    public function __construct(
        private PayrollAuditService $payrollAuditService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(string $importMode, string $period, int $page, int $perPage): array
    {
        $this->assertSourceAvailable();
        $range = $this->periodRange($importMode, $period);
        $summary = $this->sourceSummary($range['starts_at'], $range['ends_at']);
        $lastPage = max(1, (int) ceil($summary['matched'] / $perPage));
        $currentPage = min($page, $lastPage);
        $availableDates = $this->matchedSourceRowsQuery($range['starts_at'], $range['ends_at'])
            ->selectRaw('presence.date as attendance_date, COUNT(*) as rows_count')
            ->groupBy('presence.date')
            ->orderBy('presence.date')
            ->get()
            ->map(fn (object $row): array => [
                'attendance_date' => (string) $row->attendance_date,
                'rows_count' => (int) $row->rows_count,
            ])
            ->all();
        $rows = $this->matchedSourceRowsQuery($range['starts_at'], $range['ends_at'])
            ->leftJoin(
                'attendance_records as existing_attendance',
                fn (JoinClause $join): JoinClause => $join
                    ->on('existing_attendance.karyawan_id', '=', 'employees.id')
                    ->on('existing_attendance.attendance_date', '=', 'presence.date'),
            )
            ->select([
                'presence.id as source_id',
                'presence.user_id as source_user_id',
                'presence.karyawan_id as source_karyawan_id',
                'presence.date',
                'presence.first_in',
                'presence.first_out',
                'presence.second_in',
                'presence.second_out',
                'presence.overtime_in',
                'presence.overtime_out',
                'presence.total_jam_kerja',
                'presence.total_hari_kerja',
                'presence.total_jam_lembur',
                'presence.total_jam_kerja_libur',
                'presence.total_jam_lembur_libur',
                'presence.late',
                'presence.no_scan',
                'presence.shift',
                'presence.no_scan_history',
                'presence.late_history',
                'presence.shift_malam',
                'employees.id as employee_id',
                'employees.nip',
                'employees.nama',
                'existing_attendance.id as attendance_id',
            ])
            ->orderBy('employees.nip')
            ->orderBy('presence.id')
            ->forPage($currentPage, $perPage)
            ->get()
            ->map(fn (object $row): array => [
                'source_id' => (int) $row->source_id,
                'attendance_id' => $row->attendance_id === null ? null : (int) $row->attendance_id,
                'karyawan_id' => (int) $row->employee_id,
                'nip' => (int) $row->nip,
                'name' => $row->nama,
                'attendance_date' => (string) $row->date,
                'first_in' => $this->shortTime($row->first_in),
                'first_out' => $this->shortTime($row->first_out),
                'second_in' => $this->shortTime($row->second_in),
                'second_out' => $this->shortTime($row->second_out),
                'overtime_in' => $this->shortTime($row->overtime_in),
                'overtime_out' => $this->shortTime($row->overtime_out),
                'source_shift' => $row->shift
                    ?? ((int) ($row->shift_malam ?? 0) === 1 ? 'Malam' : null),
                'source_late' => (int) $row->late > 0 || (string) $row->late_history === '1',
                'source_no_scan' => $this->hasText($row->no_scan) || $this->hasText($row->no_scan_history),
                'source_worked_hours' => $row->total_jam_kerja === null ? null : (float) $row->total_jam_kerja,
                'source_overtime_hours' => $row->total_jam_lembur === null ? null : (float) $row->total_jam_lembur,
                'target_state' => $row->attendance_id === null ? 'create' : 'update',
            ])
            ->all();

        return [
            'source' => [
                'database' => $this->sourceDatabase(),
                'table' => self::SourceTable,
            ],
            'period' => [
                'mode' => $range['mode'],
                'start_date' => $range['starts_at']->toDateString(),
                'end_date' => $range['ends_at']->toDateString(),
                'label' => $range['label'],
            ],
            'summary' => $summary,
            'dates' => $availableDates,
            'data' => $rows,
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'total' => $summary['matched'],
                'from' => $summary['matched'] === 0 ? null : (($currentPage - 1) * $perPage) + 1,
                'to' => $summary['matched'] === 0 ? null : min($currentPage * $perPage, $summary['matched']),
            ],
        ];
    }

    public function import(string $importMode, string $period, User $actor): AttendanceImport
    {
        $this->assertSourceAvailable();
        $range = $this->periodRange($importMode, $period);
        $summary = $this->sourceSummary($range['starts_at'], $range['ends_at']);
        $periodField = $importMode === 'month' ? 'attendance_month' : 'attendance_date';

        if ($summary['total'] === 0) {
            throw ValidationException::withMessages([
                $periodField => 'Tidak ada attendance pada periode tersebut di database source.',
            ]);
        }

        if ($summary['matched'] === 0) {
            throw ValidationException::withMessages([
                $periodField => 'Tidak ada NIP pada database source yang cocok dengan data karyawan.',
            ]);
        }

        if ($importMode === 'month' && function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $importOperation = function () use ($actor, $range, $summary, $periodField): AttendanceImport {
            $import = AttendanceImport::query()->create([
                'imported_by' => $actor->id,
                'original_filename' => 'Database '.$this->sourceDatabase().'.'.self::SourceTable.' · '.$range['label'],
                'file_size' => 0,
                'attendance_date' => $range['attendance_date'],
                'source_employee_count' => $summary['total'],
                'processed_employee_count' => 0,
                'skipped_without_punch_count' => 0,
                'unmatched_employee_count' => $summary['unmatched'],
                'raw_punch_count' => 0,
                'duplicate_punch_count' => 0,
                'missing_punch_count' => 0,
                'late_count' => 0,
                'early_leave_count' => 0,
                'unmatched_user_ids' => $this->unmatchedUserIds(
                    $range['starts_at'],
                    $range['ends_at'],
                ),
                'warnings' => [],
            ]);
            $statistics = [
                'processed' => 0,
                'skipped_without_punch' => 0,
                'raw_punches' => 0,
                'missing_punches' => 0,
                'review_required' => 0,
                'late' => 0,
                'early_leave' => 0,
            ];
            $warnings = [];
            $seenTargetKeys = [];
            $employeeCache = collect();

            $this->matchedSourceRowsQuery($range['starts_at'], $range['ends_at'])
                ->select([
                    'presence.id as source_id',
                    'presence.user_id as source_user_id',
                    'presence.date',
                    'presence.first_in',
                    'presence.first_out',
                    'presence.second_in',
                    'presence.second_out',
                    'presence.overtime_in',
                    'presence.overtime_out',
                    'presence.total_jam_kerja',
                    'presence.total_hari_kerja',
                    'presence.total_jam_lembur',
                    'presence.total_jam_kerja_libur',
                    'presence.total_jam_lembur_libur',
                    'presence.late',
                    'presence.no_scan',
                    'presence.shift',
                    'presence.no_scan_history',
                    'presence.late_history',
                    'presence.shift_malam',
                    'employees.id as employee_id',
                    'employees.nip',
                ])
                ->orderBy('presence.id')
                ->chunk(self::ChunkSize, function (Collection $sourceRows) use (
                    $actor,
                    $import,
                    &$statistics,
                    &$warnings,
                    &$seenTargetKeys,
                    &$employeeCache,
                ): void {
                    $employeeIds = $sourceRows
                        ->pluck('employee_id')
                        ->map(fn (mixed $id): int => (int) $id)
                        ->unique()
                        ->values()
                        ->all();
                    $missingEmployeeIds = array_values(array_diff(
                        $employeeIds,
                        $employeeCache->keys()->all(),
                    ));

                    if ($missingEmployeeIds !== []) {
                        $employeeCache = $employeeCache->union(
                            $this->employeesById($missingEmployeeIds),
                        );
                    }

                    $employees = $employeeCache;
                    $attendanceDates = $sourceRows
                        ->pluck('date')
                        ->map(fn (mixed $date): string => (string) $date)
                        ->unique()
                        ->values()
                        ->all();
                    $existingRecords = AttendanceRecord::query()
                        ->whereIn('karyawan_id', $employeeIds)
                        ->whereIn('attendance_date', $attendanceDates)
                        ->get()
                        ->keyBy(fn (AttendanceRecord $record): string => $record->karyawan_id
                            .'|'.$record->attendance_date->toDateString());
                    $payload = [];

                    foreach ($sourceRows as $sourceRow) {
                        $employee = $employees->get((int) $sourceRow->employee_id);

                        if (! $employee instanceof Karyawan) {
                            continue;
                        }

                        if ($this->presentPunchCount($sourceRow) === 0) {
                            $statistics['skipped_without_punch']++;

                            continue;
                        }

                        $targetKey = $employee->id.'|'.$sourceRow->date;

                        if (isset($seenTargetKeys[$targetKey])) {
                            $warnings[] = "Duplikat source untuk NIP {$employee->nip} pada {$sourceRow->date} dilewati.";

                            continue;
                        }

                        $seenTargetKeys[$targetKey] = true;
                        $existingRecord = $existingRecords->get($targetKey);
                        $row = $this->targetRow(
                            employee: $employee,
                            date: CarbonImmutable::parse($sourceRow->date)->startOfDay(),
                            source: $sourceRow,
                            existingRecord: $existingRecord,
                            recordedBy: $actor->id,
                        );
                        $payload[] = [
                            ...$row,
                            'attendance_import_id' => $import->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $statistics['processed']++;
                        $statistics['raw_punches'] += (int) $row['raw_punch_count'];
                        $statistics['missing_punches'] += $row['has_missing_punch'] ? 1 : 0;
                        $statistics['review_required'] += $row['requires_review'] ? 1 : 0;
                        $statistics['late'] += $row['status'] === 'late' ? 1 : 0;
                        $statistics['early_leave'] += $row['early_leave_minutes'] > 0 ? 1 : 0;
                    }

                    if ($payload === []) {
                        return;
                    }

                    AttendanceRecord::query()->upsert(
                        $payload,
                        ['karyawan_id', 'attendance_date'],
                        self::UpsertColumns,
                    );
                    $this->recordAuditChanges(
                        payload: $payload,
                        existingRecords: $existingRecords,
                        employees: $employees,
                        import: $import,
                        actor: $actor,
                    );
                });

            if ($statistics['processed'] === 0) {
                throw ValidationException::withMessages([
                    $periodField => 'Tidak ada attendance source yang dapat diimpor.',
                ]);
            }

            if ($summary['unmatched'] > 0) {
                $warnings[] = "{$summary['unmatched']} record source tidak memiliki NIP yang cocok dan dilewati.";
            }

            if ($summary['no_scan'] > 0) {
                $warnings[] = "{$summary['no_scan']} record No Scan diimpor sebagai data bermasalah/review.";
            }

            $import->update([
                'processed_employee_count' => $statistics['processed'],
                'skipped_without_punch_count' => $statistics['skipped_without_punch'],
                'raw_punch_count' => $statistics['raw_punches'],
                'missing_punch_count' => $statistics['missing_punches'],
                'late_count' => $statistics['late'],
                'early_leave_count' => $statistics['early_leave'],
                'warnings' => array_values(array_unique($warnings)),
            ]);

            $import = $import->refresh();
            $import->setAttribute('review_required_count', $statistics['review_required']);

            return $import;
        };

        return DB::connection()->transactionLevel() > 0
            ? $importOperation()
            : DB::transaction($importOperation);
    }

    /**
     * @return array{
     *     total: int,
     *     matched: int,
     *     unmatched: int,
     *     existing: int,
     *     create: int,
     *     no_scan: int,
     *     review_required: int,
     *     late: int
     * }
     */
    private function sourceSummary(CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        $summary = $this->sourceQuery($startsAt, $endsAt)
            ->leftJoin(
                'attendance_records as existing_attendance',
                fn (JoinClause $join): JoinClause => $join
                    ->on('existing_attendance.karyawan_id', '=', 'employees.id')
                    ->on('existing_attendance.attendance_date', '=', 'presence.date'),
            )
            ->selectRaw(
                'COUNT(*) as total, '
                .'COALESCE(SUM(CASE WHEN employees.id IS NOT NULL THEN 1 ELSE 0 END), 0) as matched, '
                .'COALESCE(SUM(CASE WHEN employees.id IS NULL THEN 1 ELSE 0 END), 0) as unmatched, '
                .'COALESCE(SUM(CASE WHEN employees.id IS NOT NULL AND existing_attendance.id IS NOT NULL THEN 1 ELSE 0 END), 0) as existing, '
                .'COALESCE(SUM(CASE WHEN employees.id IS NOT NULL AND existing_attendance.id IS NULL THEN 1 ELSE 0 END), 0) as created, '
                ."COALESCE(SUM(CASE WHEN COALESCE(presence.no_scan, '') <> '' OR COALESCE(presence.no_scan_history, '') <> '' THEN 1 ELSE 0 END), 0) as no_scan, "
                .'COALESCE(SUM(CASE WHEN employees.id IS NOT NULL AND ('
                ."COALESCE(presence.no_scan, '') <> '' OR COALESCE(presence.no_scan_history, '') <> '' "
                ."OR NULLIF(presence.first_in, '00:00:00') IS NULL "
                ."OR COALESCE(NULLIF(presence.overtime_out, '00:00:00'), NULLIF(presence.second_out, '00:00:00'), NULLIF(presence.first_out, '00:00:00')) IS NULL "
                .'OR presence.total_jam_kerja < 0 '
                .'OR existing_attendance.requires_review = 1'
                .') THEN 1 ELSE 0 END), 0) as review_required, '
                ."COALESCE(SUM(CASE WHEN presence.late > 0 OR presence.late_history = '1' THEN 1 ELSE 0 END), 0) as late",
            )
            ->first();

        return [
            'total' => (int) ($summary->total ?? 0),
            'matched' => (int) ($summary->matched ?? 0),
            'unmatched' => (int) ($summary->unmatched ?? 0),
            'existing' => (int) ($summary->existing ?? 0),
            'create' => (int) ($summary->created ?? 0),
            'no_scan' => (int) ($summary->no_scan ?? 0),
            'review_required' => (int) ($summary->review_required ?? 0),
            'late' => (int) ($summary->late ?? 0),
        ];
    }

    private function sourceQuery(CarbonImmutable $startsAt, CarbonImmutable $endsAt): Builder
    {
        return DB::table($this->sourceTable(self::SourceTable).' as presence')
            ->leftJoin(
                'karyawans as employees',
                'employees.nip',
                '=',
                'presence.user_id',
            )
            ->whereBetween('presence.date', [
                $startsAt->toDateString(),
                $endsAt->toDateString(),
            ]);
    }

    private function matchedSourceRowsQuery(
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): Builder {
        return $this->sourceQuery($startsAt, $endsAt)->whereNotNull('employees.id');
    }

    /**
     * @return Collection<int, Karyawan>
     */
    private function employeesById(array $employeeIds): Collection
    {
        return Karyawan::query()
            ->select(['id', 'nip', 'nama'])
            ->whereIn('id', $employeeIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function targetRow(
        Karyawan $employee,
        CarbonImmutable $date,
        object $source,
        ?AttendanceRecord $existingRecord,
        int $recordedBy,
    ): array {
        $slotPunches = $this->slotPunches($source, $date);
        $sourceIsNight = ($source->shift ?? null) === 'Malam'
            || (int) ($source->shift_malam ?? 0) === 1;
        $presentPunches = array_values(array_filter($slotPunches));
        $lastPunch = $presentPunches === []
            ? null
            : $presentPunches[array_key_last($presentPunches)]['punched_at'];
        $hasSourceNoScan = $this->hasText($source->no_scan) || $this->hasText($source->no_scan_history);
        $hasSourceLateFlag = (int) $source->late > 0 || (string) $source->late_history === '1';
        $hasMissingPunch = $hasSourceNoScan
            || ! isset($slotPunches['first_in'])
            || $lastPunch === null;
        $notes = [];

        if ($hasSourceNoScan) {
            $notes[] = 'Sumber database menandai No Scan.';
        }

        if ($hasSourceLateFlag) {
            $notes[] = 'Sumber database menandai terlambat.';
        }

        if ((float) ($source->total_jam_kerja ?? 0) < 0) {
            $notes[] = 'Nilai total jam kerja source negatif dan disalin apa adanya.';
        }

        $requiresReview = $hasMissingPunch
            || (float) ($source->total_jam_kerja ?? 0) < 0
            || $hasSourceNoScan
            || ($existingRecord?->requires_review ?? false);
        $holidayWorkedHours = (float) ($source->total_jam_kerja_libur ?? 0);
        $holidayOvertimeHours = (float) ($source->total_jam_lembur_libur ?? 0);

        return [
            'karyawan_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'status' => $presentPunches === []
                ? 'absent'
                : ($hasSourceLateFlag ? 'late' : 'present'),
            'shift' => $sourceIsNight ? 'night' : 'day',
            'schedule_profile' => null,
            'first_in' => $this->punchTime($slotPunches, 'first_in'),
            'first_out' => $this->punchTime($slotPunches, 'first_out'),
            'first_out_day_offset' => $this->punchDayOffset($slotPunches, 'first_out'),
            'second_in' => $this->punchTime($slotPunches, 'second_in'),
            'second_out' => $this->punchTime($slotPunches, 'second_out'),
            'overtime_in' => $this->punchTime($slotPunches, 'overtime_in'),
            'overtime_out' => $this->punchTime($slotPunches, 'overtime_out'),
            'second_in_day_offset' => $this->punchDayOffset($slotPunches, 'second_in'),
            'second_out_day_offset' => $this->punchDayOffset($slotPunches, 'second_out'),
            'overtime_in_day_offset' => $this->punchDayOffset($slotPunches, 'overtime_in'),
            'overtime_out_day_offset' => $this->punchDayOffset($slotPunches, 'overtime_out'),
            'expected_check_in' => null,
            'expected_check_out' => null,
            'last_out_day_offset' => $lastPunch === null
                ? 0
                : (int) $date->diffInDays($lastPunch->startOfDay()),
            'worked_hours' => (float) ($source->total_jam_kerja ?? 0),
            'worked_days' => (float) ($source->total_hari_kerja ?? 0),
            'overtime_hours' => (float) ($source->total_jam_lembur ?? 0),
            'holiday_worked_hours' => $holidayWorkedHours,
            'holiday_overtime_hours' => $holidayOvertimeHours,
            'night_shift_allowance' => max(0.0, (float) ($source->shift_malam ?? 0)),
            'raw_punch_count' => count($presentPunches),
            'effective_punch_count' => count($presentPunches),
            'duplicate_punch_count' => 0,
            'ignored_punch_count' => 0,
            'late_minutes' => max(0, (int) ($source->late ?? 0)),
            'late_penalty_hours' => null,
            'early_leave_minutes' => 0,
            'has_missing_punch' => $hasMissingPunch,
            'has_duplicate_punch' => false,
            'requires_review' => $requiresReview,
            'review_resolved_at' => $requiresReview
                ? null
                : $existingRecord?->review_resolved_at,
            'review_resolved_by' => $requiresReview
                ? null
                : $existingRecord?->review_resolved_by,
            'review_resolution_note' => $requiresReview
                ? null
                : $existingRecord?->review_resolution_note,
            'comparison_notes' => $notes === [] ? null : implode(' ', array_unique($notes)),
            'recorded_by' => $recordedBy,
            'calendar_override_id' => null,
            'is_holiday_pay' => $holidayWorkedHours > 0 || $holidayOvertimeHours > 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     * @param  Collection<int, AttendanceRecord>  $existingRecords
     * @param  Collection<int, Karyawan>  $employees
     */
    private function recordAuditChanges(
        array $payload,
        Collection $existingRecords,
        Collection $employees,
        AttendanceImport $import,
        User $actor,
    ): void {
        $events = [];

        foreach ($payload as $row) {
            $existingRecord = $existingRecords->get($row['karyawan_id']);
            $employee = $employees->get($row['karyawan_id']);

            if (! $employee instanceof Karyawan) {
                continue;
            }

            $auditRecord = new AttendanceRecord;

            if ($existingRecord instanceof AttendanceRecord) {
                $auditRecord->setRawAttributes($existingRecord->getAttributes(), true);
            } else {
                $auditRecord->fill([
                    'karyawan_id' => $row['karyawan_id'],
                    'attendance_date' => $row['attendance_date'],
                ]);
            }

            $auditRecord->fill($row);
            $changes = $this->payrollAuditService->changesFor(
                $auditRecord,
                PayrollAuditService::ATTENDANCE_FIELDS,
                $existingRecord instanceof AttendanceRecord ? 'updated' : 'created',
            );

            $events[] = [
                'event_type' => 'attendance_imported',
                'subject_type' => 'attendance_record',
                'subject_id' => $existingRecord?->id,
                'subject_label' => "Attendance {$employee->nip} · {$row['attendance_date']}",
                'changes' => $changes,
                'metadata' => [
                    'attendance_import_id' => $import->id,
                    'source_database' => $this->sourceDatabase(),
                    'source_table' => self::SourceTable,
                ],
                'employee' => $employee,
            ];
        }

        $this->payrollAuditService->recordMany($events, $actor);
    }

    /**
     * @return array{
     *     first_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     first_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     second_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     second_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     overtime_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     overtime_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null
     * }
     */
    private function slotPunches(object $source, CarbonImmutable $date): array
    {
        $slots = array_fill_keys(self::SlotNames, null);
        $previousPunch = null;
        $sequence = 1;

        foreach (self::SlotNames as $slotName) {
            $time = $this->shortTime($source->{$slotName} ?? null);

            if ($time === null) {
                continue;
            }

            $punchedAt = $date->setTimeFromTimeString($time);

            if ($previousPunch !== null && $punchedAt->lessThanOrEqualTo($previousPunch)) {
                $punchedAt = $punchedAt->addDay();
            }

            $slots[$slotName] = [
                'raw_value' => $time,
                'punched_at' => $punchedAt,
                'is_next_day' => $punchedAt->toDateString() !== $date->toDateString(),
                'sequence' => $sequence++,
            ];
            $previousPunch = $punchedAt;
        }

        return $slots;
    }

    /**
     * @param  array<string, array{punched_at: CarbonImmutable}|null>  $slots
     */
    private function punchTime(array $slots, string $slot): ?string
    {
        return isset($slots[$slot])
            ? $slots[$slot]['punched_at']->format('H:i:s')
            : null;
    }

    /**
     * @param  array<string, array{is_next_day: bool}|null>  $slots
     */
    private function punchDayOffset(array $slots, string $slot): int
    {
        return isset($slots[$slot]) && $slots[$slot]['is_next_day'] ? 1 : 0;
    }

    /**
     * @return list<string>
     */
    private function unmatchedUserIds(
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): array {
        return $this->sourceQuery($startsAt, $endsAt)
            ->whereNull('employees.id')
            ->orderBy('presence.user_id')
            ->limit(50)
            ->pluck('presence.user_id')
            ->map(fn (mixed $userId): string => (string) $userId)
            ->all();
    }

    /**
     * @return array{
     *     mode: string,
     *     starts_at: CarbonImmutable,
     *     ends_at: CarbonImmutable,
     *     label: string,
     *     attendance_date: string
     * }
     */
    private function periodRange(string $importMode, string $period): array
    {
        if ($importMode === 'month') {
            $startsAt = CarbonImmutable::createFromFormat('!Y-m', $period)->startOfMonth();
            $endsAt = $startsAt->endOfMonth();

            return [
                'mode' => 'month',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'label' => $startsAt->format('m/Y'),
                'attendance_date' => $startsAt->toDateString(),
            ];
        }

        $date = CarbonImmutable::parse($period)->startOfDay();

        return [
            'mode' => 'date',
            'starts_at' => $date,
            'ends_at' => $date->endOfDay(),
            'label' => $date->toDateString(),
            'attendance_date' => $date->toDateString(),
        ];
    }

    private function sourceDatabase(): string
    {
        $database = trim((string) config('database.source_database', ''));

        if ($database === '') {
            throw ValidationException::withMessages([
                'source_database' => 'DB_SOURCE_DATABASE belum dikonfigurasi.',
            ]);
        }

        return $database;
    }

    private function sourceTable(string $table): string
    {
        return $this->sourceDatabase().'.'.$table;
    }

    private function assertSourceAvailable(): void
    {
        $exists = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $this->sourceDatabase())
            ->where('TABLE_NAME', self::SourceTable)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'source_database' => 'Tabel '.self::SourceTable.' tidak ditemukan pada database source.',
            ]);
        }
    }

    private function shortTime(mixed $time): ?string
    {
        if (! $this->hasText($time)) {
            return null;
        }

        $normalized = substr(trim((string) $time), 0, 5);

        return in_array($normalized, ['00:00'], true) ? null : $normalized;
    }

    private function presentPunchCount(object $source): int
    {
        return count(array_filter(
            array_map(
                fn (string $slot): ?string => $this->shortTime($source->{$slot} ?? null),
                self::SlotNames,
            ),
        ));
    }

    private function hasText(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }
}
