<?php

namespace App\Support\Attendance;

use App\Models\Karyawan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

class AttendanceScheduleResolver
{
    public function __construct(
        private ?SpecialWorkScheduleResolver $specialWorkScheduleResolver = null,
        private ?NationalHolidayResolver $nationalHolidayResolver = null,
        private ?CalendarOverrideResolver $calendarOverrideResolver = null,
    ) {}

    public function resolve(
        Karyawan $karyawan,
        CarbonImmutable $attendanceDate,
        CarbonImmutable $firstPunch,
    ): AttendanceSchedule {
        $profile = $this->profileFor($karyawan);
        $calendarOverride = $this->calendarOverrideResolver?->findFor(
            $karyawan,
            $attendanceDate,
        );
        $nationalHoliday = $this->nationalHolidayResolver?->findFor($attendanceDate);
        $isHolidayPay = $calendarOverride?->is_holiday_pay
            ?? ($nationalHoliday !== null || $attendanceDate->isSunday());
        $dayKey = $calendarOverride?->schedule_day_type
            ?? ($isHolidayPay
                ? 'sunday_thursday'
                : $this->dayKey($attendanceDate));
        $profileConfig = config("attendance.profiles.{$profile}");
        $shift = $this->closestShift($profileConfig, $dayKey, $firstPunch);
        $shiftConfig = data_get($profileConfig, "days.{$dayKey}.{$shift}");
        $specialSchedule = $this->specialWorkScheduleResolver?->findFor(
            $profile,
            $dayKey,
            $shift,
            $attendanceDate,
            $karyawan->company_id,
            $karyawan->directorate_id,
            $karyawan->department_id,
        );

        if (! is_array($profileConfig) || (! is_array($shiftConfig) && $specialSchedule === null)) {
            throw new RuntimeException('Konfigurasi jadwal absensi tidak lengkap.');
        }

        $checkOutDayOffset = $specialSchedule
            ? $specialSchedule->check_out_day_offset
            : (int) ($shiftConfig['check_out_day_offset'] ?? 0);
        $reviewNotes = [];
        $requiresReview = ($profileConfig['requires_review'] ?? false) === true;

        if ($requiresReview) {
            $reviewNotes[] = (string) ($profileConfig['review_note'] ?? 'Jadwal perlu ditinjau.');
        }

        if ($specialSchedule !== null) {
            $reviewNotes[] = "Menggunakan jadwal khusus: {$specialSchedule->name}.";
        }

        if ($calendarOverride !== null) {
            $overrideLabel = $calendarOverride->name ?: 'Pengecualian kalender perusahaan';
            $reviewNotes[] = $isHolidayPay
                ? "Kalender perusahaan: {$overrideLabel}; kerja libur dihitung x2."
                : "Kalender perusahaan: {$overrideLabel}; diperlakukan sebagai hari kerja.";
        }

        if ($nationalHoliday !== null && $isHolidayPay) {
            $reviewNotes[] = "Kerja pada hari libur: {$nationalHoliday->name}.";
        }

        $holidayName = null;

        if ($isHolidayPay) {
            $holidayName = $calendarOverride?->name ?? $nationalHoliday?->name;

            if ($holidayName === null && ! $attendanceDate->isSunday()) {
                $holidayName = 'Hari libur perusahaan';
            }
        }

        $expectedCheckIn = $attendanceDate->setTimeFromTimeString(
            $specialSchedule?->check_in ?? $shiftConfig['check_in'],
        );
        $expectedCheckOut = $attendanceDate
            ->addDays($checkOutDayOffset)
            ->setTimeFromTimeString($specialSchedule?->check_out ?? $shiftConfig['check_out']);
        $standardWorkHours = (float) (
            $specialSchedule?->standard_work_hours
            ?? $shiftConfig['standard_hours']
            ?? 8
        );
        $expectedGrossHours = ($expectedCheckOut->getTimestamp() - $expectedCheckIn->getTimestamp()) / 3600;
        $breakHours = $specialSchedule === null
            ? (float) ($shiftConfig['break_hours'] ?? max(0, $expectedGrossHours - $standardWorkHours))
            : max(0, $expectedGrossHours - $standardWorkHours);
        $breakWindow = $shiftConfig['break_window'] ?? null;

        return new AttendanceSchedule(
            profile: $profile,
            profileLabel: (string) $profileConfig['label'],
            shift: $shift,
            expectedCheckIn: $expectedCheckIn,
            expectedCheckOut: $expectedCheckOut,
            standardWorkHours: $standardWorkHours,
            isHolidayPay: $isHolidayPay,
            holidayName: $holidayName,
            expectedPunchCount: $specialSchedule?->expected_punch_count
                ?? (int) $profileConfig['expected_punch_count'],
            requiresReview: $requiresReview,
            reviewNotes: $reviewNotes,
            calendarOverrideId: $calendarOverride?->id,
            calendarOverrideName: $calendarOverride?->name,
            breakHours: $breakHours,
            breakWindowStart: is_array($breakWindow) ? ($breakWindow[0] ?? null) : null,
            breakWindowEnd: is_array($breakWindow) ? ($breakWindow[1] ?? null) : null,
            specialScheduleName: $specialSchedule?->name,
            breakRules: is_array($shiftConfig['break_rules'] ?? null)
                ? $shiftConfig['break_rules']
                : [],
        );
    }

    private function closestShift(
        mixed $profileConfig,
        string $dayKey,
        CarbonImmutable $firstPunch,
    ): string {
        $firstPunchMinutes = ($firstPunch->hour * 60) + $firstPunch->minute;
        $closestShift = 'day';
        $closestDistance = null;

        foreach (['day', 'night'] as $shift) {
            $checkIn = data_get($profileConfig, "days.{$dayKey}.{$shift}.check_in");

            if (! is_string($checkIn)) {
                continue;
            }

            [$hour, $minute] = array_map('intval', explode(':', $checkIn, 2));
            $checkInMinutes = ($hour * 60) + $minute;
            $distance = abs($firstPunchMinutes - $checkInMinutes);

            // Compare on a 24-hour circle so shifts crossing midnight remain valid.
            $distance = min($distance, 1440 - $distance);

            if ($closestDistance === null || $distance < $closestDistance) {
                $closestShift = $shift;
                $closestDistance = $distance;
            }
        }

        return $closestShift;
    }

    private function profileFor(Karyawan $karyawan): string
    {
        $department = Str::lower((string) $karyawan->department?->name);
        $position = Str::lower((string) $karyawan->jabatan?->name);

        if (Str::contains($department, 'security') || Str::contains($position, 'security')) {
            return 'security';
        }

        if (Str::contains($position, 'qc aging')) {
            return 'qc_aging';
        }

        return 'general';
    }

    private function dayKey(CarbonImmutable $attendanceDate): string
    {
        if ($attendanceDate->isFriday()) {
            return 'friday';
        }

        if ($attendanceDate->isSaturday()) {
            return 'saturday';
        }

        return 'sunday_thursday';
    }
}
