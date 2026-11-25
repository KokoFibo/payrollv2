<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceImportRequest;
use App\Support\Attendance\AttendanceImportService;
use Illuminate\Http\RedirectResponse;

class AttendanceImportController extends Controller
{
    public function store(
        StoreAttendanceImportRequest $request,
        AttendanceImportService $attendanceImportService,
    ): RedirectResponse {
        $import = $attendanceImportService->import(
            $request->file('attendance_file'),
            $request->user()->id,
        );
        $message = "{$import->processed_employee_count} karyawan diproses. "
            ."{$import->skipped_without_punch_count} ID tanpa punch dilewati.";

        if ($import->unmatched_employee_count > 0) {
            $message .= " {$import->unmatched_employee_count} NIP tidak ditemukan.";
        }

        if ($import->missing_punch_count > 0) {
            $message .= " {$import->missing_punch_count} punch tidak lengkap.";
        }

        return back()->with('toast', [
            'type' => $import->unmatched_employee_count > 0 || $import->missing_punch_count > 0
                ? 'warning'
                : 'success',
            'title' => 'Import absensi selesai',
            'message' => $message,
        ]);
    }
}
