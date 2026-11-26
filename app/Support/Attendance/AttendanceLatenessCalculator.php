<?php

namespace App\Support\Attendance;

use Carbon\CarbonImmutable;

class AttendanceLatenessCalculator
{
    public function __construct(
        private AttendancePolicySettings $policySettings,
        private AttendanceTimeCalculator $timeCalculator,
    ) {}

    /**
     * @return array{
     *     tolerance_minutes: int,
     *     first_in_minutes: int,
     *     first_out_early_minutes: int,
     *     second_in_minutes: int,
     *     second_out_early_minutes: int,
     *     total_minutes: int,
     *     penalty_hours: int,
     *     is_late: bool,
     *     notes: list<string>
     * }
     */
    public function calculate(
        AttendanceSchedule $schedule,
        ?CarbonImmutable $firstIn,
        ?CarbonImmutable $firstOut,
        ?CarbonImmutable $secondIn,
        ?CarbonImmutable $secondOut,
    ): array {
        $toleranceMinutes = $this->policySettings->toleranceMinutes();
        $firstInMinutes = $this->toleratedLateMinutes(
            $schedule->expectedCheckIn,
            $firstIn,
            $toleranceMinutes,
        );
        $firstOutEarlyMinutes = $this->outEarlyMinutes($schedule, $firstOut);
        $expectedBreakReturn = $this->timeCalculator->expectedBreakReturnTime(
            $schedule,
            $firstOut,
        );
        $secondInMinutes = $this->toleratedLateMinutes(
            $expectedBreakReturn,
            $secondIn,
            $toleranceMinutes,
        );
        $secondOutEarlyMinutes = $this->checkoutEarlyMinutes(
            $schedule->expectedCheckOut,
            $secondOut,
        );
        $totalMinutes = $firstInMinutes
            + $firstOutEarlyMinutes
            + $secondInMinutes
            + $secondOutEarlyMinutes;
        $notes = [];

        if ($firstInMinutes > 0) {
            $notes[] = "Terlambat masuk {$firstInMinutes} menit.";
        }

        if ($firstOutEarlyMinutes > 0) {
            $notes[] = "Keluar istirahat {$firstOutEarlyMinutes} menit lebih awal.";
        }

        if ($secondInMinutes > 0) {
            $notes[] = "Terlambat masuk istirahat {$secondInMinutes} menit.";
        }

        if ($secondOutEarlyMinutes > 0) {
            $notes[] = "Checkout {$secondOutEarlyMinutes} menit lebih awal.";
        }

        return [
            'tolerance_minutes' => $toleranceMinutes,
            'first_in_minutes' => $firstInMinutes,
            'first_out_early_minutes' => $firstOutEarlyMinutes,
            'second_in_minutes' => $secondInMinutes,
            'second_out_early_minutes' => $secondOutEarlyMinutes,
            'total_minutes' => $totalMinutes,
            'penalty_hours' => $totalMinutes > 0 ? (int) ceil($totalMinutes / 60) : 0,
            'is_late' => $totalMinutes > 0,
            'notes' => $notes,
        ];
    }

    private function toleratedLateMinutes(
        ?CarbonImmutable $expected,
        ?CarbonImmutable $actual,
        int $toleranceMinutes,
    ): int {
        if ($expected === null || $actual === null) {
            return 0;
        }

        $lateMinutes = max(
            0,
            (int) round(($actual->getTimestamp() - $expected->getTimestamp()) / 60),
        );

        return $lateMinutes > $toleranceMinutes ? $lateMinutes : 0;
    }

    private function outEarlyMinutes(
        AttendanceSchedule $schedule,
        ?CarbonImmutable $firstOut,
    ): int {
        if ($firstOut === null || $schedule->breakWindowStart === null) {
            return 0;
        }

        $breakStartsAt = $schedule->expectedCheckIn
            ->startOfDay()
            ->setTimeFromTimeString($schedule->breakWindowStart);

        return max(
            0,
            (int) round(($breakStartsAt->getTimestamp() - $firstOut->getTimestamp()) / 60),
        );
    }

    private function checkoutEarlyMinutes(
        CarbonImmutable $expectedCheckOut,
        ?CarbonImmutable $secondOut,
    ): int {
        if ($secondOut === null) {
            return 0;
        }

        return max(
            0,
            (int) round(($expectedCheckOut->getTimestamp() - $secondOut->getTimestamp()) / 60),
        );
    }
}
