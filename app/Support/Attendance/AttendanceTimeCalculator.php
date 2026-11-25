<?php

namespace App\Support\Attendance;

use Carbon\CarbonImmutable;

class AttendanceTimeCalculator
{
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
            $breakHours = $schedule->profile === 'security'
                ? 0
                : max(0, $expectedGrossHours - $schedule->standardWorkHours);
            $roundedRegularHours = $this->floorToHalfHour(
                max(0, $grossHours - $breakHours),
            );
            if ($schedule->profile !== 'security') {
                $notes[] = 'Punch istirahat tidak lengkap; jam istirahat standar dikurangkan otomatis.';
                $requiresReview = true;
            }
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
            $overtimeStart = $this->maximum(
                $overtimeIn ?? $schedule->expectedCheckOut,
                $schedule->expectedCheckOut,
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
     * @return array{
     *     worked_hours: float,
     *     overtime_hours: float,
     *     holiday_worked_hours: float,
     *     holiday_overtime_hours: float,
     *     requires_review: bool,
     *     notes: list<string>
     * }
     */
    private function emptyResult(): array
    {
        return [
            'worked_hours' => 0.0,
            'overtime_hours' => 0.0,
            'holiday_worked_hours' => 0.0,
            'holiday_overtime_hours' => 0.0,
            'requires_review' => false,
            'notes' => [],
        ];
    }
}
