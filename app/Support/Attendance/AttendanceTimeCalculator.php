<?php

namespace App\Support\Attendance;

use Carbon\CarbonImmutable;

class AttendanceTimeCalculator
{
    public function __construct(
        private ?AttendancePolicySettings $policySettings = null,
    ) {}

    /**
     * @param  list<array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}>  $punches
     * @return array{
     *     worked_hours: float,
     *     overtime_hours: float,
     *     holiday_worked_hours: float,
     *     holiday_overtime_hours: float,
     *     requires_review: bool,
     *     notes: list<string>
     * }
     */
    public function calculate(AttendanceSchedule $schedule, array $punches): array
    {
        $firstIn = $punches[0]['punched_at'] ?? null;
        $firstOut = $punches[1]['punched_at'] ?? null;
        $secondIn = $punches[2]['punched_at'] ?? null;
        $secondOut = $punches[3]['punched_at'] ?? null;
        $overtimeIn = $punches[4]['punched_at'] ?? null;
        $overtimeOut = $punches[5]['punched_at'] ?? null;
        $notes = [];
        $requiresReview = false;

        if ($firstIn === null) {
            return $this->emptyResult();
        }

        $completenessIssues = $this->punchCompletenessIssues($punches);
        $requiresReview = $completenessIssues !== [];

        if (! isset($punches[3]) && ! isset($punches[5])) {
            return $this->emptyResult(
                $requiresReview,
                $completenessIssues,
            );
        }

        $notes = [...$notes, ...$completenessIssues];

        if ($schedule->expectedCheckIn->isSaturday()) {
            [$workedHours, $overtimeHours] = $this->saturdayHours(
                $schedule,
                $firstIn,
                $firstOut,
                $secondIn,
                $secondOut,
                $overtimeIn,
                $overtimeOut,
            );
        } elseif ($schedule->profile === 'security') {
            [$workedHours, $overtimeHours] = $this->securityHours(
                $schedule,
                $firstIn,
                $firstOut,
                $secondOut,
                $overtimeOut,
                $notes,
                $requiresReview,
            );
        } else {
            $regularStart = $firstIn->greaterThan($schedule->expectedCheckIn)
                ? $firstIn
                : $schedule->expectedCheckIn;
            $roundedRegularHours = 0.0;

            if ($firstOut !== null && $secondIn !== null) {
                $firstSegmentEnd = $this->minimum($firstOut, $schedule->expectedCheckOut);
                $roundedRegularHours += $this->floorToHalfHour(
                    $this->hoursBetween($regularStart, $firstSegmentEnd),
                );

                $secondSessionStart = $secondIn;
                $secondSessionEnd = $this->minimum(
                    $secondOut ?? $schedule->expectedCheckOut,
                    $schedule->expectedCheckOut,
                );
                $roundedRegularHours += $this->floorToHalfHour(
                    $this->hoursBetween(
                        $secondSessionStart,
                        $secondSessionEnd,
                    ),
                );
            } else {
                $regularEnd = $this->minimum(
                    $secondOut ?? $firstOut ?? $schedule->expectedCheckOut,
                    $schedule->expectedCheckOut,
                );
                $grossHours = $this->hoursBetween($regularStart, $regularEnd);
                $expectedGrossHours = $this->hoursBetween(
                    $schedule->expectedCheckIn,
                    $schedule->expectedCheckOut,
                );
                $breakHours = max(
                    0,
                    min($expectedGrossHours, $schedule->breakHours),
                );
                $roundedRegularHours = $this->floorToHalfHour(
                    max(0, $grossHours - $breakHours),
                );
                $notes[] = 'Punch istirahat tidak ditemukan; jam istirahat standar dikurangkan otomatis.';
            }

            $workedHours = min(
                $schedule->standardWorkHours,
                max(0, $roundedRegularHours),
            );
            $overtimeHours = $this->overtimeHours(
                $schedule,
                $secondOut,
                $overtimeIn,
                $overtimeOut,
                $notes,
                $requiresReview,
            );
        }

        if ($schedule->isHolidayPay) {
            $notes[] = $schedule->holidayName
                ? "Kerja pada libur {$schedule->holidayName}; jam kerja dan lembur dikali 2."
                : 'Kerja pada hari Minggu; jam kerja dan lembur dikali 2.';

            return [
                'worked_hours' => 0.0,
                'overtime_hours' => 0.0,
                'holiday_worked_hours' => (float) ($workedHours * 2),
                'holiday_overtime_hours' => (float) ($overtimeHours * 2),
                'requires_review' => $requiresReview,
                'notes' => $notes,
            ];
        }

        return [
            'worked_hours' => $workedHours,
            'overtime_hours' => $overtimeHours,
            'holiday_worked_hours' => 0.0,
            'holiday_overtime_hours' => 0.0,
            'requires_review' => $requiresReview,
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<int, array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null>  $punches
     */
    public function hasCompleteEntryAndExit(array $punches): bool
    {
        return $this->punchCompletenessIssues($punches) === [];
    }

    /**
     * @param  array<int, array{raw_value: string, punched_at: CarbonImmutable, is_next_day: bool, sequence: int}|null>  $punches
     * @return list<string>
     */
    public function punchCompletenessIssues(array $punches): array
    {
        $issues = [];
        $breakOutMissing = ! isset($punches[1]);
        $breakInMissing = ! isset($punches[2]);
        $overtimeInMissing = ! isset($punches[4]);
        $overtimeOutMissing = ! isset($punches[5]);

        if (! isset($punches[0])) {
            $issues[] = 'Punch masuk belum tersedia.';
        }

        if (! isset($punches[3])) {
            $issues[] = 'Punch keluar reguler (second out) belum tersedia.';
        }

        if ($breakOutMissing !== $breakInMissing) {
            $issues[] = 'Pasangan keluar dan masuk istirahat tidak lengkap.';
        }

        if ($overtimeInMissing !== $overtimeOutMissing) {
            $issues[] = 'Pasangan masuk dan keluar lembur tidak lengkap.';
        }

        return $issues;
    }

    public function expectedBreakReturnTime(
        AttendanceSchedule $schedule,
        ?CarbonImmutable $firstOut,
    ): ?CarbonImmutable {
        if ($firstOut === null) {
            return null;
        }

        if ($schedule->breakRules === []) {
            if ($schedule->breakWindowEnd === null) {
                return null;
            }

            $returnTime = $firstOut->startOfDay()
                ->setTimeFromTimeString($schedule->breakWindowEnd);

            return $returnTime->lessThanOrEqualTo($firstOut)
                ? $returnTime->addDay()
                : $returnTime;
        }

        $firstOutMinutes = ($firstOut->hour * 60) + $firstOut->minute;
        $closestRule = null;
        $closestDistance = null;

        foreach ($schedule->breakRules as $rule) {
            if (! is_array($rule) || ! isset($rule['out'], $rule['in'])) {
                continue;
            }

            [$outHour, $outMinute] = array_map('intval', explode(':', (string) $rule['out'], 2));
            $outMinutes = ($outHour * 60) + $outMinute;
            $distance = abs($firstOutMinutes - $outMinutes);
            $distance = min($distance, 1440 - $distance);

            if ($closestDistance === null || $distance < $closestDistance) {
                $closestRule = $rule;
                $closestDistance = $distance;
            }
        }

        if ($closestRule === null) {
            return null;
        }

        $returnTime = $firstOut->startOfDay()
            ->setTimeFromTimeString((string) $closestRule['in']);

        return $returnTime->lessThanOrEqualTo($firstOut)
            ? $returnTime->addDay()
            : $returnTime;
    }

    /**
     * @param  array{
     *     worked_hours: float,
     *     overtime_hours: float,
     *     holiday_worked_hours: float,
     *     holiday_overtime_hours: float,
     *     requires_review: bool,
     *     notes: list<string>
     * }  $summary
     * @return array{
     *     worked_hours: float,
     *     overtime_hours: float,
     *     holiday_worked_hours: float,
     *     holiday_overtime_hours: float,
     *     requires_review: bool,
     *     notes: list<string>
     * }
     */
    public function deductWorkHours(array $summary, int $penaltyHours): array
    {
        if ($penaltyHours <= 0) {
            return $summary;
        }

        if ($summary['holiday_worked_hours'] > 0) {
            $summary['holiday_worked_hours'] = max(
                0.0,
                $summary['holiday_worked_hours'] - ($penaltyHours * 2),
            );
        } else {
            $summary['worked_hours'] = max(
                0.0,
                $summary['worked_hours'] - $penaltyHours,
            );
        }

        return $summary;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function saturdayHours(
        AttendanceSchedule $schedule,
        CarbonImmutable $firstIn,
        ?CarbonImmutable $firstOut,
        ?CarbonImmutable $secondIn,
        ?CarbonImmutable $secondOut,
        ?CarbonImmutable $overtimeIn,
        ?CarbonImmutable $overtimeOut,
    ): array {
        $totalHours = $this->workedHoursFromSlots(
            $schedule,
            $firstIn,
            $firstOut,
            $secondIn,
            $secondOut,
            $overtimeIn,
            $overtimeOut,
        );
        $workedHours = min($schedule->standardWorkHours, $totalHours);
        $overtimeHours = $this->floorToHalfHour(max(0, $totalHours - $workedHours));

        return [$workedHours, $overtimeHours];
    }

    /**
     * @param  list<string>  $notes
     * @return array{0: float, 1: float}
     */
    private function securityHours(
        AttendanceSchedule $schedule,
        CarbonImmutable $firstIn,
        ?CarbonImmutable $firstOut,
        ?CarbonImmutable $secondOut,
        ?CarbonImmutable $overtimeOut,
        array &$notes,
        bool &$requiresReview,
    ): array {
        $regularStart = $this->maximum($firstIn, $schedule->expectedCheckIn);
        $lastOut = $this->latestPunch($firstOut, $secondOut, $overtimeOut);

        if ($lastOut === null) {
            $notes[] = 'Punch keluar Security tidak ditemukan.';
            $requiresReview = true;

            return [0.0, 0.0];
        }

        $totalHours = $this->floorToHalfHour(
            $this->hoursBetween($regularStart, $lastOut),
        );
        $workedHours = min($schedule->standardWorkHours, $totalHours);
        $overtimeThreshold = $schedule->expectedCheckIn->addHours(12);
        $overtimeHours = $this->floorToHalfHour(
            $this->hoursBetween(
                $this->maximum($regularStart, $overtimeThreshold),
                $lastOut,
            ),
        );
        $notes[] = 'Security: shift 12 jam dihitung 8 jam reguler dan kelebihan setelah 12 jam dihitung lembur.';

        return [$workedHours, $overtimeHours];
    }

    private function workedHoursFromSlots(
        AttendanceSchedule $schedule,
        CarbonImmutable $firstIn,
        ?CarbonImmutable $firstOut,
        ?CarbonImmutable $secondIn,
        ?CarbonImmutable $secondOut,
        ?CarbonImmutable $overtimeIn,
        ?CarbonImmutable $overtimeOut,
    ): float {
        $regularStart = $this->maximum($firstIn, $schedule->expectedCheckIn);

        if ($firstOut !== null && $secondIn !== null) {
            $workedHours = $this->floorToHalfHour(
                $this->hoursBetween($regularStart, $firstOut),
            );
            $secondSessionEnd = $secondOut ?? $schedule->expectedCheckOut;
            $workedHours += $this->floorToHalfHour(
                $this->hoursBetween($secondIn, $secondSessionEnd),
            );

            if ($overtimeOut !== null) {
                $overtimeStart = $this->maximum(
                    $overtimeIn ?? $secondSessionEnd,
                    $secondSessionEnd,
                );
                $workedHours += $this->floorToHalfHour(
                    $this->hoursBetween($overtimeStart, $overtimeOut),
                );
            }

            return $workedHours;
        }

        $lastOut = $this->latestPunch($firstOut, $secondOut, $overtimeOut);

        if ($lastOut === null) {
            return 0.0;
        }

        return $this->floorToHalfHour(max(
            0,
            $this->hoursBetween($regularStart, $lastOut) - $schedule->breakHours,
        ));
    }

    private function latestPunch(?CarbonImmutable ...$punches): ?CarbonImmutable
    {
        $latest = null;

        foreach ($punches as $punch) {
            if ($punch !== null && ($latest === null || $punch->greaterThan($latest))) {
                $latest = $punch;
            }
        }

        return $latest;
    }

    /**
     * @param  list<string>  $notes
     */
    private function overtimeHours(
        AttendanceSchedule $schedule,
        ?CarbonImmutable $secondOut,
        ?CarbonImmutable $overtimeIn,
        ?CarbonImmutable $overtimeOut,
        array &$notes,
        bool &$requiresReview,
    ): float {
        $overtimeHours = 0.0;

        if ($overtimeOut !== null) {
            $overtimeStart = $this->roundOvertimeStart(
                $this->maximum(
                    $overtimeIn ?? $schedule->expectedCheckOut,
                    $schedule->expectedCheckOut,
                ),
            );
            $overtimeHours += $this->floorToHalfHour(
                $this->hoursBetween($overtimeStart, $overtimeOut),
            );
        } elseif ($overtimeIn !== null) {
            $notes[] = 'Punch masuk lembur ditemukan tanpa punch keluar lembur.';
            $requiresReview = true;
        }

        if ($secondOut !== null && $secondOut->greaterThan($schedule->expectedCheckOut)) {
            $overtimeHours += $this->floorToHalfHour(
                $this->hoursBetween($schedule->expectedCheckOut, $secondOut),
            );
            $notes[] = 'Checkout kedua melewati jam kerja; kelebihan waktu dihitung sebagai lembur.';
        }

        return $overtimeHours;
    }

    private function roundOvertimeStart(CarbonImmutable $overtimeStart): CarbonImmutable
    {
        $minutes = ($overtimeStart->hour * 60) + $overtimeStart->minute;
        $halfHourSlotStartsAt = intdiv($minutes, 30) * 30;
        $minutesAfterSlot = $minutes - $halfHourSlotStartsAt;
        $toleranceMinutes = $this->policySettings?->toleranceMinutes()
            ?? $this->fallbackToleranceMinutes();
        $roundedMinutes = $minutesAfterSlot <= $toleranceMinutes
            ? $halfHourSlotStartsAt
            : $halfHourSlotStartsAt + 30;

        return $overtimeStart->startOfDay()->addMinutes($roundedMinutes);
    }

    private function fallbackToleranceMinutes(): int
    {
        try {
            return (int) config('attendance.tolerance_minutes', 3);
        } catch (\Throwable) {
            return 3;
        }
    }

    private function hoursBetween(CarbonImmutable $start, CarbonImmutable $end): float
    {
        if ($end->lessThanOrEqualTo($start)) {
            return 0.0;
        }

        return ($end->getTimestamp() - $start->getTimestamp()) / 3600;
    }

    private function floorToHalfHour(float $hours): float
    {
        return floor(($hours + 0.000001) * 2) / 2;
    }

    private function minimum(CarbonImmutable $first, CarbonImmutable $second): CarbonImmutable
    {
        return $first->lessThan($second) ? $first : $second;
    }

    private function maximum(CarbonImmutable $first, CarbonImmutable $second): CarbonImmutable
    {
        return $first->greaterThan($second) ? $first : $second;
    }

    /**
     * @param  list<string>  $notes
     * @return array{
     *     worked_hours: float,
     *     overtime_hours: float,
     *     holiday_worked_hours: float,
     *     holiday_overtime_hours: float,
     *     requires_review: bool,
     *     notes: list<string>
     * }
     */
    private function emptyResult(bool $requiresReview = false, array $notes = []): array
    {
        return [
            'worked_hours' => 0.0,
            'overtime_hours' => 0.0,
            'holiday_worked_hours' => 0.0,
            'holiday_overtime_hours' => 0.0,
            'requires_review' => $requiresReview,
            'notes' => $notes,
        ];
    }
}
