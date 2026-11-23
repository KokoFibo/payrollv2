<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('payroll.settings', []) as $group => $settings) {
            foreach ($settings as $key => $configuration) {
                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'group' => $group,
                        'label' => $configuration['label'],
                        'value' => (string) $configuration['value'],
                        'type' => $configuration['type'] ?? 'string',
                    ],
                );
            }
        }
    }
}
