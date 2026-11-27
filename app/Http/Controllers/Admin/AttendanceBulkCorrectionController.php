<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Support\Attendance\AttendanceBulkCorrectionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceBulkCorrectionController extends Controller
{
    public function preview(
        Request $request,
        AttendanceBulkCorrectionService $bulkCorrectionService,
    ): JsonResponse {
        Gate::authorize('access-developer-settings');

        return response()->json(
            $bulkCorrectionService->preview($this->validatedFilters($request)),
        );
    }

    public function update(
        Request $request,
        AttendanceBulkCorrectionService $bulkCorrectionService,
    ): RedirectResponse {
        Gate::authorize('access-developer-settings');

        $bulkData = $request->validate([
            'times' => ['required', 'array'],
            'times.first_in' => ['nullable', 'date_format:H:i'],
            'times.first_out' => ['nullable', 'date_format:H:i'],
            'times.second_in' => ['nullable', 'date_format:H:i'],
            'times.second_out' => ['nullable', 'date_format:H:i'],
            'times.overtime_in' => ['nullable', 'date_format:H:i'],
            'times.overtime_out' => ['nullable', 'date_format:H:i'],
            'fill_empty_only' => ['required', 'boolean'],
            'select_all' => ['nullable', 'boolean'],
        ]);

        if (collect($bulkData['times'])->filter(
            fn (mixed $time): bool => is_string($time) && $time !== '',
        )->isEmpty()) {
            throw ValidationException::withMessages([
                'times' => 'Isi minimal satu jam sebelum menjalankan koreksi bulk.',
            ]);
        }

        if ($request->boolean('select_all')) {
            $result = $bulkCorrectionService->applyAll(
                $this->validatedFilters($request),
                $bulkData['times'],
                (bool) $bulkData['fill_empty_only'],
                $request->user()->id,
            );
        } else {
            $validatedTargets = $request->validate([
                'targets' => ['required', 'array', 'min:1', 'max:'.AttendanceBulkCorrectionService::MAX_TARGETS],
                'targets.*.karyawan_id' => ['required', 'integer', 'exists:karyawans,id'],
                'targets.*.attendance_date' => ['required', 'date'],
            ]);
            $targetKeys = collect($validatedTargets['targets'])
                ->map(fn (array $target): string => $target['karyawan_id'].'|'.$target['attendance_date']);

            if ($targetKeys->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'targets' => 'Daftar record yang dipilih mengandung data duplikat.',
                ]);
            }

            $result = $bulkCorrectionService->apply(
                $validatedTargets['targets'],
                $bulkData['times'],
                (bool) $bulkData['fill_empty_only'],
                $request->user()->id,
            );
        }

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Koreksi attendance selesai',
            'message' => $result['updated']
                .' attendance diperbarui, '
                .$result['created']
                .' attendance baru dibuat, dan '
                .$result['skipped']
                .' data dilewati.',
        ]);
    }

    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     company_id: int|null,
     *     directorate_id: int|null,
     *     department_id: int|null,
     *     jabatan_id: int|null,
     *     shift: string,
     *     status: string,
     *     schedule_profile: string,
     *     condition: string,
     *     search: string,
     *     page: int,
     *     per_page: int
     * }
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'directorate_id' => ['nullable', 'integer', 'exists:directorates,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'jabatan_id' => ['nullable', 'integer', 'exists:jabatans,id'],
            'shift' => ['nullable', Rule::in(['day', 'night'])],
            'status' => ['nullable', Rule::in(array_keys(AttendanceRecord::STATUSES))],
            'schedule_profile' => [
                'nullable',
                Rule::in(array_keys((array) config('attendance.profiles'))),
            ],
            'condition' => [
                'nullable',
                Rule::in(array_keys(AttendanceBulkCorrectionService::CONDITIONS)),
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([15, 30, 50, 100])],
        ]);
        $startsOn = CarbonImmutable::parse($validated['start_date']);
        $endsOn = CarbonImmutable::parse($validated['end_date']);
        $rangeDays = (int) $startsOn->diffInDays($endsOn) + 1;

        if ($rangeDays > AttendanceBulkCorrectionService::MAX_DATE_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'end_date' => 'Rentang koreksi maksimal '
                    .AttendanceBulkCorrectionService::MAX_DATE_RANGE_DAYS
                    .' hari.',
            ]);
        }

        return [
            'start_date' => $startsOn->toDateString(),
            'end_date' => $endsOn->toDateString(),
            'company_id' => isset($validated['company_id'])
                ? (int) $validated['company_id']
                : null,
            'directorate_id' => isset($validated['directorate_id'])
                ? (int) $validated['directorate_id']
                : null,
            'department_id' => isset($validated['department_id'])
                ? (int) $validated['department_id']
                : null,
            'jabatan_id' => isset($validated['jabatan_id'])
                ? (int) $validated['jabatan_id']
                : null,
            'shift' => (string) ($validated['shift'] ?? ''),
            'status' => (string) ($validated['status'] ?? ''),
            'schedule_profile' => (string) ($validated['schedule_profile'] ?? ''),
            'condition' => (string) ($validated['condition'] ?? 'all'),
            'search' => trim((string) ($validated['search'] ?? '')),
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 15),
        ];
    }
}
