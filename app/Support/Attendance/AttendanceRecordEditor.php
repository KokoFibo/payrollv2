<?php

namespace App\Support\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Karyawan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class AttendanceRecordEditor
{
    public function __construct(
        private AttendanceScheduleResolver $scheduleResolver,
        private AttendanceTimeCalculator $timeCalculator,
    ) {}

    /**
     * @param  array{
     *     first_in?: string|null,
     *     first_out?: string|null,
     *     second_in?: string|null,
     *     second_out?: string|null,
     *     overtime_in?: string|null,
     *     overtime_out?: string|null
     * }  $slotTimes
     */
    public function save(
        Karyawan $karyawan,
        CarbonImmutable $attendanceDate,
        string $status,
        array $slotTimes,
        int $recordedBy,
        ?AttendanceRecord $existingRecord = null,
    ): AttendanceRecord {
        $slotNames = [
            'first_in',
            'first_out',
            'second_in',
            'second_out',
            'overtime_in',
            'overtime_out',
        ];
        $slots = [];
        $previousPunch = null;

        foreach ($slotNames as $slotName) {
            $time = $slotTimes[$slotName] ?? null;

            if ($time === null || $time === '') {
                $slots[$slotName] = null;

                continue;
            }

            $punchedAt = $attendanceDate->setTimeFromTimeString($time);

            if ($previousPunch !== null && $punchedAt->lessThanOrEqualTo($previousPunch)) {
                $punchedAt = $punchedAt->addDay();
            }

            $slots[$slotName] = [
                'raw_value' => $time,
                'punched_at' => $punchedAt,
                'is_next_day' => $punchedAt->toDateString() !== $attendanceDate->toDateString(),
                'sequence' => count(array_filter($slots)) + 1,
            ];
            $previousPunch = $punchedAt;
        }

        $punches = array_values($slots);
        $firstPunch = collect($slots)->filter()->first()['punched_at']
            ?? $attendanceDate->setTime(8, 0);
        $schedule = $this->scheduleResolver->resolve(
            $karyawan,
            $attendanceDate,
            $firstPunch,
        );
        $timeSummary = $this->timeCalculator->calculate($schedule, $punches);
        $lastPunch = collect($slots)->filter()->last()['punched_at'] ?? null;
        $lateMinutes = max(
            0,
            (int) round(
                ($firstPunch->getTimestamp() - $schedule->expectedCheckIn->getTimestamp()) / 60,
            ),
        );
        $isLate = $lateMinutes > (int) config('attendance.tolerance_minutes', 3);
        $presentSlotCount = collect($slots)->filter()->count();
        $rawPunchCount = $existingRecord?->raw_punch_count ?? $presentSlotCount;
        $duplicatePunchCount = $existingRecord?->duplicate_punch_count ?? 0;
        $ignoredPunchCount = $existingRecord?->ignored_punch_count ?? 0;
        $hasDuplicatePunch = $existingRecord?->has_duplicate_punch ?? false;
        $hasMissingPunch = $presentSlotCount > 0
            ? $presentSlotCount < $schedule->expectedPunchCount
            : in_array($status, ['present', 'late', 'remote'], true);
        $earlyLeaveMinutes = $lastPunch !== null && ! $hasMissingPunch
            ? max(0, (int) round(
                ($schedule->expectedCheckOut->getTimestamp() - $lastPunch->getTimestamp()) / 60,
            ))
            : 0;
        $latePenaltyHours = $isLate && $this->isHourlyWorker($karyawan)
            ? (int) ceil($lateMinutes / 60)
            : null;
        $comparisonNotes = [];

        if ($duplicatePunchCount > 0) {
            $comparisonNotes[] = "Terdapat {$duplicatePunchCount} punch berulang.";
        }

        if ($ignoredPunchCount > 0) {
            $comparisonNotes[] = "{$ignoredPunchCount} punch tambahan di luar enam slot diabaikan.";
        }

        $comparisonNotes = [
            ...$comparisonNotes,
            ...$timeSummary['notes'],
            ...$schedule->reviewNotes,
        ];

        return AttendanceRecord::query()->updateOrCreate(
            [
                'karyawan_id' => $karyawan->id,
                'attendance_date' => $attendanceDate->toDateString(),
            ],
            [
                'status' => $status,
                'shift' => $schedule->shift,
                'schedule_profile' => $schedule->profile,
                'first_in' => $this->slotTime($slots, 'first_in'),
                'first_out' => $this->slotTime($slots, 'first_out'),
                'second_in' => $this->slotTime($slots, 'second_in'),
                'second_out' => $this->slotTime($slots, 'second_out'),
                'overtime_in' => $this->slotTime($slots, 'overtime_in'),
                'overtime_out' => $this->slotTime($slots, 'overtime_out'),
                'second_in_day_offset' => $this->slotDayOffset($slots, 'second_in'),
                'second_out_day_offset' => $this->slotDayOffset($slots, 'second_out'),
                'overtime_in_day_offset' => $this->slotDayOffset($slots, 'overtime_in'),
                'overtime_out_day_offset' => $this->slotDayOffset($slots, 'overtime_out'),
                'expected_check_in' => $schedule->expectedCheckIn->format('H:i:s'),
                'expected_check_out' => $schedule->expectedCheckOut->format('H:i:s'),
                'last_out_day_offset' => $lastPunch === null
                    ? 0
                    : (int) $attendanceDate->diffInDays($lastPunch->startOfDay()),
                'worked_hours' => $timeSummary['worked_hours'],
                'worked_days' => $presentSlotCount > 0 ? 1 : 0,
                'overtime_hours' => $timeSummary['overtime_hours'],
                'holiday_worked_hours' => $timeSummary['holiday_worked_hours'],
                'holiday_overtime_hours' => $timeSummary['holiday_overtime_hours'],
                'raw_punch_count' => $rawPunchCount,
                'effective_punch_count' => $presentSlotCount,
                'duplicate_punch_count' => $duplicatePunchCount,
                'ignored_punch_count' => $ignoredPunchCount,
                'late_minutes' => $lateMinutes,
                'late_penalty_hours' => $latePenaltyHours,
                'early_leave_minutes' => $earlyLeaveMinutes,
                'has_missing_punch' => $hasMissingPunch,
                'has_duplicate_punch' => $hasDuplicatePunch,
                'requires_review' => $hasMissingPunch
                    || $timeSummary['requires_review'],
                'comparison_notes' => $comparisonNotes === []
                    ? null
                    : implode(' ', $comparisonNotes),
                'recorded_by' => $existingRecord?->recorded_by ?? $recordedBy,
                'calendar_override_id' => $schedule->calendarOverrideId,
                'is_holiday_pay' => $schedule->isHolidayPay,
            ],
        );
    }

    public function recalculate(
        AttendanceRecord $record,
        int $recordedBy,
    ): AttendanceRecord {
        $record->loadMissing([
            'karyawan.company',
            'karyawan.directorate',
            'karyawan.department',
            'karyawan.jabatan',
        ]);

        return $this->save(
            karyawan: $record->karyawan,
            attendanceDate: $record->attendance_date->toImmutable(),
            status: $record->status,
            slotTimes: [
                'first_in' => $record->first_in,
                'first_out' => $record->first_out,
                'second_in' => $record->second_in,
                'second_out' => $record->second_out,
                'overtime_in' => $record->overtime_in,
                'overtime_out' => $record->overtime_out,
            ],
            recordedBy: $recordedBy,
            existingRecord: $record,
        );
    }

    private function slotTime(array $slots, string $slot): ?string
    {
        return isset($slots[$slot])
            ? $slots[$slot]['punched_at']->format('H:i:s')
            : null;
    }

    private function slotDayOffset(array $slots, string $slot): int
    {
        return isset($slots[$slot]) && $slots[$slot]['is_next_day'] ? 1 : 0;
    }

    private function isHourlyWorker(Karyawan $karyawan): bool
    {
        return Str::contains(
            Str::lower((string) $karyawan->metode_penggajian),
            ['per jam', 'hourly'],
        );
    }
}
