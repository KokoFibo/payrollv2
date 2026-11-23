<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Etnis;
use App\Models\Jabatan;
use App\Models\JobGrade;

final class MasterDataResources
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'companies' => [
                'model' => Company::class,
                'table' => 'companies',
                'title' => 'Company',
                'singular' => 'Company',
                'description' => 'Kelola entitas perusahaan yang dapat dipilih pada data karyawan.',
                'search' => ['name'],
                'order_by' => 'name',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama Company', 'placeholder' => 'Contoh: ASB'],
                ],
            ],
            'directorates' => [
                'model' => Directorate::class,
                'table' => 'directorates',
                'title' => 'Directorate',
                'singular' => 'Directorate',
                'description' => 'Kelola directorate atau placement tujuan penempatan karyawan.',
                'search' => ['name'],
                'order_by' => 'name',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama Directorate', 'placeholder' => 'Contoh: Finance Center'],
                ],
            ],
            'departments' => [
                'model' => Department::class,
                'table' => 'departments',
                'title' => 'Department',
                'singular' => 'Department',
                'description' => 'Kelola departemen untuk struktur organisasi dan penempatan karyawan.',
                'search' => ['name'],
                'order_by' => 'name',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama Department', 'placeholder' => 'Contoh: Finance Accounting'],
                ],
            ],
            'jabatans' => [
                'model' => Jabatan::class,
                'table' => 'jabatans',
                'title' => 'Jabatan',
                'singular' => 'Jabatan',
                'description' => 'Kelola daftar jabatan yang digunakan pada profil karyawan.',
                'search' => ['name'],
                'order_by' => 'name',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama Jabatan', 'placeholder' => 'Contoh: Supervisor'],
                ],
            ],
            'etnis' => [
                'model' => Etnis::class,
                'table' => 'etnis',
                'title' => 'Etnis',
                'singular' => 'Etnis',
                'description' => 'Kelola daftar etnis yang dapat dipilih pada data karyawan.',
                'search' => ['name'],
                'order_by' => 'name',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama Etnis', 'placeholder' => 'Contoh: Jawa'],
                ],
            ],
            'job-grades' => [
                'model' => JobGrade::class,
                'table' => 'job_grades',
                'title' => 'Job Grade',
                'singular' => 'Job Grade',
                'description' => 'Kelola kode dan nama grade untuk pemetaan level jabatan.',
                'search' => ['grade', 'grade_name'],
                'order_by' => 'grade',
                'fields' => [
                    ['name' => 'grade', 'label' => 'Grade', 'placeholder' => 'Contoh: M1'],
                    ['name' => 'grade_name', 'label' => 'Nama Grade', 'placeholder' => 'Contoh: Supervisor'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function find(string $resource): array
    {
        return self::all()[$resource] ?? abort(404);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, array<int, string>>
     */
    public static function rules(array $config, ?int $editingId = null): array
    {
        $rules = [];

        foreach ($config['fields'] as $field) {
            $fieldRules = ['required', 'string', 'max:255'];

            if (in_array($field['name'], ['name', 'grade'], true)) {
                $fieldRules[] = 'unique:'.$config['table'].','.$field['name'].($editingId ? ','.$editingId : '');
            }

            $rules[$field['name']] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function displayName(object $record, array $config): string
    {
        if ($config['table'] === 'job_grades') {
            return trim($record->grade.' - '.$record->grade_name);
        }

        return (string) $record->name;
    }
}
