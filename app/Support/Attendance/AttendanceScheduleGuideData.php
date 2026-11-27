<?php

namespace App\Support\Attendance;

class AttendanceScheduleGuideData
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     review_required: bool,
     *     notice: string|null,
     *     days: list<array{
     *         key: string,
     *         label: string,
     *         shifts: list<array{
     *             key: string,
     *             label: string,
     *             time: string,
     *             break: string,
     *             standard_hours: float,
     *             punch_count: int,
     *             note: string
     *         }>
     *     }>
     * }>
     */
    public function profiles(): array
    {
        $profiles = config('attendance.profiles', []);
        $days = [
            'sunday_thursday' => 'Minggu - Kamis',
            'friday' => 'Jumat',
            'saturday' => 'Sabtu',
        ];
        $shifts = [
            'day' => 'Shift pagi',
            'night' => 'Shift malam',
        ];
        $result = [];

        foreach ($profiles as $profileKey => $profile) {
            if (! is_array($profile)) {
                continue;
            }

            $profileDays = [];

            foreach ($days as $dayKey => $dayLabel) {
                $profileShifts = [];

                foreach ($shifts as $shiftKey => $shiftLabel) {
                    $shift = data_get($profile, "days.{$dayKey}.{$shiftKey}");

                    if (! is_array($shift)) {
                        continue;
                    }

                    $profileShifts[] = [
                        'key' => $shiftKey,
                        'label' => $shiftLabel,
                        'time' => $this->timeRange($shift),
                        'break' => $this->breakLabel($shift),
                        'standard_hours' => (float) ($shift['standard_hours'] ?? 8),
                        'punch_count' => (int) ($profile['expected_punch_count'] ?? 4),
                        'note' => $this->shiftNote($profileKey, $dayKey, $shiftKey),
                    ];
                }

                if ($profileShifts !== []) {
                    $profileDays[] = [
                        'key' => $dayKey,
                        'label' => $dayLabel,
                        'shifts' => $profileShifts,
                    ];
                }
            }

            $result[] = [
                'key' => $profileKey,
                'label' => (string) ($profile['label'] ?? $profileKey),
                'review_required' => (bool) ($profile['requires_review'] ?? false),
                'notice' => $profile['review_note'] ?? null,
                'days' => $profileDays,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{
     *     title: string,
     *     items: list<array{title: string, value: string, description: string}>
     * }>
     */
    public function requirements(): array
    {
        $tolerance = (int) config('attendance.tolerance_minutes', 3);

        return [
            [
                'title' => 'Jam kerja dan istirahat',
                'items' => [
                    [
                        'title' => 'Dasar jam kerja reguler',
                        'value' => 'Jam kerja maksimal',
                        'description' => 'Semua profile dan hari memakai jam kerja maksimal jadwal sebagai dasar. Keterlambatan dan pulang awal dipotong setelahnya.',
                    ],
                    [
                        'title' => 'Hari kerja normal',
                        'value' => 'Maksimal 8 jam',
                        'description' => 'Berlaku Minggu-Kamis untuk Produksi/Umum, Security, dan QC Aging sesuai detail shift.',
                    ],
                    [
                        'title' => 'Hari Jumat',
                        'value' => '7,5 jam',
                        'description' => 'Produksi/Umum dan QC Aging shift pagi memiliki jam reguler 7,5 jam. Shift malam tetap 8 jam.',
                    ],
                    [
                        'title' => 'Hari Sabtu',
                        'value' => '6 jam',
                        'description' => 'Shift pagi dan malam dihitung maksimal 6 jam kerja reguler.',
                    ],
                    [
                        'title' => 'Waktu istirahat',
                        'value' => 'Sesuai shift',
                        'description' => 'Jam istirahat setiap profile, hari, dan shift dirinci pada pilihan Jadwal Reguler.',
                    ],
                    [
                        'title' => 'Masuk lebih awal',
                        'value' => 'Tidak menambah lembur',
                        'description' => 'Waktu sebelum jam masuk reguler tidak otomatis dihitung sebagai jam kerja atau lembur.',
                    ],
                ],
            ],
            [
                'title' => 'Ketentuan per profile',
                'items' => [
                    [
                        'title' => 'Produksi / Umum',
                        'value' => '4 punch',
                        'description' => 'Punch masuk, keluar istirahat, masuk istirahat, dan keluar akhir shift. Shift malam dapat melewati tengah malam.',
                    ],
                    [
                        'title' => 'Security',
                        'value' => '2 punch',
                        'description' => 'Minggu-Jumat memakai shift 12 jam dengan 8 jam reguler. Sabtu memakai 6 jam reguler.',
                    ],
                    [
                        'title' => 'Security checkout awal',
                        'value' => 'Hitung shift penuh',
                        'description' => 'Checkout sebelum jadwal pulang tetap dihitung sebagai jam kerja penuh sesuai aturan Security saat ini.',
                    ],
                    [
                        'title' => 'QC Aging',
                        'value' => '4 punch + review',
                        'description' => 'Karena detail pembagian keluar-masuk belum lengkap, hasil QC Aging selalu memerlukan pemeriksaan admin.',
                    ],
                ],
            ],
            [
                'title' => 'Keterlambatan',
                'items' => [
                    [
                        'title' => 'Toleransi masuk',
                        'value' => $tolerance.' menit',
                        'description' => 'Masuk dalam batas toleransi masih dianggap tepat waktu.',
                    ],
                    [
                        'title' => 'Potongan keterlambatan',
                        'value' => 'Dibulatkan per jam',
                        'description' => 'Total menit terlambat dibulatkan ke atas menjadi jam penuh, lalu dikurangkan dari jam kerja.',
                    ],
                ],
            ],
            [
                'title' => 'Perhitungan lembur',
                'items' => [
                    [
                        'title' => 'Lembur umum',
                        'value' => 'Per 30 menit',
                        'description' => 'Kelebihan waktu setelah jadwal pulang dibulatkan ke bawah setiap 30 menit.',
                    ],
                    [
                        'title' => 'Security Sabtu malam',
                        'value' => '00:00-05:00 x2',
                        'description' => 'Lembur sebelum pukul 05:00 dikali dua. Setelah 05:00 dihitung satu kali.',
                    ],
                    [
                        'title' => 'Contoh Security Sabtu malam',
                        'value' => '17:00-06:29',
                        'description' => 'Menghasilkan 6 jam kerja reguler dan 11 jam lembur: 5 x 2 + 1 x 1.',
                    ],
                ],
            ],
            [
                'title' => 'Punch dan review',
                'items' => [
                    [
                        'title' => 'Punch tidak lengkap',
                        'value' => 'Wajib review',
                        'description' => 'Punch masuk, keluar, istirahat, atau lembur yang tidak lengkap ditandai untuk pemeriksaan admin.',
                    ],
                    [
                        'title' => 'Punch berulang',
                        'value' => 'Tidak otomatis review',
                        'description' => 'Punch yang berulang dalam waktu berdekatan disimpan sekali; admin tetap dapat memeriksa hasilnya.',
                    ],
                    [
                        'title' => 'Penyelesaian review',
                        'value' => 'Tandai benar',
                        'description' => 'Setelah data dikoreksi, admin menandai review selesai dan sistem menyimpan nama serta waktu pemeriksaan.',
                    ],
                ],
            ],
            [
                'title' => 'Hari libur dan jadwal khusus',
                'items' => [
                    [
                        'title' => 'Hari libur',
                        'value' => 'Jam kerja x2',
                        'description' => 'Jam kerja dan lembur pada hari libur atau Minggu dikali dua, kecuali pengecualian perusahaan menentukan lain.',
                    ],
                    [
                        'title' => 'Prioritas jadwal',
                        'value' => 'Paling spesifik',
                        'description' => 'Jadwal khusus per company/department, profile, tipe hari, shift, dan tanggal terbaru diprioritaskan.',
                    ],
                    [
                        'title' => 'QC Aging',
                        'value' => 'Selalu review',
                        'description' => 'Detail pembagian keluar-masuk QC Aging masih perlu pemeriksaan manual.',
                    ],
                ],
            ],
            [
                'title' => 'Perubahan jadwal Ramadan',
                'items' => [
                    [
                        'title' => 'Tempat input',
                        'value' => 'Tab Jadwal Kerja',
                        'description' => 'Isi bagian Jadwal Perubahan / Ramadan di bagian bawah tab Jadwal Kerja.',
                    ],
                    [
                        'title' => 'Upload dari aplikasi',
                        'value' => 'Developer Settings',
                        'description' => 'Upload dilakukan di Developer Settings > Jadwal Kerja agar jadwal reguler dan jadwal khusus tetap terpusat.',
                    ],
                    [
                        'title' => 'Jadwal lain',
                        'value' => 'Tetap tersimpan',
                        'description' => 'Import hanya menambah atau memperbarui baris dengan kunci yang sama dan tidak menghapus jadwal lain.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $shift
     */
    private function timeRange(array $shift): string
    {
        $checkIn = (string) ($shift['check_in'] ?? '-');
        $checkOut = (string) ($shift['check_out'] ?? '-');
        $dayOffset = (int) ($shift['check_out_day_offset'] ?? 0);

        return $checkIn.' - '.$checkOut.($dayOffset > 0 ? ' (+'.$dayOffset.' hari)' : '');
    }

    /**
     * @param  array<string, mixed>  $shift
     */
    private function breakLabel(array $shift): string
    {
        $rules = $shift['break_rules'] ?? [];

        if (! is_array($rules) || $rules === []) {
            $window = $shift['break_window'] ?? null;

            if (! is_array($window) || count($window) < 2) {
                return 'Tidak ada istirahat';
            }

            return $window[0].' - '.$window[1];
        }

        $labels = [];

        foreach (array_values($rules) as $index => $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $labels[] = 'Istirahat '.($index + 1).': '
                .($rule['out'] ?? '-')
                .' - '
                .($rule['in'] ?? '-');
        }

        return $labels === [] ? 'Tidak ada istirahat' : implode('; ', $labels);
    }

    private function shiftNote(string $profile, string $dayType, string $shift): string
    {
        if ($profile === 'security' && $dayType === 'saturday' && $shift === 'night') {
            return 'Lembur 00:00-05:00 x2, setelah 05:00 x1.';
        }

        if ($profile === 'security') {
            return 'Kelebihan setelah jam pulang dihitung lembur.';
        }

        if ($profile === 'qc_aging') {
            return 'Jadwal khusus, hasil tetap perlu review.';
        }

        return 'Lembur dihitung setelah jam pulang.';
    }
}
