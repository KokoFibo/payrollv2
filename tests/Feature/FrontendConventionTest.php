<?php

use Illuminate\Support\Facades\File;

test('vue application does not use laravel breeze starter components or livewire', function () {
    $forbidden = [
        '@/Components/ApplicationLogo.vue',
        '@/Components/Checkbox.vue',
        '@/Components/DangerButton.vue',
        '@/Components/Dropdown.vue',
        '@/Components/DropdownLink.vue',
        '@/Components/InputError.vue',
        '@/Components/InputLabel.vue',
        '@/Components/Modal.vue',
        '@/Components/NavLink.vue',
        '@/Components/PrimaryButton.vue',
        '@/Components/ResponsiveNavLink.vue',
        '@/Components/SecondaryButton.vue',
        '@/Components/TextInput.vue',
        '@/Layouts/AuthenticatedLayout.vue',
        'wire:',
        '<livewire:',
        'Livewire',
    ];

    $violations = collect(File::allFiles(resource_path('js')))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.vue'))
        ->flatMap(function ($file) use ($forbidden): array {
            $contents = File::get($file->getPathname());

            return collect($forbidden)
                ->filter(fn (string $needle): bool => str_contains($contents, $needle))
                ->map(fn (string $needle): string => $file->getRelativePathname().' contains '.$needle)
                ->all();
        })
        ->values()
        ->all();

    expect($violations)->toBe([]);
});
