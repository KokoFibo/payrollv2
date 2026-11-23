<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'username' => [
                'nullable',
                'integer',
                'min:0',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'role' => ['required', 'integer', 'exists:roles,id'],
            'language' => ['required', Rule::in(['Id', 'En'])],
            'password' => [
                $user instanceof User ? 'nullable' : 'required',
                'confirmed',
                Password::defaults(),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'username' => $this->filled('username') ? $this->input('username') : null,
            'password' => $this->filled('password') ? $this->input('password') : null,
        ]);
    }
}
