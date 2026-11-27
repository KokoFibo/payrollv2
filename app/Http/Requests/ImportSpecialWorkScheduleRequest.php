<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ImportSpecialWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-developer-settings') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'schedule_file' => [
                'required',
                File::types(['xlsx'])->max(5 * 1024),
                'extensions:xlsx',
            ],
        ];
    }
}
