<?php

namespace App\Support\Attendance;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AttendanceCleanupService
{
    /**
     * @return list<array{
     *     value: string,
     *     record_count: int,
     *     import_count: int,
     *     file_bytes: int
     * }>
     */
    public function monthOptions(int $lookbackMonths = 24): array
    {
        $lookbackMonths = max(1, min($lookbackMonths, 120));
        $recordsByMonth = DB::table('attendance_records')
            ->selectRaw("DATE_FORMAT(attendance_date, '%Y-%m') AS month_key, COUNT(*) AS aggregate_count")
            ->groupByRaw("DATE_FORMAT(attendance_date, '%Y-%m')")
            ->get()
            ->keyBy('month_key');
        $importsByMonth = DB::table('attendance_imports')
            ->selectRaw("DATE_FORMAT(attendance_date, '%Y-%m') AS month_key, COUNT(*) AS aggregate_count, COALESCE(SUM(file_size), 0) AS file_bytes")
            ->groupByRaw("DATE_FORMAT(attendance_date, '%Y-%m')")
            ->get()
            ->keyBy('month_key');
        $currentMonth = CarbonImmutable::now((string) config('app.timezone'))->startOfMonth();
        $monthKeys = collect(range(0, $lookbackMonths - 1))
            ->map(fn (int $offset): string => $currentMonth->subMonths($offset)->format('Y-m'))
            ->merge($recordsByMonth->keys())
            ->merge($importsByMonth->keys())
            ->unique()
            ->sortDesc()
            ->values();

        return $monthKeys
            ->map(fn (string $month): array => [
                'value' => $month,
                'record_count' => (int) ($recordsByMonth->get($month)?->aggregate_count ?? 0),
                'import_count' => (int) ($importsByMonth->get($month)?->aggregate_count ?? 0),
                'file_bytes' => (int) ($importsByMonth->get($month)?->file_bytes ?? 0),
            ])
            ->all();
    }

    /**
     * @param  list<string>  $monthKeys
     * @return array{
     *     months: list<string>,
     *     record_rows: int,
     *     import_rows: int,
     *     file_bytes: int,
     *     deleted_records: int,
     *     deleted_imports: int
     * }
     */
    public function deleteMonths(array $monthKeys): array
    {
        $months = collect($monthKeys)
            ->map(fn (mixed $month): string => trim((string) $month))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        if ($months === []) {
            throw new InvalidArgumentException('Pilih minimal satu bulan presensi.');
        }

        $monthRanges = array_map(
            fn (string $month): array => $this->monthRange($month),
            $months,
        );

        return DB::transaction(function () use ($months, $monthRanges): array {
            $recordsQuery = $this->whereMonths(DB::table('attendance_records'), $monthRanges);
            $importsQuery = $this->whereMonths(DB::table('attendance_imports'), $monthRanges);
            $recordRows = (clone $recordsQuery)->count();
            $importRows = (clone $importsQuery)->count();
            $fileBytes = (int) (clone $importsQuery)->sum('file_size');
            $deletedRecords = $recordsQuery->delete();
            $deletedImports = $importsQuery->delete();

            return [
                'months' => $months,
                'record_rows' => $recordRows,
                'import_rows' => $importRows,
                'file_bytes' => $fileBytes,
                'deleted_records' => $deletedRecords,
                'deleted_imports' => $deletedImports,
            ];
        }, attempts: 3);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $monthRanges
     */
    private function whereMonths(Builder $query, array $monthRanges): Builder
    {
        return $query->where(function (Builder $query) use ($monthRanges): void {
            foreach ($monthRanges as [$startsOn, $endsOn]) {
                $query->orWhereBetween('attendance_date', [$startsOn, $endsOn]);
            }
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function monthRange(string $month): array
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new InvalidArgumentException('Format bulan presensi tidak valid.');
        }

        $date = CarbonImmutable::createFromFormat('!Y-m', $month, (string) config('app.timezone'));

        return [
            $date->startOfMonth()->toDateString(),
            $date->endOfMonth()->toDateString(),
        ];
    }
}
