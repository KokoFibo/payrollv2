<?php

namespace Database\Factories;

use App\Enums\KaryawanStatus;
use App\Enums\OutsourceType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Etnis;
use App\Models\Jabatan;
use App\Models\JobGrade;
use App\Models\Karyawan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Karyawan>
 */
class KaryawanFactory extends Factory
{
    public function definition(): array
    {
        $status = fake()->randomElement(KaryawanStatus::cases())->value;

        return [
            'nip' => fake()->unique()->numberBetween(10000, 999999),
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'hp' => fake()->phoneNumber(),
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['Laki-laki', 'Perempuan']),
            'status_pernikahan' => fake()->randomElement(['Belum Menikah', 'Menikah']),
            'agama' => fake()->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Budha']),
            'etnis_id' => Etnis::factory(),
            'jenis_identitas' => 'KTP',
            'no_identitas' => fake()->unique()->numerify('################'),
            'alamat_identitas' => fake()->address(),
            'alamat_tinggal' => fake()->address(),
            'status_karyawan' => $status,
            'tanggal_bergabung' => fake()->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
            'tanggal_resigned' => $status === KaryawanStatus::Resigned->value
                ? fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d')
                : null,
            'tanggal_blacklist' => $status === KaryawanStatus::Blacklist->value
                ? fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d')
                : null,
            'company_id' => Company::factory(),
            'directorate_id' => Directorate::factory(),
            'department_id' => Department::factory(),
            'jabatan_id' => Jabatan::factory(),
            'job_grade_id' => JobGrade::factory(),
            'outsource' => OutsourceType::Os->value,
            'metode_penggajian' => 'Bulanan',
            'gaji_pokok' => fake()->numberBetween(4_000_000, 25_000_000),
            'gaji_overtime' => fake()->numberBetween(100_000, 2_000_000),
            'iuran_air' => fake()->numberBetween(0, 200_000),
            'tanggal_update' => now()->toDateString(),
            'pendidikan' => fake()->randomElement(['SMA/SMK', 'D3', 'S1', 'S2']),
            'jurusan' => fake()->word(),
            'nama_kampus' => fake()->company(),
        ];
    }
}
