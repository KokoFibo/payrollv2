<?php

namespace App\Support\Attendance;

use Carbon\CarbonImmutable;

class AttendanceTimeCalculator
{
    private const SaturdayNightDoubleOvertimeMultiplier = 2.0;

    private const SaturdayNightSingleOvertimeMultiplier = 1.0;

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
     *     night_shift_allowance: float,
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

        if ($schedule->profile === 'security') {
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
            if ($firstOut === null || $secondIn === null) {
                $notes[] = 'Punch istirahat tidak ditemukan; jam kerja memakai batas maksimal dan record masuk review.';
            }

            $workedHours = $schedule->standardWorkHours;
            $notes[] = 'Jam kerja reguler memakai batas maksimal '
                .$schedule->standardWorkHours
                .' jam sebelum potongan keterlambatan atau pulang awal.';

            $overtimeHours = $this->overtimeHours(
                $schedule,
                $secondOut,
                $overtimeIn,
                $overtimeOut,
                $notes,
                $requiresReview,
            );
        }

        $nightShiftAllowance = $schedule->shift === 'night' && $workedHours > 0
            ? 1.0
            : 0.0;

        if ($nightShiftAllowance > 0) {
            $notes[] = 'Shift malam: tambahan allowance 1 jam lembur.';
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
                'night_shift_allowance' => $nightShiftAllowance,
                'requires_review' => $requiresReview,
                'notes' => $notes,
            ];
        }

        return [
            'worked_hours' => $workedHours,
            'overtime_hours' => $overtimeHours,
            'holiday_worked_hours' => 0.0,
            'holiday_overtime_hours' => 0.0,
            'night_shift_allowance' => $nightShiftAllowance,
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
     *     night_shift_allowance: float,
     *     requires_review: bool,
     *     notes: list<string>
     * }  $summary
     * @return array{
     *     worked_hours: float,
     *     overtime_hours: float,
     *     holiday_worked_hours: float,
     *     holiday_overtime_hours: float,
     *     night_shift_allowance: float,
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

        $workedHours = $schedule->standardWorkHours;
        $notes[] = 'Security: jam kerja reguler memakai batas maksimal '
            .$schedule->standardWorkHours
            .' jam.';

        $overtimeStart = $this->maximum(
            $regularStart,
            $schedule->expectedCheckOut,
        );
        $isSaturdayNight = $schedule->shift === 'night'
            && $schedule->expectedCheckIn->isSaturday();
        $overtimeHours = $isSaturdayNight
            ? $this->saturdayNightOvertimeHours(
                $schedule,
                $overtimeStart,
                $lastOut,
                $notes,
            )
            : $this->floorToHalfHour(
                $this->hoursBetween($overtimeStart, $lastOut),
            );

        if (! $isSaturdayNight) {
            $notes[] = 'Security: jam reguler mengikuti batas shift dan kelebihan setelah jadwal pulang dihitung lembur.';
        }

        return [$workedHours, $overtimeHours];
    }

    /**
     * @param  list<string>  $notes
     */
    private function saturdayNightOvertimeHours(
        AttendanceSchedule $schedule,
        CarbonImmutable $overtimeStart,
        CarbonImmutable $lastOut,
        array &$notes,
    ): float {
        $doubleRateEndsAt = $schedule->expectedCheckOut->setTime(5, 0);
        $doubleRateHours = $this->floorToHalfHour(
            $this->hoursBetween(
                $overtimeStart,
                $this->minimum($lastOut, $doubleRateEndsAt),
            ),
        );
        $singleRateHours = $this->floorToHalfHour(
            $this->hoursBetween(
                $this->maximum($overtimeStart, $doubleRateEndsAt),
                $lastOut,
            ),
        );
        $notes[] = 'Security Sabtu malam: lembur pukul 00:00-05:00 dikali 2, setelah 05:00 dikali 1.';

        return ($doubleRateHours * self::SaturdayNightDoubleOvertimeMultiplier)
            + ($singleRateHours * self::SaturdayNightSingleOvertimeMultiplier);
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
     *     night_shift_allowance: float,
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
            'night_shift_allowance' => 0.0,
            'requires_review' => $requiresReview,
            'notes' => $notes,
        ];
    }
}
