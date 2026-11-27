<?php

namespace App\Http\Requests;

use App\Enums\KaryawanStatus;
use App\Enums\PayrollMethod;
use App\Models\Karyawan;
use App\Models\User;
use App\Support\AdminSalaryVisibility;
use App\Support\KaryawanOptions;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveKaryawanRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();
        $karyawan = $this->route('karyawan');

        if ($karyawan instanceof Karyawan) {
            return $user?->can('update', $karyawan) ?? false;
        }

        return $user?->can('create', Karyawan::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $karyawan = $this->route('karyawan');
        $karyawanId = $karyawan instanceof Karyawan ? $karyawan->getKey() : null;
        $salaryRestricted = $this->salaryRestricted();

        return [
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('karyawans', 'email')->ignore($karyawanId),
                function (string $attribute, mixed $value, Closure $fail) use ($karyawan): void {
                    if ($value === null) {
                        return;
                    }

                    if (
                        $karyawan instanceof Karyawan
                        && strcasecmp(trim((string) $karyawan->email), trim((string) $value)) === 0
                    ) {
                        return;
                    }

                    $isApplicationUser = User::query()
                        ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $value))])
                        ->exists();

                    if ($isApplicationUser) {
                        $fail('Email ini terdaftar sebagai user aplikasi, bukan karyawan.');
                    }
                },
            ],
            'hp' => ['nullable', 'string', 'max:30'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'status_pernikahan' => ['nullable', Rule::in(KaryawanOptions::MARITAL_STATUSES)],
            'golongan_darah' => ['nullable', Rule::in(['A', 'B', 'AB', 'O'])],
            'agama' => ['nullable', Rule::in(KaryawanOptions::AGAMA)],
            'etnis_id' => ['required', 'integer', 'exists:etnis,id'],
            'jenis_identitas' => ['required', Rule::in(['KTP', 'SIM', 'Paspor'])],
            'no_identitas' => ['required', 'string', 'max:50'],
            'alamat_identitas' => ['required', 'string', 'max:255'],
            'alamat_tinggal_sekarang' => ['required', 'string', 'max:255'],
            'status_karyawan' => ['required', Rule::enum(KaryawanStatus::class)],
            'tanggal_bergabung' => ['required', 'date'],
            'tanggal_resigned' => [
                'nullable',
                'date',
                Rule::requiredIf($this->input('status_karyawan') === KaryawanStatus::Resigned->value),
            ],
            'tanggal_blacklist' => [
                'nullable',
                'date',
                Rule::requiredIf($this->input('status_karyawan') === KaryawanStatus::Blacklist->value),
            ],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'directorate_id' => ['required', 'integer', 'exists:directorates,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'jabatan_id' => ['required', 'integer', 'exists:jabatans,id'],
            'job_grade_id' => ['nullable', 'integer', 'exists:job_grades,id'],
            'nama_bank' => ['nullable', Rule::in(KaryawanOptions::BANKS)],
            'nomor_rekening' => [
                'nullable',
                'string',
                'max:30',
                function (string $attribute, mixed $value, Closure $fail) use ($karyawan): void {
                    $hash = karyawan_blind_index($value);

                    if ($hash === null) {
                        return;
                    }

                    $duplicateExists = Karyawan::query()
                        ->where('nomor_rekening_hash', $hash)
                        ->when(
                            $karyawan instanceof Karyawan,
                            fn ($query) => $query->where('id', '!=', $karyawan->id),
                        )
                        ->exists();

                    if ($duplicateExists) {
                        $fail('Nomor rekening sudah digunakan.');
                    }
                },
            ],
            'metode_penggajian' => ['required', Rule::enum(PayrollMethod::class)],
            'gaji_pokok' => $salaryRestricted ? ['nullable', 'integer', 'min:0'] : ['required', 'integer', 'min:0'],
            'gaji_tetap' => ['nullable', 'integer', 'min:0'],
            'gaji_overtime' => ['required', 'integer', 'min:0'],
            'gaji_shift_malam_satpam' => ['nullable', 'integer', 'min:0'],
            'bonus' => ['nullable', 'integer', 'min:0'],
            'tunjangan_jabatan' => ['nullable', 'integer', 'min:0'],
            'tunjangan_bahasa' => ['nullable', 'integer', 'min:0'],
            'tunjangan_skill' => ['nullable', 'integer', 'min:0'],
            'tunjangan_lembur_sabtu' => ['nullable', 'integer', 'min:0'],
            'tunjangan_lama_kerja' => ['nullable', 'integer', 'min:0'],
            'tunjangan_housing' => ['nullable', 'integer', 'min:0'],
            'iuran_air' => ['required', 'integer', 'min:0'],
            'denda' => ['nullable', 'integer', 'min:0'],
            'iuran_locker' => ['nullable', 'integer', 'min:0'],
            'gaji_bpjs' => ['nullable', 'integer', 'min:0'],
            'potongan_jht' => ['boolean'],
            'potongan_jp' => ['boolean'],
            'potongan_jkk' => ['boolean'],
            'potongan_jkm' => ['boolean'],
            'potongan_kesehatan' => ['boolean'],
            'no_npwp' => ['nullable', 'string', 'max:50'],
            'ptkp' => ['nullable', Rule::in(array_keys(KaryawanOptions::PTKP))],
            'nama_contact_darurat' => ['nullable', 'string', 'max:255'],
            'nama_contact_darurat_2' => ['nullable', 'string', 'max:255'],
            'contact_darurat_1' => ['nullable', 'string', 'max:30'],
            'contact_darurat_2' => ['nullable', 'string', 'max:30'],
            'hubungan_1' => ['nullable', 'string', 'max:50'],
            'hubungan_2' => ['nullable', 'string', 'max:50'],
            'tanggungan' => ['nullable', 'integer', 'min:0'],
            'pendidikan' => ['required', Rule::in(KaryawanOptions::PENDIDIKAN)],
            'jurusan' => ['nullable', 'string', 'max:100'],
            'nama_kampus' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function salaryRestricted(): bool
    {
        $karyawan = $this->route('karyawan');
        $user = $this->user();

        return $karyawan instanceof Karyawan
            && $user instanceof User
            && app(AdminSalaryVisibility::class)->shouldMaskSalary($karyawan, $user);
    }
}
