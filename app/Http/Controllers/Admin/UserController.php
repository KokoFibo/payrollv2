<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const USER_MANAGER_ROLES = [7, 8];

    public function index(Request $request): Response
    {
        Gate::authorize('manage-users');

        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', '');

        $users = User::query()
            ->with('assignedRole:id,name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', fn ($query) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'role' => $user->role,
                'role_name' => $user->assignedRole?->name,
                'language' => $user->language,
                'email_verified' => $user->email_verified_at !== null,
                'is_current_user' => $request->user()->is($user),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $role,
            ],
            'roles' => Role::query()
                ->whereIn('id', [3, 4, 5, 6, 7, 8])
                ->orderByDesc('id')
                ->get(['id', 'name'])
                ->map(fn (Role $role): array => [
                    'value' => $role->id,
                    'label' => $role->name,
                ])
                ->all(),
            'stats' => [
                'total' => User::query()->count(),
                'admins' => User::query()->whereIn('role', [5, 6, 7, 8])->count(),
                'privileged' => User::query()->whereIn('role', self::USER_MANAGER_ROLES)->count(),
            ],
        ]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::query()->create($validated);
        $user->forceFill([
            'email_verified_at' => now(),
            'must_change_password' => true,
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'User ditambahkan',
            'message' => "{$validated['name']} dapat login menggunakan akun yang baru dibuat.",
        ]);
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->user()->is($user) && ! in_array((int) $validated['role'], self::USER_MANAGER_ROLES, true)) {
            return back()->withErrors([
                'role' => 'Anda tidak dapat menurunkan role akun yang sedang digunakan.',
            ]);
        }

        $passwordWasChanged = ($validated['password'] ?? null) !== null;

        if (! $passwordWasChanged) {
            unset($validated['password']);
        }

        $user->update($validated);

        if ($passwordWasChanged) {
            $user->forceFill(['must_change_password' => true])->save();
        }

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'User diperbarui',
            'message' => "Data user {$user->name} berhasil disimpan.",
        ]);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        if ($request->user()->is($user)) {
            return back()->withErrors([
                'user' => 'Anda tidak dapat menghapus akun yang sedang digunakan.',
            ]);
        }

        if ((int) $user->role === 8 && User::query()->where('role', 8)->count() <= 1) {
            return back()->withErrors([
                'user' => 'Developer terakhir tidak dapat dihapus.',
            ]);
        }

        $user->delete();

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'User dihapus',
            'message' => 'Akun pengguna berhasil dihapus.',
        ]);
    }
}
