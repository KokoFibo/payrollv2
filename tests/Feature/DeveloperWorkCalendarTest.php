<?php

use App\Models\AttendanceRecord;
use App\Models\CalendarOverride;
use App\Models\Company;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\NationalHoliday;
use App\Models\SpecialWorkSchedule;
use App\Models\User;
use App\Support\Attendance\AttendanceScheduleResolver;
use App\Support\Attendance\CalendarOverrideResolver;
use App\Support\Attendance\SpecialWorkScheduleResolver;
use Carbon\CarbonImmutable;

test('developer can create update and delete national holidays', function () {
    $developer = User::factory()->create(['role' => 8]);

    $this->actingAs($developer)
        ->post(route('admin.developer-settings.national-holidays.store'), [
            'name' => 'Libur Tahun Baru',
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-01-03',
            'total_days' => null,
        ])
        ->assertRedirect();

    $holiday = NationalHoliday::query()->firstOrFail();

    expect($holiday->name)->toBe('Libur Tahun Baru')
        ->and($holiday->total_days)->toBe(3);

    $this->actingAs($developer)
        ->put(route('admin.developer-settings.national-holidays.update', $holiday), [
            'name' => 'Libur Tahun Baru Diperbarui',
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-01-02',
        ])
        ->assertRedirect();

    expect($holiday->refresh())
        ->name->toBe('Libur Tahun Baru Diperbarui')
        ->total_days->toBe(2);

    $this->actingAs($developer)
        ->delete(route('admin.developer-settings.national-holidays.destroy', $holiday))
        ->assertRedirect();

    $this->assertModelMissing($holiday);
});

test('adding a one-day national holiday recalculates attendance into holiday fields', function () {
    $developer = User::factory()->create(['role' => 8]);
    $importer = User::factory()->create(['role' => 5]);
    $department = Department::factory()->create(['name' => 'Production']);
    $position = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $position->id,
    ]);
    $record = AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'present',
        'first_in' => '08:00:00',
        'first_out' => '11:30:00',
        'second_in' => '12:30:00',
        'second_out' => '18:30:00',
        'worked_hours' => 8,
        'worked_days' => 1,
        'overtime_hours' => 1.5,
        'raw_punch_count' => 6,
        'effective_punch_count' => 4,
        'duplicate_punch_count' => 2,
        'has_duplicate_punch' => true,
        'recorded_by' => $importer->id,
    ]);

    $this->actingAs($developer)
        ->post(route('admin.developer-settings.national-holidays.store'), [
            'name' => 'Hari Libur Satu Hari',
            'starts_on' => '2026-11-19',
            'ends_on' => null,
        ])
        ->assertRedirect();

    $holiday = NationalHoliday::query()->firstOrFail();

    expect($holiday)
        ->ends_on->toDateString()->toBe('2026-11-19')
        ->total_days->toBe(1)
        ->and($record->refresh())
        ->is_holiday_pay->toBeTrue()
        ->worked_hours->toBe('0.0')
        ->overtime_hours->toBe('0.0')
        ->holiday_worked_hours->toBe('16.0')
        ->holiday_overtime_hours->toBe('3.0')
        ->raw_punch_count->toBe(6)
        ->effective_punch_count->toBe(4)
        ->duplicate_punch_count->toBe(2)
        ->has_duplicate_punch->toBeTrue()
        ->recorded_by->toBe($importer->id)
        ->comparison_notes->toContain('dikali 2');
});

test('schedule resolver detects a legacy holiday without an end date', function () {
    $department = Department::factory()->create(['name' => 'Production']);
    $position = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $position->id,
    ]);
    NationalHoliday::factory()->create([
        'name' => 'Libur Lama Satu Hari',
        'starts_on' => '2026-11-19',
        'ends_on' => null,
        'total_days' => 1,
    ]);

    $schedule = app(AttendanceScheduleResolver::class)->resolve(
        $employee,
        CarbonImmutable::parse('2026-11-19'),
        CarbonImmutable::parse('2026-11-19 08:00:00'),
    );

    expect($schedule)
        ->isHolidayPay->toBeTrue()
        ->holidayName->toBe('Libur Lama Satu Hari');
});

test('developer can create update and delete special work schedules', function () {
    $developer = User::factory()->create(['role' => 8]);

    $this->actingAs($developer)
        ->post(route('admin.developer-settings.special-work-schedules.store'), [
            'name' => 'Ramadan 2027',
            'starts_on' => '2027-02-01',
            'ends_on' => '2027-03-01',
            'schedule_profile' => 'general',
            'day_type' => 'sunday_thursday',
            'shift' => 'day',
            'check_in' => '08:00',
            'check_out' => '16:00',
            'check_out_day_offset' => 0,
            'standard_work_hours' => 8,
            'expected_punch_count' => 4,
            'notes' => 'Jam kerja Ramadan.',
        ])
        ->assertRedirect();

    $schedule = SpecialWorkSchedule::query()->firstOrFail();

    expect($schedule->name)->toBe('Ramadan 2027')
        ->and($schedule->check_out)->toBe('16:00:00');

    $this->actingAs($developer)
        ->put(route('admin.developer-settings.special-work-schedules.update', $schedule), [
            'name' => 'Ramadan 2027 Revisi',
            'starts_on' => '2027-02-01',
            'ends_on' => '2027-03-01',
            'schedule_profile' => null,
            'day_type' => null,
            'shift' => null,
            'check_in' => '07:30',
            'check_out' => '15:30',
            'check_out_day_offset' => 0,
            'standard_work_hours' => 7.5,
            'expected_punch_count' => 4,
            'notes' => 'Berlaku semua profile.',
        ])
        ->assertRedirect();

    expect($schedule->refresh())
        ->name->toBe('Ramadan 2027 Revisi')
        ->schedule_profile->toBeNull()
        ->check_in->toBe('07:30:00');

    $this->actingAs($developer)
        ->delete(route('admin.developer-settings.special-work-schedules.destroy', $schedule))
        ->assertRedirect();

    $this->assertModelMissing($schedule);
});

test('developer can apply and revise a bulk schedule and existing attendance is recalculated', function () {
    $developer = User::factory()->create(['role' => 8]);
    $department = Department::factory()->create(['name' => 'Production']);
    $position = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'department_id' => $department->id,
        'jabatan_id' => $position->id,
    ]);
    $attendance = AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2027-02-04',
        'status' => 'present',
        'first_in' => '08:00:00',
        'first_out' => '11:30:00',
        'second_in' => '12:30:00',
        'second_out' => '16:00:00',
        'worked_hours' => 8,
        'has_missing_punch' => false,
    ]);
    $payload = [
        'name' => 'Ramadan 2027',
        'starts_on' => '2027-02-01',
        'ends_on' => '2027-03-01',
        'schedules' => [
            [
                'enabled' => true,
                'schedule_profile' => 'general',
                'shift' => 'day',
                'check_in' => '08:00',
                'check_out' => '16:00',
                'check_out_day_offset' => 0,
                'standard_work_hours' => 7,
                'expected_punch_count' => 4,
            ],
        ],
    ];

    $this->actingAs($developer)
        ->post(route('admin.developer-settings.special-work-schedules.bulk'), $payload)
        ->assertRedirect();

    expect(SpecialWorkSchedule::query()->count())->toBe(1);
    expect($attendance->refresh())
        ->expected_check_in->toBe('08:00:00')
        ->expected_check_out->toBe('16:00:00')
        ->worked_hours->toBe('7.0');

    $payload['schedules'][0]['check_in'] = '09:00';
    $payload['schedules'][0]['check_out'] = '15:00';
    $payload['schedules'][0]['standard_work_hours'] = 6;

    $this->actingAs($developer)
        ->post(route('admin.developer-settings.special-work-schedules.bulk'), $payload)
        ->assertRedirect();

    expect(SpecialWorkSchedule::query()->count())->toBe(1);
    expect($attendance->refresh())
        ->expected_check_in->toBe('09:00:00')
        ->expected_check_out->toBe('15:00:00')
        ->worked_hours->toBe('5.0');

    $payload['schedules'][0]['enabled'] = false;

    $this->actingAs($developer)
        ->post(route('admin.developer-settings.special-work-schedules.bulk'), $payload)
        ->assertRedirect();

    expect(SpecialWorkSchedule::query()->count())->toBe(0);
    expect($attendance->refresh())
        ->expected_check_in->toBe('08:00:00')
        ->expected_check_out->toBe('17:00:00');
});

test('developer can create update and delete company calendar overrides', function () {
    $developer = User::factory()->create(['role' => 8]);
    $company = Company::factory()->create(['name' => 'Company Shift Kalender']);

    $this->actingAs($developer)
        ->post(route('admin.developer-settings.calendar-overrides.store'), [
            'company_ids' => [$company->id],
            'calendar_date' => '2026-11-20',
            'name' => 'Libur nasional dijadikan hari kerja',
            'is_holiday_pay' => false,
            'schedule_day_type' => 'friday',
            'notes' => 'Khusus company ini.',
        ])
        ->assertRedirect();

    $override = CalendarOverride::query()->firstOrFail();

    expect($override)
        ->company_id->toBe($company->id)
        ->calendar_date->toDateString()->toBe('2026-11-20')
        ->is_holiday_pay->toBeFalse()
        ->schedule_day_type->toBe('friday');

    $this->actingAs($developer)
        ->put(route('admin.developer-settings.calendar-overrides.update', $override), [
            'company_id' => $company->id,
            'calendar_date' => '2026-11-21',
            'name' => 'Libur dipindahkan ke Sabtu',
            'is_holiday_pay' => true,
            'schedule_day_type' => null,
            'notes' => 'Libur x2 pada Sabtu.',
        ])
        ->assertRedirect();

    expect($override->refresh())
        ->calendar_date->toDateString()->toBe('2026-11-21')
        ->is_holiday_pay->toBeTrue()
        ->schedule_day_type->toBeNull();

    $this->actingAs($developer)
        ->delete(route('admin.developer-settings.calendar-overrides.destroy', $override))
        ->assertRedirect();

    $this->assertModelMissing($override);
});

test('developer can create or update a calendar override for multiple companies at once', function () {
    $developer = User::factory()->create(['role' => 8]);
    $companies = Company::factory()->count(3)->create();
    $existingOverride = CalendarOverride::factory()->create([
        'company_id' => $companies[0]->id,
        'calendar_date' => '2026-11-20',
        'name' => 'Nama sebelumnya',
        'is_holiday_pay' => true,
    ]);

    $this->actingAs($developer)
        ->post(route('admin.developer-settings.calendar-overrides.store'), [
            'company_ids' => $companies->pluck('id')->all(),
            'calendar_date' => '2026-11-20',
            'name' => 'Jumat masuk untuk semua company terpilih',
            'is_holiday_pay' => false,
            'schedule_day_type' => 'friday',
            'notes' => 'Dibuat dalam satu aksi.',
        ])
        ->assertRedirect();

    $overrides = CalendarOverride::query()
        ->whereDate('calendar_date', '2026-11-20')
        ->orderBy('company_id')
        ->get();

    expect($overrides)->toHaveCount(3)
        ->and($overrides->pluck('company_id')->sort()->values()->all())
        ->toBe($companies->pluck('id')->sort()->values()->all())
        ->and($existingOverride->refresh())
        ->name->toBe('Jumat masuk untuk semua company terpilih')
        ->is_holiday_pay->toBeFalse()
        ->schedule_day_type->toBe('friday');
});

test('calendar override requires at least one company', function () {
    $developer = User::factory()->create(['role' => 8]);

    $this->actingAs($developer)
        ->from(route('admin.developer-settings.index'))
        ->post(route('admin.developer-settings.calendar-overrides.store'), [
            'company_ids' => [],
            'calendar_date' => '2026-11-20',
            'name' => 'Tanpa company',
            'is_holiday_pay' => false,
        ])
        ->assertRedirect(route('admin.developer-settings.index'))
        ->assertSessionHasErrors('company_ids');

    expect(CalendarOverride::query()->count())->toBe(0);
});

test('company calendar override recalculates existing holiday attendance', function () {
    $developer = User::factory()->create(['role' => 8]);
    $company = Company::factory()->create();
    $department = Department::factory()->create(['name' => 'Production']);
    $position = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'company_id' => $company->id,
        'department_id' => $department->id,
        'jabatan_id' => $position->id,
    ]);
    NationalHoliday::factory()->create([
        'name' => 'Libur Nasional',
        'starts_on' => '2026-11-19',
        'ends_on' => '2026-11-19',
    ]);
    $record = AttendanceRecord::factory()->for($employee)->create([
        'attendance_date' => '2026-11-19',
        'status' => 'present',
        'first_in' => '08:00:00',
        'first_out' => '11:30:00',
        'second_in' => '12:30:00',
        'second_out' => '18:30:00',
        'worked_hours' => 0,
        'overtime_hours' => 0,
        'holiday_worked_hours' => 16,
        'holiday_overtime_hours' => 3,
        'is_holiday_pay' => true,
    ]);

    $this->actingAs($developer)
        ->post(route('admin.developer-settings.calendar-overrides.store'), [
            'company_ids' => [$company->id],
            'calendar_date' => '2026-11-19',
            'name' => 'Company masuk pada hari libur',
            'is_holiday_pay' => false,
            'schedule_day_type' => 'sunday_thursday',
        ])
        ->assertRedirect();

    expect($record->refresh())
        ->is_holiday_pay->toBeFalse()
        ->worked_hours->toBe('8.0')
        ->overtime_hours->toBe('1.5')
        ->holiday_worked_hours->toBe('0.0')
        ->holiday_overtime_hours->toBe('0.0');
});

test('attendance schedule resolver applies holiday overrides per company', function () {
    $holiday = NationalHoliday::factory()->create([
        'name' => 'Libur Nasional Jumat',
        'starts_on' => '2026-11-20',
        'ends_on' => '2026-11-20',
    ]);
    $normalCompany = Company::factory()->create(['name' => 'Company Hari Kerja']);
    $holidayCompany = Company::factory()->create(['name' => 'Company Tetap Libur']);
    $department = Department::factory()->create(['name' => 'Production']);
    $position = Jabatan::factory()->create(['name' => 'General Worker']);
    $normalEmployee = Karyawan::factory()->create([
        'company_id' => $normalCompany->id,
        'department_id' => $department->id,
        'jabatan_id' => $position->id,
    ]);
    $holidayEmployee = Karyawan::factory()->create([
        'company_id' => $holidayCompany->id,
        'department_id' => $department->id,
        'jabatan_id' => $position->id,
    ]);
    $override = CalendarOverride::factory()->create([
        'company_id' => $normalCompany->id,
        'calendar_date' => '2026-11-20',
        'name' => 'Jumat tetap masuk',
        'is_holiday_pay' => false,
        'schedule_day_type' => 'friday',
    ]);
    $resolver = app(AttendanceScheduleResolver::class);
    $attendanceDate = CarbonImmutable::parse('2026-11-20');
    $firstPunch = CarbonImmutable::parse('2026-11-20 07:55:00');

    $normalSchedule = $resolver->resolve($normalEmployee, $attendanceDate, $firstPunch);
    $holidaySchedule = $resolver->resolve($holidayEmployee, $attendanceDate, $firstPunch);

    expect($normalSchedule)
        ->isHolidayPay->toBeFalse()
        ->standardWorkHours->toBe(7.5)
        ->calendarOverrideId->toBe($override->id)
        ->and($holidaySchedule)
        ->isHolidayPay->toBeTrue()
        ->standardWorkHours->toBe(8.0)
        ->holidayName->toBe($holiday->name)
        ->calendarOverrideId->toBeNull();
});

test('calendar override resolver prefers department scope over company scope', function () {
    $company = Company::factory()->create();
    $directorate = Directorate::factory()->create();
    $department = Department::factory()->create();
    $employee = Karyawan::factory()->create([
        'company_id' => $company->id,
        'directorate_id' => $directorate->id,
        'department_id' => $department->id,
    ]);
    CalendarOverride::factory()->create([
        'company_id' => $company->id,
        'calendar_date' => '2026-11-20',
        'name' => 'Aturan company',
        'is_holiday_pay' => false,
    ]);
    $departmentOverride = CalendarOverride::factory()->create([
        'company_id' => $company->id,
        'department_id' => $department->id,
        'calendar_date' => '2026-11-20',
        'name' => 'Aturan department',
        'is_holiday_pay' => true,
    ]);

    $override = (new CalendarOverrideResolver)->findFor(
        $employee,
        CarbonImmutable::parse('2026-11-20'),
    );

    expect($override?->id)->toBe($departmentOverride->id);
});

test('attendance schedule resolver prefers the most specific special schedule', function () {
    $company = Company::factory()->create(['name' => 'Company Schedule']);
    $directorate = Directorate::factory()->create(['name' => 'Directorate Schedule']);
    $department = Department::factory()->create(['name' => 'Production']);
    $position = Jabatan::factory()->create(['name' => 'General Worker']);
    $employee = Karyawan::factory()->create([
        'company_id' => $company->id,
        'directorate_id' => $directorate->id,
        'department_id' => $department->id,
        'jabatan_id' => $position->id,
    ]);
    SpecialWorkSchedule::factory()->create([
        'name' => 'Ramadan Semua Profile',
        'starts_on' => '2027-02-01',
        'ends_on' => '2027-03-01',
        'schedule_profile' => null,
        'day_type' => null,
        'shift' => null,
        'check_in' => '08:00:00',
        'check_out' => '16:00:00',
    ]);
    SpecialWorkSchedule::factory()->create([
        'name' => 'Ramadan Produksi',
        'starts_on' => '2027-02-01',
        'ends_on' => '2027-03-01',
        'schedule_profile' => 'general',
        'day_type' => 'sunday_thursday',
        'shift' => 'day',
        'check_in' => '09:00:00',
        'check_out' => '15:00:00',
    ]);
    SpecialWorkSchedule::factory()->create([
        'name' => 'Ramadan Department Produksi',
        'company_id' => $company->id,
        'directorate_id' => $directorate->id,
        'department_id' => $department->id,
        'starts_on' => '2027-02-01',
        'ends_on' => '2027-03-01',
        'schedule_profile' => 'general',
        'day_type' => 'sunday_thursday',
        'shift' => 'day',
        'check_in' => '10:00:00',
        'check_out' => '14:00:00',
    ]);
    $resolver = new AttendanceScheduleResolver(new SpecialWorkScheduleResolver);

    $schedule = $resolver->resolve(
        $employee,
        CarbonImmutable::parse('2027-02-04'),
        CarbonImmutable::parse('2027-02-04 08:55:00'),
    );

    expect($schedule)
        ->expectedCheckIn->format('H:i')->toBe('10:00')
        ->expectedCheckOut->format('H:i')->toBe('14:00')
        ->and($schedule->reviewNotes)->toContain('Menggunakan jadwal khusus: Ramadan Department Produksi.');
});

test('non developers cannot manage work calendar data', function () {
    $admin = User::factory()->create(['role' => 5]);

    $this->actingAs($admin)
        ->post(route('admin.developer-settings.national-holidays.store'), [
            'name' => 'Tidak Boleh',
        ])
        ->assertForbidden();
});
