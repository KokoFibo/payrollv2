<?php

namespace App\Support\Attendance;

use App\Models\AttendanceImport;
use App\Models\AttendanceRecord;
use App\Models\Karyawan;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceImportService
{
    public function __construct(
        private AttendanceWorkbookReader $workbookReader,
        private AttendanceScheduleResolver $scheduleResolver,
        private AttendanceTimeCalculator $timeCalculator,
    ) {}

    public function import(UploadedFile $file, int $importedBy): AttendanceImport
    {
        try {
            $workbook = $this->workbookReader->read((string) $file->getRealPath());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'attendance_file' => $exception->getMessage(),
            ]);
        }

        $employees = $this->employeesByNip(
            array_column($workbook['employees'], 'source_user_id'),
        );

        if ($employees->isEmpty()) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Tidak ada NIP pada Excel yang cocok dengan data karyawan.',
            ]);
        }

        $summaries = [];
        $unmatchedUserIds = [];
        $duplicatePunchCount = 0;
        $missingPunchCount = 0;
        $lateCount = 0;
        $earlyLeaveCount = 0;
        $rawPunchCount = 0;

        foreach ($workbook['employees'] as $block) {
            $karyawan = $employees->get((string) $block['source_user_id']);

            if (! $karyawan instanceof Karyawan) {
                $unmatchedUserIds[] = $block['source_user_id'];

                continue;
            }

            $punches = $block['punches'];
            usort($punches, function (array $left, array $right): int {
                return [$left['punched_at']->getTimestamp(), $left['sequence']]
                    <=> [$right['punched_at']->getTimestamp(), $right['sequence']];
            });
            $effectivePunches = $this->effectivePunches($punches);
            $clockPunches = array_slice($effectivePunches, 0, 6);
            $firstPunch = $clockPunches[0]['punched_at'];
            $lastPunch = $clockPunches[array_key_last($clockPunches)]['punched_at'];
            $schedule = $this->scheduleResolver->resolve(
                $karyawan,
                $workbook['attendance_date'],
                $firstPunch,
            );
            $slotPunches = $this->clockSlotPunches($clockPunches, $schedule);
            $timeSummary = $this->timeCalculator->calculate(
                $schedule,
                array_values($slotPunches),
            );
            $lateMinutes = max(
                0,
                $this->minuteDifference($schedule->expectedCheckIn, $firstPunch),
            );
            $isLate = $lateMinutes > (int) config('attendance.tolerance_minutes', 3);
            $duplicateCount = max(0, count($punches) - count($effectivePunches));
            $hasMissingPunch = count($clockPunches) < $schedule->expectedPunchCount;
            $hasDuplicatePunch = $duplicateCount > 0;
            $ignoredPunchCount = max(0, count($effectivePunches) - 6);
            $checkOutDifference = $this->minuteDifference($schedule->expectedCheckOut, $lastPunch);
            $earlyLeaveMinutes = ! $hasMissingPunch ? max(0, -$checkOutDifference) : 0;
            $latePenaltyHours = $isLate && $this->isHourlyWorker($karyawan)
                ? (int) ceil($lateMinutes / 60)
                : null;
            $requiresReview = $hasMissingPunch || $timeSummary['requires_review'];
            $lastOutDayOffset = (int) $workbook['attendance_date']
                ->diffInDays($lastPunch->startOfDay());
            $notes = $this->comparisonNotes(
                $karyawan,
                $schedule,
                $lateMinutes,
                $isLate,
                $latePenaltyHours,
                $duplicateCount,
                $ignoredPunchCount,
                count($clockPunches),
                $hasMissingPunch,
                $earlyLeaveMinutes,
                $timeSummary['notes'],
            );

            $summaries[] = [
                'source_user_id' => (string) $block['source_user_id'],
                'karyawan_id' => $karyawan->id,
                'attendance_date' => $workbook['attendance_date']->toDateString(),
                'status' => $isLate ? 'late' : 'present',
                'shift' => $schedule->shift,
                'schedule_profile' => $schedule->profile,
                'first_in' => $this->punchTime($slotPunches, 'first_in'),
                'first_out' => $this->punchTime($slotPunches, 'first_out'),
                'second_in' => $this->punchTime($slotPunches, 'second_in'),
                'second_out' => $this->punchTime($slotPunches, 'second_out'),
                'overtime_in' => $this->punchTime($slotPunches, 'overtime_in'),
                'overtime_out' => $this->punchTime($slotPunches, 'overtime_out'),
                'second_in_day_offset' => $this->punchDayOffset($slotPunches, 'second_in'),
                'second_out_day_offset' => $this->punchDayOffset($slotPunches, 'second_out'),
                'overtime_in_day_offset' => $this->punchDayOffset($slotPunches, 'overtime_in'),
                'overtime_out_day_offset' => $this->punchDayOffset($slotPunches, 'overtime_out'),
                'expected_check_in' => $schedule->expectedCheckIn->format('H:i:s'),
                'expected_check_out' => $schedule->expectedCheckOut->format('H:i:s'),
                'last_out_day_offset' => $lastOutDayOffset,
                'worked_hours' => $timeSummary['worked_hours'],
                'worked_days' => 1,
                'overtime_hours' => $timeSummary['overtime_hours'],
                'holiday_worked_hours' => $timeSummary['holiday_worked_hours'],
                'holiday_overtime_hours' => $timeSummary['holiday_overtime_hours'],
                'raw_punch_count' => count($punches),
                'effective_punch_count' => count($clockPunches),
                'duplicate_punch_count' => $duplicateCount,
                'ignored_punch_count' => $ignoredPunchCount,
                'late_minutes' => $lateMinutes,
                'late_penalty_hours' => $latePenaltyHours,
                'early_leave_minutes' => $earlyLeaveMinutes,
                'has_missing_punch' => $hasMissingPunch,
                'has_duplicate_punch' => $hasDuplicatePunch,
                'requires_review' => $requiresReview,
                'comparison_notes' => $notes === [] ? null : implode(' ', $notes),
                'recorded_by' => $importedBy,
                'calendar_override_id' => $schedule->calendarOverrideId,
                'is_holiday_pay' => $schedule->isHolidayPay,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $rawPunchCount += count($punches);
            $duplicatePunchCount += $duplicateCount;
            $missingPunchCount += $hasMissingPunch ? 1 : 0;
            $lateCount += $isLate ? 1 : 0;
            $earlyLeaveCount += $earlyLeaveMinutes > 0 ? 1 : 0;
        }

        if ($summaries === []) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Tidak ada karyawan yang dapat diproses karena NIP tidak ditemukan.',
            ]);
        }

        $warnings = [];

        if ($unmatchedUserIds !== []) {
            $warnings[] = count($unmatchedUserIds)
                .' NIP pada Excel tidak ditemukan di data karyawan dan tidak diimpor.';
        }

        return DB::transaction(function () use (
            $file,
            $importedBy,
            $workbook,
            $summaries,
            $unmatchedUserIds,
            $warnings,
            $rawPunchCount,
            $duplicatePunchCount,
            $missingPunchCount,
            $lateCount,
            $earlyLeaveCount,
        ): AttendanceImport {
            $import = AttendanceImport::query()->create([
                'imported_by' => $importedBy,
                'original_filename' => $file->getClientOriginalName(),
                'file_size' => (int) ($file->getSize() ?: 0),
                'attendance_date' => $workbook['attendance_date']->toDateString(),
                'source_employee_count' => $workbook['source_employee_count'],
                'processed_employee_count' => count($summaries),
                'skipped_without_punch_count' => $workbook['skipped_without_punch_count'],
                'unmatched_employee_count' => count($unmatchedUserIds),
                'raw_punch_count' => $rawPunchCount,
                'duplicate_punch_count' => $duplicatePunchCount,
                'missing_punch_count' => $missingPunchCount,
                'late_count' => $lateCount,
                'early_leave_count' => $earlyLeaveCount,
                'unmatched_user_ids' => $unmatchedUserIds,
                'warnings' => $warnings,
            ]);

            $summaryRows = array_map(function (array $summary) use ($import): array {
                unset($summary['source_user_id']);
                $summary['attendance_import_id'] = $import->id;

                return $summary;
            }, $summaries);

            AttendanceRecord::query()->upsert(
                $summaryRows,
                ['karyawan_id', 'attendance_date'],
                [
                    'attendance_import_id',
                    'status',
                    'shift',
                    'schedule_profile',
                    'first_in',
                    'first_out',
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
                    'comparison_notes',
                    'recorded_by',
                    'calendar_override_id',
                    'is_holiday_pay',
                    'updated_at',
                ],
            );

            return $import->refresh();
        });
    }

    /**
     * @param  list<string>  $sourceUserIds
     * @return Collection<string, Karyawan>
     */
    private function employeesByNip(array $sourceUserIds): Collection
    {
        return Karyawan::query()
            ->select([
                'id',
                'nip',
                'nama',
                'department_id',
                'jabatan_id',
                'company_id',
                'directorate_id',
                'metode_penggajian',
            ])
            ->with([
                'department:id,name',
                'jabatan:id,name',
            ])
            ->whereIn('nip', array_values(array_unique($sourceUserIds)))
            ->get()
            ->keyBy(fn (Karyawan $karyawan): string => (string) $karyawan->nip);
    }

    /**
     * @param  list<array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}>  $punches
     * @return list<array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}>
     */
    private function effectivePunches(array $punches): array
    {
        $windowSeconds = (int) config('attendance.duplicate_punch_window_minutes', 10) * 60;
        $effective = [];
        $previousTimestamp = null;

        foreach ($punches as $punch) {
            $timestamp = $punch['punched_at']->getTimestamp();

            if ($previousTimestamp === null || $timestamp - $previousTimestamp >= $windowSeconds) {
                $effective[] = $punch;
            }

            $previousTimestamp = $timestamp;
        }

        return $effective;
    }

    /**
     * @param  list<array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}>  $punches
     * @return array{
     *     first_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     first_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     second_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     second_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     overtime_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     overtime_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null
     * }
     */
    private function clockSlotPunches(array $punches, AttendanceSchedule $schedule): array
    {
        $slots = [
            'first_in' => $punches[0] ?? null,
            'first_out' => $punches[1] ?? null,
            'second_in' => $punches[2] ?? null,
            'second_out' => null,
            'overtime_in' => null,
            'overtime_out' => null,
        ];

        if ($schedule->profile === 'security') {
            return $this->securityClockSlots($punches, $schedule);
        }

        $remainingPunches = array_slice($punches, 3);

        if (count($remainingPunches) >= 3) {
            $slots['second_out'] = $remainingPunches[0];
            $slots['overtime_in'] = $remainingPunches[1];
            $slots['overtime_out'] = $remainingPunches[2];
        } elseif (count($remainingPunches) === 2) {
            if ($remainingPunches[0]['punched_at']->greaterThan($schedule->expectedCheckOut)) {
                $slots['overtime_in'] = $remainingPunches[0];
                $slots['overtime_out'] = $remainingPunches[1];
            } else {
                $slots['second_out'] = $remainingPunches[0];
                $slots['overtime_in'] = $remainingPunches[1];
            }
        } elseif (count($remainingPunches) === 1) {
            $slots['second_out'] = $remainingPunches[0];
        }

        return $slots;
    }

    /**
     * @param  list<array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}>  $punches
     * @return array{
     *     first_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     first_out: null,
     *     second_in: null,
     *     second_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     overtime_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     overtime_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null
     * }
     */
    private function securityClockSlots(array $punches, AttendanceSchedule $schedule): array
    {
        $slots = [
            'first_in' => $punches[0] ?? null,
            'first_out' => null,
            'second_in' => null,
            'second_out' => null,
            'overtime_in' => null,
            'overtime_out' => null,
        ];

        if (count($punches) < 2) {
            return $slots;
        }

        $secondOutIndex = 1;
        $regularCheckoutThreshold = $schedule->expectedCheckOut->addMinutes(59);

        foreach (array_slice($punches, 1, null, true) as $index => $punch) {
            if ($punch['punched_at']->lessThanOrEqualTo($regularCheckoutThreshold)) {
                $secondOutIndex = $index;
            }
        }

        $slots['second_out'] = $punches[$secondOutIndex];
        $remaining = array_slice($punches, $secondOutIndex + 1);
        $slots['overtime_in'] = $remaining[0] ?? null;
        $slots['overtime_out'] = $remaining[1] ?? null;

        return $slots;
    }

    /**
     * @param  array{
     *     first_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     first_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     second_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     second_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     overtime_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     overtime_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null
     * }  $slots
     */
    private function punchTime(array $slots, string $slot): ?string
    {
        return isset($slots[$slot])
            ? $slots[$slot]['punched_at']->format('H:i:s')
            : null;
    }

    /**
     * @param  array{
     *     first_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     first_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     second_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     second_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     overtime_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     overtime_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null
     * }  $slots
     */
    private function punchDayOffset(array $slots, string $slot): int
    {
        return isset($slots[$slot]) && $slots[$slot]['is_next_day'] ? 1 : 0;
    }

    private function minuteDifference(CarbonImmutable $expected, CarbonImmutable $actual): int
    {
        return (int) round(($actual->getTimestamp() - $expected->getTimestamp()) / 60);
    }

    private function isHourlyWorker(Karyawan $karyawan): bool
    {
        return Str::contains(
            Str::lower((string) $karyawan->metode_penggajian),
            ['per jam', 'hourly'],
        );
    }

    /**
     * @param  list<string>  $calculationNotes
     * @return list<string>
     */
    private function comparisonNotes(
        Karyawan $karyawan,
        AttendanceSchedule $schedule,
        int $lateMinutes,
        bool $isLate,
        ?int $latePenaltyHours,
        int $duplicateCount,
        int $ignoredPunchCount,
        int $effectivePunchCount,
        bool $hasMissingPunch,
        int $earlyLeaveMinutes,
        array $calculationNotes,
    ): array {
        $notes = [];

        if ($duplicateCount > 0) {
            $notes[] = "Terdapat {$duplicateCount} punch berulang.";
        }

        if ($ignoredPunchCount > 0) {
            $notes[] = "{$ignoredPunchCount} punch tambahan di luar enam slot diabaikan.";
        }

        if ($hasMissingPunch) {
            $notes[] = "Punch efektif {$effectivePunchCount} dari {$schedule->expectedPunchCount}.";
        }

        if ($isLate) {
            $notes[] = "Terlambat {$lateMinutes} menit.";

            if ($latePenaltyHours !== null) {
                $notes[] = "Potongan {$latePenaltyHours} jam.";
            } else {
                $notes[] = 'Aturan potongan untuk metode penggajian '
                    .($karyawan->metode_penggajian ?: 'yang belum ditentukan')
                    .' belum tersedia.';
            }
        }

        if ($earlyLeaveMinutes > 0) {
            $notes[] = "Pulang {$earlyLeaveMinutes} menit lebih awal.";
        }

        return [
            ...$notes,
            ...$calculationNotes,
            ...$schedule->reviewNotes,
        ];
    }
}
