<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RequiredPasswordController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->user()?->must_change_password) {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('Auth/ChangeRequiredPassword', [
            'userName' => $request->user()->name,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'different:current_password',
                Password::defaults(),
                'confirmed',
            ],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        return redirect()
            ->route('admin.dashboard')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Password diperbarui',
                'message' => 'Password baru berhasil disimpan. Anda sekarang dapat menggunakan aplikasi.',
            ]);
    }
}
