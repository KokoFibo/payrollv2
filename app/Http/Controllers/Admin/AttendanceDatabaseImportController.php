<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceDatabaseImportRequest;
use App\Support\Attendance\AttendanceDatabaseImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class AttendanceDatabaseImportController extends Controller
{
    public function preview(
        AttendanceDatabaseImportRequest $request,
        AttendanceDatabaseImportService $databaseImportService,
    ): JsonResponse {
        $validated = $request->validated();
        $importMode = (string) ($validated['attendance_mode'] ?? 'date');
        $period = $importMode === 'month'
            ? $validated['attendance_month']
            : $validated['attendance_date'];

        try {
            return response()->json($databaseImportService->preview(
                importMode: $importMode,
                period: $period,
                page: (int) ($validated['page'] ?? 1),
                perPage: (int) ($validated['per_page'] ?? 15),
            ));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Database source tidak dapat dibaca. Periksa konfigurasi dan koneksi database source.',
            ], 422);
        }
    }

    public function store(
        AttendanceDatabaseImportRequest $request,
        AttendanceDatabaseImportService $databaseImportService,
    ): RedirectResponse {
        $validated = $request->validated();
        $importMode = (string) ($validated['attendance_mode'] ?? 'date');
        $period = $importMode === 'month'
            ? $validated['attendance_month']
            : $validated['attendance_date'];

        try {
            $import = $databaseImportService->import(
                importMode: $importMode,
                period: $period,
                actor: $request->user(),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast', [
                'type' => 'error',
                'title' => 'Import database gagal',
                'message' => 'Database source tidak dapat diproses. Periksa konfigurasi dan koneksi database source.',
            ]);
        }

        $message = "{$import->processed_employee_count} attendance diproses dari database source.";

        if ($import->skipped_without_punch_count > 0) {
            $message .= " {$import->skipped_without_punch_count} record tanpa punch dilewati.";
        }

        if ($import->unmatched_employee_count > 0) {
            $message .= " {$import->unmatched_employee_count} record tanpa NIP cocok dilewati.";
        }

        if ($import->missing_punch_count > 0) {
            $message .= " {$import->missing_punch_count} record memiliki punch tidak lengkap.";
        }

        if ($import->review_required_count > 0) {
            $message .= " {$import->review_required_count} record perlu review.";
        }

        return redirect()
            ->route('admin.attendance.index', [
                'date' => $import->attendance_date->toDateString(),
            ])
            ->with('toast', [
                'type' => $import->unmatched_employee_count > 0
                    || $import->missing_punch_count > 0
                    || $import->review_required_count > 0
                    ? 'warning'
                    : 'success',
                'title' => 'Import database selesai',
                'message' => $message,
            ]);
    }

    public function storeDay(
        AttendanceDatabaseImportRequest $request,
        AttendanceDatabaseImportService $databaseImportService,
    ): JsonResponse {
        try {
            $import = $databaseImportService->import(
                importMode: 'date',
                period: (string) $request->validated('attendance_date'),
                actor: $request->user(),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Database source tidak dapat diproses untuk tanggal tersebut.',
            ], 422);
        }

        return response()->json([
            'attendance_date' => $import->attendance_date->toDateString(),
            'processed_employee_count' => $import->processed_employee_count,
            'unmatched_employee_count' => $import->unmatched_employee_count,
            'missing_punch_count' => $import->missing_punch_count,
            'review_required_count' => $import->review_required_count,
        ]);
    }
}
