<?php

namespace App\Support\Attendance;

use App\Models\AttendanceRecord;
use Carbon\CarbonImmutable;
use Throwable;

class AttendanceAnomalyScanner
{
    public function __construct(
        private AttendanceScheduleResolver $scheduleResolver,
        private AttendanceTimeCalculator $timeCalculator,
        private AttendancePolicySettings $policySettings,
    ) {}

    /**
     * @param  array{short_work_minutes: int, long_work_minutes: int, overtime_threshold_hours: float}  $options
     * @return array{
     *     period: array{start_date: string, end_date: string, days: int},
     *     summary: array{records: int, skipped_review: int, affected_records: int, affected_employees: int, critical: int, warning: int, info: int},
     *     results: list<array<string, mixed>>
     * }
     */
    public function scan(
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        array $options,
    ): array {
        $records = AttendanceRecord::query()
            ->whereBetween('attendance_date', [
                $startsOn->toDateString(),
                $endsOn->toDateString(),
            ])
            ->with([
                'karyawan:id,nip,nama,company_id,directorate_id,department_id,jabatan_id',
                'karyawan.company:id,name',
                'karyawan.directorate:id,name',
                'karyawan.department:id,name',
                'karyawan.jabatan:id,name',
                'calendarOverride:id,name,is_holiday_pay,schedule_day_type',
            ])
            ->lazyById(200);

        $results = [];
        $scannedRecordCount = 0;
        $skippedReviewCount = 0;

        foreach ($records as $record) {
            $scannedRecordCount++;

            if ($record->requires_review) {
                $skippedReviewCount++;

                continue;
            }

            array_push(
                $results,
                ...$this->inspectRecord($record, $options),
            );
        }

        usort($results, function (array $left, array $right): int {
            return [
                $this->severityOrder($left['severity']),
                $left['attendance_date'],
                $left['employee_name'],
                $left['code'],
            ] <=> [
                $this->severityOrder($right['severity']),
                $right['attendance_date'],
                $right['employee_name'],
                $right['code'],
            ];
        });

        $affectedRecordIds = array_values(array_unique(
            array_column($results, 'attendance_record_id'),
        ));
        $affectedEmployeeIds = array_values(array_unique(
            array_column($results, 'employee_id'),
        ));

        return [
            'period' => [
                'start_date' => $startsOn->toDateString(),
                'end_date' => $endsOn->toDateString(),
                'days' => (int) $startsOn->diffInDays($endsOn) + 1,
            ],
            'summary' => [
                'records' => $scannedRecordCount,
                'skipped_review' => $skippedReviewCount,
                'affected_records' => count($affectedRecordIds),
                'affected_employees' => count($affectedEmployeeIds),
                'critical' => count(array_filter(
                    $results,
                    fn (array $result): bool => $result['severity'] === 'critical',
                )),
                'warning' => count(array_filter(
                    $results,
                    fn (array $result): bool => $result['severity'] === 'warning',
                )),
                'info' => count(array_filter(
                    $results,
                    fn (array $result): bool => $result['severity'] === 'info',
                )),
            ],
            'results' => $results,
        ];
    }

    /**
     * @param  array{short_work_minutes: int, long_work_minutes: int, overtime_threshold_hours: float}  $options
     * @return list<array<string, mixed>>
     */
    private function inspectRecord(AttendanceRecord $record, array $options): array
    {
        $attendanceDate = $record->attendance_date->toImmutable()->startOfDay();
        $firstIn = $this->parseTime($attendanceDate, $record->first_in);
        $firstOut = $this->parseTime(
            $attendanceDate,
            $record->first_out,
            (int) $record->first_out_day_offset,
        );
        $secondIn = $this->parseTime($attendanceDate, $record->second_in);
        $secondOut = $this->parseTime(
            $attendanceDate,
            $record->second_out,
            (int) $record->second_out_day_offset,
        );
        $overtimeIn = $this->parseTime(
            $attendanceDate,
            $record->overtime_in,
            (int) $record->overtime_in_day_offset,
        );
        $overtimeOut = $this->parseTime(
            $attendanceDate,
            $record->overtime_out,
            (int) $record->overtime_out_day_offset,
        );
        $scheduleFirstPunch = $firstIn
            ?? $this->parseTime($attendanceDate, $record->expected_check_in)
            ?? $attendanceDate->setTime(8, 0);
        $schedule = $this->scheduleResolver->resolve(
            $record->karyawan,
            $attendanceDate,
            $scheduleFirstPunch,
        );
        $context = $this->scheduleContext($schedule);
        $anomalies = [];
        $workingStatus = in_array($record->status, ['present', 'late', 'remote'], true);
        $hasAnyPunch = $firstIn !== null
            || $firstOut !== null
            || $secondIn !== null
            || $secondOut !== null
            || $overtimeIn !== null
            || $overtimeOut !== null;

        $push = function (
            string $code,
            string $category,
            string $severity,
            string $message,
            string|int|float|null $expected = null,
            string|int|float|null $actual = null,
        ) use (&$anomalies, $record, $schedule, $context): void {
            $anomalies[] = [
                'id' => $record->id.':'.$code,
                'attendance_record_id' => $record->id,
                'employee_id' => $record->karyawan->id,
                'employee_name' => $record->karyawan->nama,
                'employee_nip' => $record->karyawan->nip,
                'department' => $record->karyawan->department?->name,
                'position' => $record->karyawan->jabatan?->name,
                'attendance_date' => $record->attendance_date->toDateString(),
                'status' => $record->status,
                'shift' => $schedule->shiftLabel(),
                'schedule_profile' => $schedule->profileLabel,
                'schedule_context' => $context,
                'expected_check_in' => $schedule->expectedCheckIn->format('H:i'),
                'expected_check_out' => $schedule->expectedCheckOut->format('H:i'),
                'standard_work_hours' => $schedule->standardWorkHours,
                'first_in' => $record->first_in ? substr($record->first_in, 0, 5) : null,
                'first_out' => $record->first_out ? substr($record->first_out, 0, 5) : null,
                'first_out_day_offset' => (int) $record->first_out_day_offset,
                'second_in' => $record->second_in ? substr($record->second_in, 0, 5) : null,
                'second_in_day_offset' => (int) $record->second_in_day_offset,
                'second_out' => $record->second_out ? substr($record->second_out, 0, 5) : null,
                'second_out_day_offset' => (int) $record->second_out_day_offset,
                'overtime_in' => $record->overtime_in ? substr($record->overtime_in, 0, 5) : null,
                'overtime_in_day_offset' => (int) $record->overtime_in_day_offset,
                'overtime_out' => $record->overtime_out ? substr($record->overtime_out, 0, 5) : null,
                'overtime_out_day_offset' => (int) $record->overtime_out_day_offset,
                'worked_hours' => (float) $record->worked_hours,
                'overtime_hours' => (float) $record->overtime_hours,
                'holiday_worked_hours' => (float) $record->holiday_worked_hours,
                'holiday_overtime_hours' => (float) $record->holiday_overtime_hours,
                'requires_review' => $record->requires_review,
                'code' => $code,
                'category' => $category,
                'severity' => $severity,
                'message' => $message,
                'expected' => $expected,
                'actual' => $actual,
            ];
        };

        if ($workingStatus && $firstIn === null) {
            $push(
                'FIRST_IN_MISSING',
                'punch',
                'critical',
                'Jam masuk tidak ditemukan pada attendance dengan status kehadiran.',
            );
        }

        if (! $workingStatus && $hasAnyPunch) {
            $push(
                'STATUS_PUNCH_MISMATCH',
                'status',
                'critical',
                'Attendance non-kehadiran memiliki data punch.',
                'Tanpa punch',
                'Ada punch',
            );
        }

        $storedExpectedIn = $record->expected_check_in
            ? substr($record->expected_check_in, 0, 5)
            : null;
        $storedExpectedOut = $record->expected_check_out
            ? substr($record->expected_check_out, 0, 5)
            : null;
        $activeExpectedIn = $schedule->expectedCheckIn->format('H:i');
        $activeExpectedOut = $schedule->expectedCheckOut->format('H:i');

        if (
            ($storedExpectedIn !== null && $storedExpectedIn !== $activeExpectedIn)
            || ($storedExpectedOut !== null && $storedExpectedOut !== $activeExpectedOut)
        ) {
            $push(
                'SCHEDULE_CONTEXT_CHANGED',
                'schedule',
                'warning',
                'Jadwal pada attendance berbeda dengan jadwal aktif saat ini.',
                "{$activeExpectedIn}-{$activeExpectedOut}",
                ($storedExpectedIn ?? '-').'-'.($storedExpectedOut ?? '-'),
            );
        }

        if ($record->shift !== null && $record->shift !== $schedule->shift) {
            $push(
                'SHIFT_CHANGED',
                'schedule',
                'warning',
                'Shift pada attendance berbeda dengan shift hasil jadwal aktif.',
                $schedule->shiftLabel(),
                $record->shift === 'night' ? 'Shift malam' : 'Shift pagi',
            );
        }

        if ($record->schedule_profile !== null && $record->schedule_profile !== $schedule->profile) {
            $push(
                'PROFILE_CHANGED',
                'schedule',
                'warning',
                'Profil jadwal pada attendance berbeda dengan profil karyawan saat ini.',
                $schedule->profileLabel,
                $record->schedule_profile,
            );
        }

        if ($workingStatus && $firstIn !== null) {
            $this->inspectPunchPlacement(
                $record,
                $schedule,
                $firstIn,
                $firstOut,
                $secondIn,
                $secondOut,
                $overtimeIn,
                $overtimeOut,
                $options,
                $push,
            );
            $this->inspectWorkHours(
                $record,
                $schedule,
                $options,
                $push,
            );
        }

        if ($record->ignored_punch_count > 0) {
            $push(
                'EXTRA_PUNCHES_IGNORED',
                'punch',
                'warning',
                'Terdapat punch tambahan yang diabaikan saat pemetaan slot.',
                'Sesuai pola jadwal',
                "{$record->ignored_punch_count} punch diabaikan",
            );
        }

        return $anomalies;
    }

    /**
     * @param  array{short_work_minutes: int, long_work_minutes: int, overtime_threshold_hours: float}  $options
     * @param  callable(string, string, string, string, string|int|float|null, string|int|float|null): void  $push
     */
    private function inspectPunchPlacement(
        AttendanceRecord $record,
        AttendanceSchedule $schedule,
        CarbonImmutable $firstIn,
        ?CarbonImmutable $firstOut,
        ?CarbonImmutable $secondIn,
        ?CarbonImmutable $secondOut,
        ?CarbonImmutable $overtimeIn,
        ?CarbonImmutable $overtimeOut,
        array $options,
        callable $push,
    ): void {
        $toleranceMinutes = $this->policySettings->toleranceMinutes();
        $firstInLateMinutes = max(
            0,
            (int) round(
                ($firstIn->getTimestamp() - $schedule->expectedCheckIn->getTimestamp()) / 60,
            ),
        );

        if ($firstInLateMinutes > $toleranceMinutes) {
            $push(
                'FIRST_IN_LATE',
                'schedule',
                'warning',
                'First in melewati toleransi jadwal masuk.',
                'Toleransi '.$toleranceMinutes.' menit',
                "{$firstInLateMinutes} menit",
            );
        }

        $expectedBreakReturn = $this->timeCalculator->expectedBreakReturnTime(
            $schedule,
            $firstOut,
        );
        $secondInLateMinutes = $secondIn !== null && $expectedBreakReturn !== null
            ? max(
                0,
                (int) round(
                    ($secondIn->getTimestamp() - $expectedBreakReturn->getTimestamp()) / 60,
                ),
            )
            : 0;

        if ($secondInLateMinutes > $toleranceMinutes) {
            $push(
                'SECOND_IN_LATE',
                'schedule',
                'warning',
                'Second in melewati toleransi jam kembali istirahat.',
                $expectedBreakReturn?->format('H:i').' + '.$toleranceMinutes.' menit',
                "{$secondInLateMinutes} menit",
            );
        }

        if ($schedule->expectedPunchCount >= 2 && $secondOut === null) {
            $push(
                'SECOND_OUT_MISSING',
                'punch',
                'critical',
                $overtimeIn !== null || $overtimeOut !== null
                    ? 'Second out reguler tidak ada meskipun terdapat punch lembur.'
                    : 'Second out reguler tidak ditemukan.',
                'Checkout akhir shift',
                'Kosong',
            );
        }

        if (($firstOut === null) !== ($secondIn === null)) {
            $push(
                'BREAK_PAIR_INCOMPLETE',
                'punch',
                'warning',
                'Pasangan keluar dan masuk istirahat tidak lengkap.',
                'First out dan second in',
                ($firstOut?->format('H:i') ?? '-').' / '.($secondIn?->format('H:i') ?? '-'),
            );
        }

        if ($firstOut !== null && $firstOut->lessThanOrEqualTo($firstIn)) {
            $push(
                'FIRST_OUT_ORDER',
                'punch',
                'critical',
                'First out tidak terjadi setelah first in.',
                'Setelah '.$firstIn->format('H:i'),
                $firstOut->format('H:i'),
            );
        }

        if ($secondIn !== null && $secondOut !== null && $secondOut->lessThanOrEqualTo($secondIn)) {
            $push(
                'SECOND_OUT_ORDER',
                'punch',
                'critical',
                'Second out tidak terjadi setelah second in.',
                'Setelah '.$secondIn->format('H:i'),
                $secondOut->format('H:i'),
            );
        }

        if ($schedule->breakWindowStart !== null) {
            $breakStartsAt = $record->attendance_date->toImmutable()
                ->startOfDay()
                ->setTimeFromTimeString($schedule->breakWindowStart);

            if ($firstOut !== null && $firstOut->lessThan($breakStartsAt)) {
                $minutes = (int) round(
                    ($breakStartsAt->getTimestamp() - $firstOut->getTimestamp()) / 60,
                );
                $push(
                    'FIRST_OUT_BEFORE_BREAK_WINDOW',
                    'schedule',
                    'warning',
                    'First out terjadi sebelum jendela istirahat.',
                    $breakStartsAt->format('H:i'),
                    $firstOut->format('H:i')." ({$minutes} menit lebih awal)",
                );
            }

            if (
                $secondIn !== null
                && ! $this->isWithinTimeWindow(
                    $secondIn,
                    $schedule->breakWindowStart,
                    $schedule->breakWindowEnd,
                )
            ) {
                $push(
                    'SECOND_IN_OUTSIDE_BREAK_WINDOW',
                    'schedule',
                    'warning',
                    'Second in berada di luar jendela istirahat.',
                    $schedule->breakWindowStart.'-'.($schedule->breakWindowEnd ?? '-'),
                    $secondIn->format('H:i'),
                );
            }

            if (
                $secondOut !== null
                && $this->isWithinTimeWindow(
                    $secondOut,
                    $schedule->breakWindowStart,
                    $schedule->breakWindowEnd,
                )
            ) {
                $push(
                    'SECOND_OUT_IN_BREAK_WINDOW',
                    'schedule',
                    'critical',
                    'Second out berada di jendela istirahat, bukan di jam pulang akhir shift.',
                    'Sekitar '.$schedule->expectedCheckOut->format('H:i'),
                    $secondOut->format('H:i'),
                );
            }
        }

        if ($secondOut !== null) {
            $checkoutDifference = (int) round(
                ($secondOut->getTimestamp() - $schedule->expectedCheckOut->getTimestamp()) / 60,
            );
            $checkoutTolerance = (int) config('attendance.anomaly_scan.checkout_proximity_minutes', 30);
            $maximumCheckoutDelay = (int) round($options['overtime_threshold_hours'] * 60);

            if ($schedule->profile !== 'security' && $checkoutDifference < -$checkoutTolerance) {
                $push(
                    'SECOND_OUT_FAR_FROM_CHECKOUT',
                    'work_hours',
                    'warning',
                    'Second out terlalu awal dari jadwal pulang.',
                    $schedule->expectedCheckOut->format('H:i'),
                    $secondOut->format('H:i'),
                );
            } elseif ($checkoutDifference > $maximumCheckoutDelay) {
                $push(
                    'SECOND_OUT_FAR_AFTER_CHECKOUT',
                    'overtime',
                    'warning',
                    'Second out lebih dari batas lembur setelah jadwal pulang.',
                    'Maksimal '.$options['overtime_threshold_hours'].' jam setelah '.$schedule->expectedCheckOut->format('H:i'),
                    $secondOut->format('H:i'),
                );
            }
        }

        if (($overtimeIn === null) !== ($overtimeOut === null)) {
            $push(
                'OVERTIME_PAIR_INCOMPLETE',
                'overtime',
                'critical',
                'Pasangan masuk dan keluar lembur tidak lengkap.',
                'Overtime in dan overtime out',
                ($overtimeIn?->format('H:i') ?? '-').' / '.($overtimeOut?->format('H:i') ?? '-'),
            );
        }

        if ($overtimeIn !== null && $overtimeOut !== null && $overtimeOut->lessThanOrEqualTo($overtimeIn)) {
            $push(
                'OVERTIME_ORDER',
                'overtime',
                'critical',
                'Overtime out tidak terjadi setelah overtime in.',
                'Setelah '.$overtimeIn->format('H:i'),
                $overtimeOut->format('H:i'),
            );
        }
    }

    /**
     * @param  array{short_work_minutes: int, long_work_minutes: int, overtime_threshold_hours: float}  $options
     * @param  callable(string, string, string, string, string|int|float|null, string|int|float|null): void  $push
     */
    private function inspectWorkHours(
        AttendanceRecord $record,
        AttendanceSchedule $schedule,
        array $options,
        callable $push,
    ): void {
        $regularHours = $record->is_holiday_pay
            ? (float) $record->holiday_worked_hours / 2
            : (float) $record->worked_hours;
        $overtimeHours = $record->is_holiday_pay
            ? (float) $record->holiday_overtime_hours / 2
            : (float) $record->overtime_hours;
        $standardHours = $schedule->standardWorkHours;

        if ($regularHours <= 0) {
            $push(
                'WORK_HOURS_ZERO',
                'work_hours',
                'critical',
                'Jam kerja tercatat nol meskipun karyawan memiliki punch masuk.',
                $standardHours,
                $regularHours,
            );
        } elseif ($regularHours < $standardHours - ($options['short_work_minutes'] / 60)) {
            $push(
                'WORK_HOURS_SHORT',
                'work_hours',
                'warning',
                'Jam kerja reguler jauh di bawah standar jadwal aktif.',
                $standardHours,
                $regularHours,
            );
        } elseif ($regularHours > $standardHours + ($options['long_work_minutes'] / 60)) {
            $push(
                'WORK_HOURS_LONG',
                'work_hours',
                'warning',
                'Jam kerja reguler jauh di atas standar jadwal aktif.',
                $standardHours,
                $regularHours,
            );
        }

        if ($overtimeHours > $options['overtime_threshold_hours']) {
            $push(
                'OVERTIME_EXCESSIVE',
                'overtime',
                'warning',
                'Jam lembur melebihi batas wajar.',
                $options['overtime_threshold_hours'],
                $overtimeHours,
            );
        }
    }

    private function parseTime(
        CarbonImmutable $attendanceDate,
        ?string $time,
        int $dayOffset = 0,
    ): ?CarbonImmutable {
        if ($time === null || $time === '') {
            return null;
        }

        try {
            return $attendanceDate
                ->addDays($dayOffset)
                ->setTimeFromTimeString($time);
        } catch (Throwable) {
            return null;
        }
    }

    private function isWithinTimeWindow(
        CarbonImmutable $value,
        string $windowStart,
        ?string $windowEnd,
    ): bool {
        if ($windowEnd === null) {
            return false;
        }

        $valueMinutes = ($value->hour * 60) + $value->minute;
        [$startHour, $startMinute] = array_map('intval', explode(':', $windowStart, 2));
        [$endHour, $endMinute] = array_map('intval', explode(':', $windowEnd, 2));
        $startMinutes = ($startHour * 60) + $startMinute;
        $endMinutes = ($endHour * 60) + $endMinute;

        if ($startMinutes <= $endMinutes) {
            return $valueMinutes >= $startMinutes && $valueMinutes <= $endMinutes;
        }

        return $valueMinutes >= $startMinutes || $valueMinutes <= $endMinutes;
    }

    private function scheduleContext(AttendanceSchedule $schedule): string
    {
        if ($schedule->specialScheduleName !== null) {
            return 'Jadwal khusus: '.$schedule->specialScheduleName;
        }

        if ($schedule->calendarOverrideName !== null) {
            return 'Pengecualian kalender: '.$schedule->calendarOverrideName;
        }

        if ($schedule->isHolidayPay) {
            return 'Libur: '.($schedule->holidayName ?? 'Hari libur');
        }

        return 'Reguler';
    }

    private function severityOrder(string $severity): int
    {
        return match ($severity) {
            'critical' => 0,
            'warning' => 1,
            default => 2,
        };
    }
}
