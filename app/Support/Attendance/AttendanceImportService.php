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
use Illuminate\Validation\ValidationException;

class AttendanceImportService
{
    public function __construct(
        private AttendanceWorkbookReader $workbookReader,
        private AttendanceScheduleResolver $scheduleResolver,
        private AttendanceTimeCalculator $timeCalculator,
        private AttendanceLatenessCalculator $latenessCalculator,
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

        $existingRecords = AttendanceRecord::query()
            ->where('attendance_date', $workbook['attendance_date']->toDateString())
            ->whereIn('karyawan_id', $employees->modelKeys())
            ->get()
            ->keyBy('karyawan_id');
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

            $sourcePunches = $block['punches'];
            $schedule = $this->scheduleResolver->resolve(
                $karyawan,
                $workbook['attendance_date'],
                $sourcePunches[0]['punched_at'],
            );
            $punches = $this->normalizeNightShiftPunches(
                $sourcePunches,
                $workbook['attendance_date'],
                $schedule,
            );
            usort($punches, function (array $left, array $right): int {
                return [$left['punched_at']->getTimestamp(), $left['sequence']]
                    <=> [$right['punched_at']->getTimestamp(), $right['sequence']];
            });
            $effectivePunches = $this->effectivePunches($punches);
            $clockPunches = array_slice($effectivePunches, 0, 6);
            $firstPunch = $clockPunches[0]['punched_at'];
            $lastPunch = $clockPunches[array_key_last($clockPunches)]['punched_at'];
            $slotPunches = $this->clockSlotPunches(
                $effectivePunches,
                $schedule,
            );
            $timeSummary = $this->timeCalculator->calculate(
                $schedule,
                array_values($slotPunches),
            );
            $lateness = $this->latenessCalculator->calculate(
                $schedule,
                $slotPunches['first_in']['punched_at'] ?? null,
                $slotPunches['first_out']['punched_at'] ?? null,
                $slotPunches['second_in']['punched_at'] ?? null,
                $slotPunches['second_out']['punched_at'] ?? null,
            );
            $lateMinutes = $lateness['total_minutes'];
            $isLate = $lateness['is_late'];
            $duplicateCount = max(0, count($punches) - count($effectivePunches));
            $hasMissingPunch = ! $this->timeCalculator->hasCompleteEntryAndExit(
                array_values($slotPunches),
            );
            $hasDuplicatePunch = $duplicateCount > 0;
            $ignoredPunchCount = max(
                0,
                count($effectivePunches) - count(array_filter($slotPunches)),
            );
            $securityCheckoutPunchCount = $this->securityCheckoutPunchCount(
                $effectivePunches,
                $schedule,
            );
            $checkOutDifference = $this->minuteDifference($schedule->expectedCheckOut, $lastPunch);
            $earlyLeaveMinutes = ! $hasMissingPunch ? max(0, -$checkOutDifference) : 0;
            $latePenaltyHours = $lateness['penalty_hours'] > 0
                ? $lateness['penalty_hours']
                : null;
            $timeSummary = $this->timeCalculator->deductWorkHours(
                $timeSummary,
                $lateness['penalty_hours'],
            );
            $existingRecord = $existingRecords->get($karyawan->id);
            $preservedReview = ($existingRecord?->requires_review ?? false)
                && ! $hasMissingPunch
                && ! $timeSummary['requires_review'];
            $requiresReview = $preservedReview
                || $hasMissingPunch
                || $securityCheckoutPunchCount >= 2
                || $timeSummary['requires_review'];
            $lastOutDayOffset = (int) $workbook['attendance_date']
                ->diffInDays($lastPunch->startOfDay());
            $notes = $this->comparisonNotes(
                $schedule,
                $lateness['notes'],
                $duplicateCount,
                $ignoredPunchCount,
                count($clockPunches),
                $hasMissingPunch,
                $earlyLeaveMinutes,
                $timeSummary['notes'],
            );

            if ($preservedReview) {
                $notes[] = 'Status review dipertahankan sampai admin menghapusnya secara manual.';
            }

            if ($securityCheckoutPunchCount >= 2) {
                $notes[] = "{$securityCheckoutPunchCount} punch berada di sekitar jam pulang Security; checkout perlu ditinjau.";
            }

            $summaries[] = [
                'source_user_id' => (string) $block['source_user_id'],
                'karyawan_id' => $karyawan->id,
                'attendance_date' => $workbook['attendance_date']->toDateString(),
                'status' => $isLate ? 'late' : 'present',
                'shift' => $schedule->shift,
                'schedule_profile' => $schedule->profile,
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
                'review_resolved_at' => $requiresReview
                    ? null
                    : $existingRecord?->review_resolved_at,
                'review_resolved_by' => $requiresReview
                    ? null
                    : $existingRecord?->review_resolved_by,
                'review_resolution_note' => $requiresReview
                    ? null
                    : $existingRecord?->review_resolution_note,
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
                ],
            );

            return $import->refresh();
        });
    }

    /**
     * @param  list<array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}>  $punches
     * @return list<array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}>
     */
    private function normalizeNightShiftPunches(
        array $punches,
        CarbonImmutable $attendanceDate,
        AttendanceSchedule $schedule,
    ): array {
        if ($schedule->shift !== 'night') {
            return $punches;
        }

        $previousPunch = null;

        foreach ($punches as &$punch) {
            $punchedAt = $attendanceDate
                ->addDays($punch['is_next_day'] ? 1 : 0)
                ->setTime($punch['punched_at']->hour, $punch['punched_at']->minute);

            if (
                $previousPunch !== null
                && ! $punch['is_next_day']
                && $punchedAt->lessThan($previousPunch)
            ) {
                $punchedAt = $punchedAt->addDay();
            }

            $punch['punched_at'] = $punchedAt;
            $punch['is_next_day'] = $punchedAt->toDateString() !== $attendanceDate->toDateString();
            $previousPunch = $punchedAt;
        }
        unset($punch);

        return $punches;
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
     * @param  list<array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}>  $effectivePunches
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
    private function clockSlotPunches(
        array $effectivePunches,
        AttendanceSchedule $schedule,
    ): array {
        if ($schedule->profile === 'security' && $schedule->expectedPunchCount <= 2) {
            return [
                'first_in' => $effectivePunches[0] ?? null,
                'first_out' => null,
                'second_in' => null,
                'second_out' => count($effectivePunches) >= 2
                    ? $effectivePunches[array_key_last($effectivePunches)]
                    : null,
                'overtime_in' => null,
                'overtime_out' => null,
            ];
        }

        $breakWindowSlots = $this->breakWindowClockSlots($effectivePunches, $schedule);

        if ($breakWindowSlots !== null) {
            return $breakWindowSlots;
        }

        $punches = array_slice($effectivePunches, 0, 6);
        $punchCount = count($punches);
        $slots = [
            'first_in' => $punches[0] ?? null,
            'first_out' => $punchCount >= 3 ? $punches[1] : null,
            'second_in' => $punchCount >= 3 ? $punches[2] : null,
            // Two punches represent entry and final exit; there is no lunch-out evidence.
            'second_out' => $punchCount === 2 ? $punches[1] : null,
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
     *     first_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     second_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     second_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     overtime_in: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null,
     *     overtime_out: array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null
     * }|null
     */
    private function breakWindowClockSlots(
        array $punches,
        AttendanceSchedule $schedule,
    ): ?array {
        if (
            count($punches) < 3
            || $schedule->breakWindowStart === null
            || $schedule->breakWindowEnd === null
        ) {
            return null;
        }

        $breakCandidates = [];

        foreach (array_slice($punches, 1, null, true) as $index => $punch) {
            if (! $this->isWithinBreakWindow(
                $punch['punched_at'],
                $schedule->breakWindowStart,
                $schedule->breakWindowEnd,
            )) {
                continue;
            }

            $breakCandidates[$punch['punched_at']->format('Y-m-d H:i')] = [
                'index' => $index,
                'punched_at' => $punch['punched_at'],
            ];
        }

        $breakCandidates = array_values($breakCandidates);

        if (count($breakCandidates) < 2) {
            return null;
        }

        $bestPair = null;

        for ($index = 1; $index < count($breakCandidates); $index++) {
            $previous = $breakCandidates[$index - 1];
            $current = $breakCandidates[$index];
            $gapMinutes = (int) round(
                ($current['punched_at']->getTimestamp() - $previous['punched_at']->getTimestamp()) / 60,
            );

            if ($gapMinutes < 5) {
                continue;
            }

            if ($bestPair === null || $gapMinutes >= $bestPair['gap_minutes']) {
                $bestPair = [
                    'first_out' => $previous,
                    'second_in' => $current,
                    'gap_minutes' => $gapMinutes,
                ];
            }
        }

        if ($bestPair === null) {
            return null;
        }

        $postBreakPunches = array_values(array_filter(
            $this->effectivePunches(
                array_slice($punches, $bestPair['second_in']['index'] + 1),
            ),
            fn (array $punch): bool => ! $this->isWithinBreakWindow(
                $punch['punched_at'],
                $schedule->breakWindowStart,
                $schedule->breakWindowEnd,
            ),
        ));
        $slots = [
            'first_in' => $punches[0],
            'first_out' => $punches[$bestPair['first_out']['index']],
            'second_in' => $punches[$bestPair['second_in']['index']],
            'second_out' => null,
            'overtime_in' => null,
            'overtime_out' => null,
        ];

        if (count($postBreakPunches) === 1) {
            $slots['second_out'] = $postBreakPunches[0];
        } elseif (count($postBreakPunches) === 2) {
            if ($postBreakPunches[0]['punched_at']->greaterThan(
                $schedule->expectedCheckOut,
            )) {
                $slots['overtime_in'] = $postBreakPunches[0];
                $slots['overtime_out'] = $postBreakPunches[1];
            } else {
                $slots['second_out'] = $postBreakPunches[0];
                $slots['overtime_in'] = $postBreakPunches[1];
            }
        } elseif (count($postBreakPunches) >= 3) {
            $slots['second_out'] = $postBreakPunches[0];
            $slots['overtime_in'] = $postBreakPunches[1];
            $slots['overtime_out'] = $postBreakPunches[2];
        }

        return $slots;
    }

    private function isWithinBreakWindow(
        CarbonImmutable $punchedAt,
        string $windowStart,
        string $windowEnd,
    ): bool {
        $punchMinutes = ($punchedAt->hour * 60) + $punchedAt->minute;
        [$startHour, $startMinute] = array_map('intval', explode(':', $windowStart, 2));
        [$endHour, $endMinute] = array_map('intval', explode(':', $windowEnd, 2));
        $startMinutes = ($startHour * 60) + $startMinute;
        $endMinutes = ($endHour * 60) + $endMinute;

        if ($startMinutes <= $endMinutes) {
            return $punchMinutes >= $startMinutes && $punchMinutes <= $endMinutes;
        }

        return $punchMinutes >= $startMinutes || $punchMinutes <= $endMinutes;
    }

    /**
     * @param  list<array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}>  $effectivePunches
     */
    private function securityCheckoutPunchCount(
        array $effectivePunches,
        AttendanceSchedule $schedule,
    ): int {
        if ($schedule->profile !== 'security' || $schedule->expectedPunchCount > 2) {
            return 0;
        }

        $windowMinutes = (int) config('attendance.checkout_review_window_minutes', 120);
        $windowStartsAt = $schedule->expectedCheckOut->subMinutes($windowMinutes);
        $windowEndsAt = $schedule->expectedCheckOut->addMinutes($windowMinutes);

        return count(array_filter(
            array_slice($effectivePunches, 1),
            fn (array $punch): bool => $punch['punched_at']->greaterThanOrEqualTo($windowStartsAt)
                && $punch['punched_at']->lessThanOrEqualTo($windowEndsAt),
        ));
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
        $punchCount = count($punches);
        $slots = [
            'first_in' => $punches[0] ?? null,
            'first_out' => $punchCount >= 3 ? $punches[1] : null,
            'second_in' => $punchCount >= 3 ? $punches[2] : null,
            'second_out' => $punchCount === 2 ? $punches[1] : null,
            'overtime_in' => null,
            'overtime_out' => null,
        ];

        if ($punchCount < 3) {
            return $slots;
        }

        $secondOutIndex = null;
        $regularCheckoutThreshold = $schedule->expectedCheckOut->addMinutes(59);

        foreach (array_slice($punches, 3, null, true) as $index => $punch) {
            if ($punch['punched_at']->lessThanOrEqualTo($regularCheckoutThreshold)) {
                $secondOutIndex = $index;
            }
        }

        if ($secondOutIndex !== null) {
            $slots['second_out'] = $punches[$secondOutIndex];
        }

        $remaining = array_slice(
            $punches,
            $secondOutIndex === null ? 3 : $secondOutIndex + 1,
        );
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

    /**
     * @param  list<string>  $calculationNotes
     * @return list<string>
     */
    private function comparisonNotes(
        AttendanceSchedule $schedule,
        array $latenessNotes,
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
            $notes[] = "{$ignoredPunchCount} punch tambahan diabaikan setelah pemetaan slot.";
        }

        if ($hasMissingPunch) {
            $notes[] = "Punch efektif {$effectivePunchCount} dari {$schedule->expectedPunchCount}.";
        }

        if ($earlyLeaveMinutes > 0) {
            $notes[] = "Pulang {$earlyLeaveMinutes} menit lebih awal.";
        }

        return [
            ...$notes,
            ...$latenessNotes,
            ...$calculationNotes,
            ...$schedule->reviewNotes,
        ];
    }
}
