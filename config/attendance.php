<?php

$dayBreakRules = [
    ['out' => '11:30', 'in' => '12:30'],
    ['out' => '12:00', 'in' => '13:00'],
];
$nightBreakRules = [
    ['out' => '23:30', 'in' => '00:30'],
    ['out' => '00:00', 'in' => '01:00'],
];
$saturdayDayBreakRules = $dayBreakRules;
$saturdayNightBreakRules = [
    ['out' => '20:30', 'in' => '21:30'],
    ['out' => '21:00', 'in' => '22:00'],
];
$qcDayBreakRules = [
    ['out' => '11:00', 'in' => '12:00'],
    ['out' => '12:00', 'in' => '13:00'],
];
$qcFridayBreakRules = [
    ['out' => '10:00', 'in' => '11:30'],
    ['out' => '11:30', 'in' => '13:00'],
];

return [
    'tolerance_minutes' => 3,
    'duplicate_punch_window_minutes' => 10,
    'checkout_review_window_minutes' => 120,
    'anomaly_scan' => [
        'max_range_days' => 31,
        'checkout_proximity_minutes' => 30,
        'short_work_minutes' => 60,
        'long_work_minutes' => 120,
        'overtime_threshold_hours' => 4,
    ],

    'profiles' => [
        'general' => [
            'label' => 'Produksi / Umum',
            'expected_punch_count' => 4,
            'days' => [
                'sunday_thursday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '17:00', 'check_out_day_offset' => 0, 'standard_hours' => 8, 'break_window' => ['11:30', '13:00'], 'break_rules' => $dayBreakRules],
                    'night' => ['check_in' => '20:00', 'check_out' => '05:00', 'check_out_day_offset' => 1, 'standard_hours' => 8, 'break_window' => ['23:00', '01:00'], 'break_rules' => $nightBreakRules],
                ],
                'friday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '17:00', 'check_out_day_offset' => 0, 'standard_hours' => 7.5, 'break_window' => ['11:30', '13:00'], 'break_rules' => [['out' => '11:30', 'in' => '13:00']]],
                    'night' => ['check_in' => '20:00', 'check_out' => '05:00', 'check_out_day_offset' => 1, 'standard_hours' => 8, 'break_window' => ['23:00', '01:00'], 'break_rules' => $nightBreakRules],
                ],
                'saturday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '15:00', 'check_out_day_offset' => 0, 'standard_hours' => 6, 'break_window' => ['11:30', '13:00'], 'break_rules' => $saturdayDayBreakRules],
                    'night' => ['check_in' => '17:00', 'check_out' => '00:00', 'check_out_day_offset' => 1, 'standard_hours' => 6, 'break_window' => ['20:30', '22:00'], 'break_rules' => $saturdayNightBreakRules],
                ],
            ],
        ],
        'security' => [
            'label' => 'Security',
            'expected_punch_count' => 2,
            'days' => [
                'sunday_thursday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '20:00', 'check_out_day_offset' => 0, 'standard_hours' => 8, 'break_window' => ['11:30', '13:00'], 'break_rules' => $dayBreakRules],
                    'night' => ['check_in' => '20:00', 'check_out' => '08:00', 'check_out_day_offset' => 1, 'standard_hours' => 8, 'break_window' => ['23:00', '01:00'], 'break_rules' => $nightBreakRules],
                ],
                'friday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '20:00', 'check_out_day_offset' => 0, 'standard_hours' => 8, 'break_window' => ['11:30', '13:00'], 'break_rules' => [['out' => '11:30', 'in' => '13:00']]],
                    'night' => ['check_in' => '20:00', 'check_out' => '08:00', 'check_out_day_offset' => 1, 'standard_hours' => 8, 'break_window' => ['23:00', '01:00'], 'break_rules' => $nightBreakRules],
                ],
                'saturday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '17:00', 'check_out_day_offset' => 0, 'standard_hours' => 6, 'break_hours' => 1, 'break_window' => ['11:30', '13:00'], 'break_rules' => $saturdayDayBreakRules],
                    'night' => ['check_in' => '17:00', 'check_out' => '00:00', 'check_out_day_offset' => 1, 'standard_hours' => 6, 'break_hours' => 1, 'break_window' => ['20:30', '22:00'], 'break_rules' => $saturdayNightBreakRules],
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
                    'day' => ['check_in' => '08:00', 'check_out' => '17:00', 'check_out_day_offset' => 0, 'standard_hours' => 8, 'break_window' => ['11:00', '13:00'], 'break_rules' => $qcDayBreakRules],
                    'night' => ['check_in' => '20:00', 'check_out' => '05:00', 'check_out_day_offset' => 1, 'standard_hours' => 8, 'break_window' => ['23:00', '01:00'], 'break_rules' => $nightBreakRules],
                ],
                'friday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '17:00', 'check_out_day_offset' => 0, 'standard_hours' => 7.5, 'break_window' => ['10:00', '13:00'], 'break_rules' => $qcFridayBreakRules],
                    'night' => ['check_in' => '20:00', 'check_out' => '05:00', 'check_out_day_offset' => 1, 'standard_hours' => 8, 'break_window' => ['23:00', '01:00'], 'break_rules' => $nightBreakRules],
                ],
                'saturday' => [
                    'day' => ['check_in' => '08:00', 'check_out' => '15:00', 'check_out_day_offset' => 0, 'standard_hours' => 6, 'break_window' => ['11:00', '13:00'], 'break_rules' => $qcDayBreakRules],
                    'night' => ['check_in' => '20:00', 'check_out' => '03:00', 'check_out_day_offset' => 1, 'standard_hours' => 6, 'break_window' => ['23:00', '01:00'], 'break_rules' => $nightBreakRules],
                ],
            ],
        ],
    ],
];
