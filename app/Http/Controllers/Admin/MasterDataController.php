<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\MasterDataResources;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MasterDataController extends Controller
{
    public function store(Request $request, string $resource): RedirectResponse
    {
        $this->authorizeResource();
        $config = MasterDataResources::find($resource);
        $validated = $request->validate(MasterDataResources::rules($config));
        $modelClass = $config['model'];
        $record = $modelClass::query()->create($validated);

        return $this->redirectToResource(
            $resource,
            'Master data disimpan',
            MasterDataResources::displayName($record, $config).' berhasil ditambahkan.',
        );
    }

    public function update(Request $request, string $resource, int $record): RedirectResponse
    {
        $this->authorizeResource();
        $config = MasterDataResources::find($resource);
        $modelClass = $config['model'];
        $masterRecord = $modelClass::query()->findOrFail($record);
        $validated = $request->validate(MasterDataResources::rules($config, $masterRecord->getKey()));
        $masterRecord->update($validated);

        return $this->redirectToResource(
            $resource,
            'Master data disimpan',
            MasterDataResources::displayName($masterRecord->refresh(), $config).' berhasil diperbarui.',
        );
    }

    public function destroy(string $resource, int $record): RedirectResponse
    {
        $this->authorizeResource();
        $config = MasterDataResources::find($resource);
        $modelClass = $config['model'];
        $masterRecord = $modelClass::query()->withCount('karyawans')->findOrFail($record);
        $name = MasterDataResources::displayName($masterRecord, $config);
        $linkedCount = (int) $masterRecord->karyawans_count;
        $masterRecord->delete();

        return $this->redirectToResource(
            $resource,
            'Master data dihapus',
            $linkedCount > 0
                ? "{$name} dihapus. {$linkedCount} karyawan kini tidak memiliki relasi ini."
                : "{$name} berhasil dihapus.",
        );
    }

    private function authorizeResource(): void
    {
        Gate::authorize('access-developer-settings');
    }

    private function redirectToResource(string $resource, string $title, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.developer-settings.index', ['resource' => $resource])
            ->with('toast', [
                'type' => 'success',
                'title' => $title,
                'message' => $message,
            ]);
    }
}
