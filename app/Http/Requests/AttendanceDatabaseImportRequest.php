<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceDatabaseImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-payroll') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attendance_mode' => ['nullable', Rule::in(['date', 'month'])],
            'attendance_date' => [
                'nullable',
                'date_format:Y-m-d',
                'required_unless:attendance_mode,month',
            ],
            'attendance_month' => [
                'nullable',
                'date_format:Y-m',
                'required_if:attendance_mode,month',
            ],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([15, 30, 50, 100])],
        ];
    }
}
