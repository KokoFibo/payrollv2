<?php

return [
    'company_name' => env('PAYROLL_COMPANY_NAME', 'Payroll Workspace'),
    'currency' => 'IDR',
    'settings' => [
        'general' => [
            'company_name' => [
                'label' => 'Nama Organisasi',
                'value' => env('PAYROLL_COMPANY_NAME', 'Payroll Workspace'),
                'type' => 'string',
            ],
            'locale' => [
                'label' => 'Bahasa',
                'value' => 'id',
                'type' => 'string',
            ],
            'timezone' => [
                'label' => 'Zona Waktu',
                'value' => 'Asia/Jakarta',
                'type' => 'string',
            ],
        ],
        'payroll' => [
            'cutoff_start_day' => [
                'label' => 'Tanggal Awal Cutoff',
                'value' => '25',
                'type' => 'integer',
            ],
            'cutoff_end_day' => [
                'label' => 'Tanggal Akhir Cutoff',
                'value' => '24',
                'type' => 'integer',
            ],
            'pay_date' => [
                'label' => 'Tanggal Pembayaran',
                'value' => '25',
                'type' => 'integer',
            ],
        ],
        'attendance' => [
            'work_start_time' => [
                'label' => 'Jam Masuk',
                'value' => '08:00',
                'type' => 'string',
            ],
            'late_tolerance_minutes' => [
                'label' => 'Toleransi Keterlambatan',
                'value' => '15',
                'type' => 'integer',
            ],
        ],
    ],
];
