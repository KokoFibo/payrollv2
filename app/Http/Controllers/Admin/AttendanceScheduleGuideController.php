<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportSpecialWorkScheduleRequest;
use App\Support\Attendance\SpecialWorkScheduleImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceScheduleGuideController extends Controller
{
    public const TemplateFilename = 'Jadwal Kerja Regular Yifang - Final + Jam Kerja Maks.xlsx';

    public function download(): BinaryFileResponse
    {
        Gate::authorize('manage-payroll');

        $path = self::templatePath();
        abort_unless(is_file($path), 404, 'Template pedoman jadwal belum tersedia.');

        return response()->download($path, self::TemplateFilename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(
        ImportSpecialWorkScheduleRequest $request,
        SpecialWorkScheduleImportService $importService,
    ): RedirectResponse {
        $result = $importService->import(
            $request->file('schedule_file'),
            $request->user()->id,
        );

        return redirect()
            ->route('admin.developer-settings.index', [
                'section' => 'work-schedules',
            ])
            ->with('toast', [
                'type' => 'success',
                'title' => 'Jadwal khusus diimpor',
                'message' => "{$result['created']} jadwal baru ditambahkan, "
                    ."{$result['updated']} jadwal diperbarui, dan "
                    ."{$result['recalculated']} attendance dihitung ulang. "
                    .'Jadwal lain tetap tersimpan.',
            ]);
    }

    public static function templatePath(): string
    {
        return resource_path('templates/attendance/'.self::TemplateFilename);
    }
}
