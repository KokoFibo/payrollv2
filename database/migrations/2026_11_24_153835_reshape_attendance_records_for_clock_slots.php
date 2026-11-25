<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->renameColumn('check_in', 'first_in');
            $table->renameColumn('check_out', 'first_out');
            $table->renameColumn('check_out_day_offset', 'last_out_day_offset');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->time('second_in')->nullable()->after('first_out');
            $table->time('second_out')->nullable()->after('second_in');
            $table->time('overtime_in')->nullable()->after('second_out');
            $table->time('overtime_out')->nullable()->after('overtime_in');
            $table->unsignedTinyInteger('second_in_day_offset')->default(0)->after('overtime_out');
            $table->unsignedTinyInteger('second_out_day_offset')->default(0)->after('second_in_day_offset');
            $table->unsignedTinyInteger('overtime_in_day_offset')->default(0)->after('second_out_day_offset');
            $table->unsignedTinyInteger('overtime_out_day_offset')->default(0)->after('overtime_in_day_offset');
            $table->decimal('worked_hours', 6, 1)->default(0)->after('overtime_out_day_offset');
            $table->decimal('worked_days', 5, 1)->default(1)->after('worked_hours');
            $table->decimal('overtime_hours', 6, 1)->default(0)->after('worked_days');
            $table->decimal('holiday_worked_hours', 6, 1)->default(0)->after('overtime_hours');
            $table->decimal('holiday_overtime_hours', 6, 1)->default(0)->after('holiday_worked_hours');
            $table->unsignedSmallInteger('ignored_punch_count')->default(0)->after('duplicate_punch_count');
        });

        $this->backfillClockSlots();

        Schema::dropIfExists('attendance_punches');

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('overtime_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedInteger('overtime_minutes')->default(0)->after('overtime_hours');
        });

        DB::table('attendance_records')->update([
            'overtime_minutes' => DB::raw('ROUND(overtime_hours * 60)'),
        ]);

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('karyawan_id')->constrained('karyawans')->cascadeOnDelete();
            $table->unsignedSmallInteger('punch_sequence');
            $table->dateTime('punched_at');
            $table->time('punch_time');
            $table->boolean('is_next_day')->default(false);
            $table->string('raw_value', 64);
            $table->timestamps();

            $table->unique(
                ['attendance_import_id', 'attendance_record_id', 'punch_sequence'],
                'attendance_punches_import_record_sequence_unique',
            );
            $table->index(['attendance_record_id', 'punch_sequence']);
        });

        $this->restorePunchesFromClockSlots();

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->renameColumn('first_in', 'check_in');
            $table->renameColumn('first_out', 'check_out');
            $table->renameColumn('last_out_day_offset', 'check_out_day_offset');
            $table->dropColumn([
                'second_in',
                'second_out',
                'overtime_in',
                'overtime_out',
                'second_in_day_offset',
                'second_out_day_offset',
                'overtime_in_day_offset',
                'overtime_out_day_offset',
                'worked_hours',
                'worked_days',
                'overtime_hours',
                'holiday_worked_hours',
                'holiday_overtime_hours',
                'ignored_punch_count',
            ]);
        });
    }

    private function backfillClockSlots(): void
    {
        if (! Schema::hasTable('attendance_punches')) {
            return;
        }

        DB::table('attendance_records')
            ->select(['id', 'overtime_minutes'])
            ->chunkById(200, function ($records): void {
                $recordIds = $records->pluck('id')->all();
                $punchesByRecord = DB::table('attendance_punches')
                    ->whereIn('attendance_record_id', $recordIds)
                    ->orderBy('attendance_record_id')
                    ->orderBy('punch_sequence')
                    ->get()
                    ->groupBy('attendance_record_id');

                foreach ($records as $record) {
                    $punches = $this->firstPunchPerTimeWindow(
                        $punchesByRecord->get($record->id, collect())->all(),
                    );
                    $slots = array_slice($punches, 0, 6);

                    DB::table('attendance_records')
                        ->where('id', $record->id)
                        ->update([
                            ...$this->slotFields($slots),
                            'worked_hours' => $this->workedHours($punches),
                            'worked_days' => $slots === [] ? 0 : 1,
                            'overtime_hours' => round(((int) $record->overtime_minutes) / 60, 1),
                            'ignored_punch_count' => max(0, count($punches) - 6),
                        ]);
                }
            });
    }

    private function restorePunchesFromClockSlots(): void
    {
        DB::table('attendance_records')
            ->select([
                'id',
                'attendance_import_id',
                'karyawan_id',
                'attendance_date',
                'first_in',
                'first_out',
                'second_in',
                'second_out',
                'overtime_in',
                'overtime_out',
                'second_in_day_offset',
                'second_out_day_offset',
                'overtime_in_day_offset',
                'overtime_out_day_offset',
            ])
            ->orderBy('id')
            ->chunk(200, function ($records): void {
                $rows = [];

                foreach ($records as $record) {
                    if ($record->attendance_import_id === null) {
                        continue;
                    }

                    foreach ($this->slotValues($record) as $sequence => [$time, $dayOffset]) {
                        if ($time === null) {
                            continue;
                        }

                        $punchedAt = CarbonImmutable::parse($record->attendance_date)
                            ->addDays($dayOffset)
                            ->setTimeFromTimeString($time);
                        $rows[] = [
                            'attendance_import_id' => $record->attendance_import_id,
                            'attendance_record_id' => $record->id,
                            'karyawan_id' => $record->karyawan_id,
                            'punch_sequence' => $sequence,
                            'punched_at' => $punchedAt,
                            'punch_time' => $time,
                            'is_next_day' => $dayOffset > 0,
                            'raw_value' => $punchedAt->format('g:i A').($dayOffset > 0 ? '+' : ''),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('attendance_punches')->insert($rows);
                }
            });
    }

    /**
     * @param  list<object>  $punches
     * @return list<object>
     */
    private function firstPunchPerTimeWindow(array $punches): array
    {
        $windowSeconds = (int) config('attendance.duplicate_punch_window_minutes', 10) * 60;
        $effective = [];
        $previousTimestamp = null;

        foreach ($punches as $punch) {
            $timestamp = CarbonImmutable::parse($punch->punched_at)->getTimestamp();

            if ($previousTimestamp === null || $timestamp - $previousTimestamp >= $windowSeconds) {
                $effective[] = $punch;
            }

            $previousTimestamp = $timestamp;
        }

        return $effective;
    }

    /**
     * @param  list<object>  $punches
     * @return array<string, mixed>
     */
    private function slotFields(array $punches): array
    {
        $fields = [
            'second_in' => null,
            'second_out' => null,
            'overtime_in' => null,
            'overtime_out' => null,
            'second_in_day_offset' => 0,
            'second_out_day_offset' => 0,
            'overtime_in_day_offset' => 0,
            'overtime_out_day_offset' => 0,
        ];
        $names = [
            0 => 'first_in',
            1 => 'first_out',
            2 => 'second_in',
            3 => 'second_out',
            4 => 'overtime_in',
            5 => 'overtime_out',
        ];
        $offsetNames = [
            2 => 'second_in_day_offset',
            3 => 'second_out_day_offset',
            4 => 'overtime_in_day_offset',
            5 => 'overtime_out_day_offset',
        ];

        foreach ($punches as $index => $punch) {
            $fields[$names[$index]] = $punch->punch_time;

            if (isset($offsetNames[$index])) {
                $fields[$offsetNames[$index]] = (int) $punch->is_next_day;
            }
        }

        if ($punches !== []) {
            $fields['last_out_day_offset'] = (int) end($punches)->is_next_day;
        }

        return $fields;
    }

    /**
     * @param  list<object>  $punches
     */
    private function workedHours(array $punches): float
    {
        $minutes = 0;

        foreach ([[0, 1], [2, 3]] as [$startIndex, $endIndex]) {
            if (! isset($punches[$startIndex], $punches[$endIndex])) {
                continue;
            }

            $difference = CarbonImmutable::parse($punches[$endIndex]->punched_at)
                ->getTimestamp() - CarbonImmutable::parse($punches[$startIndex]->punched_at)->getTimestamp();

            if ($difference > 0) {
                $minutes += $difference / 60;
            }
        }

        return round($minutes / 60, 1);
    }

    /**
     * @return array<int, array{0: string|null, 1: int}>
     */
    private function slotValues(object $record): array
    {
        return [
            1 => [$record->first_in, 0],
            2 => [$record->first_out, 0],
            3 => [$record->second_in, (int) $record->second_in_day_offset],
            4 => [$record->second_out, (int) $record->second_out_day_offset],
            5 => [$record->overtime_in, (int) $record->overtime_in_day_offset],
            6 => [$record->overtime_out, (int) $record->overtime_out_day_offset],
        ];
    }
};
