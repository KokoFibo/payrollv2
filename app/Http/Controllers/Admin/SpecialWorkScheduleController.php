<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpecialWorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SpecialWorkScheduleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('access-developer-settings');

        SpecialWorkSchedule::query()->create($this->validatedData($request));

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Jadwal khusus ditambahkan',
            'message' => 'Periode jadwal kerja khusus berhasil dibuat.',
        ]);
    }

    public function update(Request $request, SpecialWorkSchedule $specialWorkSchedule): RedirectResponse
    {
        Gate::authorize('access-developer-settings');

        $specialWorkSchedule->update($this->validatedData($request));

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Jadwal khusus diperbarui',
            'message' => 'Perubahan jadwal kerja khusus berhasil disimpan.',
        ]);
    }

    public function destroy(SpecialWorkSchedule $specialWorkSchedule): RedirectResponse
    {
        Gate::authorize('access-developer-settings');

        $specialWorkSchedule->delete();

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Jadwal khusus dihapus',
            'message' => 'Jadwal kerja khusus berhasil dihapus.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'directorate_id' => ['nullable', 'integer', 'exists:directorates,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'schedule_profile' => [
                'nullable',
                Rule::in(array_keys(SpecialWorkSchedule::PROFILES)),
            ],
            'day_type' => [
                'nullable',
                Rule::in(array_keys(SpecialWorkSchedule::DAY_TYPES)),
            ],
            'shift' => [
                'nullable',
                Rule::in(array_keys(SpecialWorkSchedule::SHIFTS)),
            ],
            'check_in' => ['required', 'date_format:H:i'],
            'check_out' => ['required', 'date_format:H:i'],
            'check_out_day_offset' => ['required', 'integer', 'between:0,2'],
            'standard_work_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'expected_punch_count' => ['required', 'integer', 'between:0,6'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
