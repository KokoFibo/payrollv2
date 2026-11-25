<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Attendance\AttendanceCleanupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AttendanceCleanupController extends Controller
{
    public function destroy(
        Request $request,
        AttendanceCleanupService $attendanceCleanupService,
    ): RedirectResponse {
        Gate::authorize('access-developer-settings');

        $validated = $request->validate([
            'months' => ['required', 'array', 'min:1', 'max:60'],
            'months.*' => ['required', 'string', 'distinct', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);
        $result = $attendanceCleanupService->deleteMonths($validated['months']);

        return back()
            ->with('attendance_cleanup_result', $result)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Data presensi dibersihkan',
                'message' => $result['deleted_records']
                    .' rekap presensi dan '
                    .$result['deleted_imports']
                    .' riwayat import berhasil dihapus.',
            ]);
    }
}
