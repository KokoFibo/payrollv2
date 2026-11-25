<?php

return [
    'tolerance_minutes' => 3,
    'duplicate_punch_window_minutes' => 10,

    'profiles' => [
        'general' => [
            'label' => 'Produksi / Umum',
            'expected_punch_count' => 4,
            'days' => [
                'sunday_thursday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '17:00', 'check_out_day_offset' => 0, 'standard_hours' => 8],
                    'night' => ['check_in' => '20:00', 'check_out' => '05:00', 'check_out_day_offset' => 1, 'standard_hours' => 8],
                ],
                'friday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '17:00', 'check_out_day_offset' => 0, 'standard_hours' => 7.5],
                    'night' => ['check_in' => '20:00', 'check_out' => '05:00', 'check_out_day_offset' => 1, 'standard_hours' => 7.5],
                ],
                'saturday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '15:00', 'check_out_day_offset' => 0, 'standard_hours' => 6],
                    'night' => ['check_in' => '20:00', 'check_out' => '05:00', 'check_out_day_offset' => 1, 'standard_hours' => 6],
                ],
            ],
        ],
        'security' => [
            'label' => 'Security',
            'expected_punch_count' => 2,
            'days' => [
                'sunday_thursday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '20:00', 'check_out_day_offset' => 0, 'standard_hours' => 8],
                    'night' => ['check_in' => '20:00', 'check_out' => '08:00', 'check_out_day_offset' => 1, 'standard_hours' => 8],
                ],
                'friday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '20:00', 'check_out_day_offset' => 0, 'standard_hours' => 7.5],
                    'night' => ['check_in' => '20:00', 'check_out' => '08:00', 'check_out_day_offset' => 1, 'standard_hours' => 7.5],
                ],
                'saturday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '17:00', 'check_out_day_offset' => 0, 'standard_hours' => 6],
                    'night' => ['check_in' => '17:00', 'check_out' => '08:00', 'check_out_day_offset' => 1, 'standard_hours' => 6],
                ],
            ],
        ],
        'qc_aging' => [
            'label' => 'QC Aging',
            'expected_punch_count' => 4,
            'requires_review' => true,
            'review_note' => 'Jam selesai QC Aging shift pagi belum didefinisikan secara lengkap.',
            'days' => [
                'sunday_thursday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '17:00', 'check_out_day_offset' => 0, 'standard_hours' => 8],
                    'night' => ['check_in' => '20:00', 'check_out' => '05:00', 'check_out_day_offset' => 1, 'standard_hours' => 8],
                ],
                'friday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '17:00', 'check_out_day_offset' => 0, 'standard_hours' => 7.5],
                    'night' => ['check_in' => '20:00', 'check_out' => '05:00', 'check_out_day_offset' => 1, 'standard_hours' => 7.5],
                ],
                'saturday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '15:00', 'check_out_day_offset' => 0, 'standard_hours' => 6],
                    'night' => ['check_in' => '20:00', 'check_out' => '03:00', 'check_out_day_offset' => 1, 'standard_hours' => 6],
                ],
            ],
        ],
    ],
];
