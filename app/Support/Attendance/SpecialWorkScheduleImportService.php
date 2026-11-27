<?php

namespace App\Support\Attendance;

use App\Models\Company;
use App\Models\SpecialWorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SpecialWorkScheduleImportService
{
    public function __construct(
        private SpecialWorkScheduleWorkbookReader $workbookReader,
        private AttendanceCalendarRecalculator $calendarRecalculator,
    ) {}

    /**
     * @return array{created: int, updated: int, recalculated: int}
     */
    public function import(UploadedFile $file, int $recordedBy): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw ValidationException::withMessages([
                'schedule_file' => 'File Excel tidak dapat dibaca.',
            ]);
        }

        try {
            $rows = $this->workbookReader->read($path);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'schedule_file' => $exception->getMessage(),
            ]);
        }

        $errors = [];
        $preparedSchedules = [];
        $keys = [];

        foreach ($rows as $row) {
            $rowNumber = $row['row_number'];
            $profile = $this->profile($row['schedule_profile']);
            $dayType = $this->dayType($row['day_type']);
            $shift = $this->shift($row['shift']);
            $companyId = $this->companyId($row['company_name'], $rowNumber, $errors);
            $validated = [
                'name' => $row['name'],
                'starts_on' => $row['starts_on'],
                'ends_on' => $row['ends_on'],
                'schedule_profile' => $profile,
                'day_type' => $dayType,
                'shift' => $shift,
                'check_in' => $row['check_in'],
                'check_out' => $row['check_out'],
                'check_out_day_offset' => $row['check_out_day_offset'] === ''
                    ? 0
                    : $row['check_out_day_offset'],
                'standard_work_hours' => $row['standard_work_hours'],
                'expected_punch_count' => $row['expected_punch_count'],
                'notes' => $row['notes'] === '' ? null : $row['notes'],
            ];
            $validator = Validator::make($validated, [
                'name' => ['required', 'string', 'max:150'],
                'starts_on' => ['required', 'date'],
                'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
                'schedule_profile' => ['nullable', Rule::in(array_keys(SpecialWorkSchedule::PROFILES))],
                'day_type' => ['nullable', Rule::in(array_keys(SpecialWorkSchedule::DAY_TYPES))],
                'shift' => ['nullable', Rule::in(array_keys(SpecialWorkSchedule::SHIFTS))],
                'check_in' => ['required', 'date_format:H:i'],
                'check_out' => ['required', 'date_format:H:i'],
                'check_out_day_offset' => ['required', 'integer', 'between:0,2'],
                'standard_work_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
                'expected_punch_count' => ['required', 'integer', 'between:0,6'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Baris {$rowNumber}: ".$validator->errors()->first();

                continue;
            }

            $schedule = [
                ...$validated,
                'company_id' => $companyId,
                'directorate_id' => null,
                'department_id' => null,
            ];
            $key = implode(':', [
                $schedule['name'],
                $schedule['starts_on'],
                $schedule['ends_on'],
                $companyId ?? '-',
                $profile ?? '-',
                $dayType ?? '-',
                $shift ?? '-',
            ]);

            if (isset($keys[$key])) {
                $errors[] = "Baris {$rowNumber}: kombinasi periode, profile, tipe hari, dan shift duplikat.";

                continue;
            }

            $keys[$key] = true;
            $preparedSchedules[] = $schedule;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'schedule_file' => $errors,
            ]);
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($preparedSchedules, &$created, &$updated): void {
            foreach ($preparedSchedules as $schedule) {
                $specialSchedule = SpecialWorkSchedule::query()->updateOrCreate(
                    [
                        'name' => $schedule['name'],
                        'company_id' => $schedule['company_id'],
                        'directorate_id' => null,
                        'department_id' => null,
                        'starts_on' => $schedule['starts_on'],
                        'ends_on' => $schedule['ends_on'],
                        'schedule_profile' => $schedule['schedule_profile'],
                        'day_type' => $schedule['day_type'],
                        'shift' => $schedule['shift'],
                    ],
                    [
                        'check_in' => $schedule['check_in'],
                        'check_out' => $schedule['check_out'],
                        'check_out_day_offset' => $schedule['check_out_day_offset'],
                        'standard_work_hours' => $schedule['standard_work_hours'],
                        'expected_punch_count' => $schedule['expected_punch_count'],
                        'notes' => $schedule['notes'],
                    ],
                );

                if ($specialSchedule->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        });

        $startsOn = collect($preparedSchedules)->min('starts_on');
        $endsOn = collect($preparedSchedules)->max('ends_on');
        $companyIds = collect($preparedSchedules)->pluck('company_id')->filter()->unique()->values();
        $recalculated = $this->calendarRecalculator->recalculateDateRange(
            CarbonImmutable::parse($startsOn),
            CarbonImmutable::parse($endsOn),
            $companyIds->isEmpty() ? null : $companyIds->all(),
            $recordedBy,
        );

        return [
            'created' => $created,
            'updated' => $updated,
            'recalculated' => $recalculated,
        ];
    }

    private function profile(string $value): ?string
    {
        return match ($this->normalizedValue($value)) {
            '', 'semua', 'semuaprofile' => null,
            'general', 'produksiumum' => 'general',
            'security' => 'security',
            'qcaging' => 'qc_aging',
            default => $this->normalizedValue($value),
        };
    }

    private function dayType(string $value): ?string
    {
        return match ($this->normalizedValue($value)) {
            '', 'semua', 'semuahari' => null,
            'sundaythursday', 'minggukamis' => 'sunday_thursday',
            'friday', 'jumat' => 'friday',
            'saturday', 'sabtu' => 'saturday',
            default => $this->normalizedValue($value),
        };
    }

    private function shift(string $value): ?string
    {
        return match ($this->normalizedValue($value)) {
            '', 'semua', 'semuashift' => null,
            'day', 'shiftpagi', 'pagi' => 'day',
            'night', 'shiftmalam', 'malam' => 'night',
            default => $this->normalizedValue($value),
        };
    }

    /**
     * @param  list<string>  $errors
     */
    private function companyId(string $companyName, int $rowNumber, array &$errors): ?int
    {
        $companyName = trim($companyName);

        if ($companyName === '') {
            return null;
        }

        $companyId = Company::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($companyName)])
            ->value('id');

        if ($companyId === null) {
            $errors[] = "Baris {$rowNumber}: company '{$companyName}' tidak ditemukan.";
        }

        return $companyId;
    }

    private function normalizedValue(string $value): string
    {
        return Str::slug(Str::ascii(trim($value)), '');
    }
}
