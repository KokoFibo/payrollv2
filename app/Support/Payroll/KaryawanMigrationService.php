<?php

namespace App\Support\Payroll;

use App\Enums\KaryawanStatus;
use App\Enums\OutsourceType;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class KaryawanMigrationService
{
    public const SourceTable = 'karyawans';

    private const TargetTable = 'karyawans';

    /**
     * @var list<string>
     */
    private const TargetMasterTables = [
        'companies',
        'directorates',
        'departments',
        'jabatans',
        'job_grades',
    ];

    private const ChunkSize = 250;

    private const LegacySourceNipField = 'id_karyawan';

    /**
     * @var array<string, string>
     */
    private const JobGradeNames = [
        'P1' => 'Non Staff',
        'P2' => 'Non Staff',
        'P3' => 'Junior Staff',
        'P4' => 'Staff',
        'P5' => 'Senior Staff',
        'P6' => 'Junior Staff',
        'P7' => 'Officer',
        'P8' => 'Senior Officer',
        'M1' => 'Junior Supervisor',
        'M2' => 'Supervisor',
        'M3' => 'Senior Supervisor',
        'M4' => 'Junior Manager',
        'M5' => 'Manager',
        'M6' => 'Senior Manager',
        'M7' => 'Director',
        'M8' => 'Senior Director',
        'M9' => 'Vice President',
        'M10' => 'Chairman',
    ];

    /**
     * @var array<string, array{target: string|null, status: string, note: string}>
     */
    private const FieldOverrides = [
        self::LegacySourceNipField => [
            'target' => 'nip',
            'status' => 'renamed',
            'note' => 'Field identifier lama dipetakan menjadi NIP pada tabel target.',
        ],
        'placement_id' => [
            'target' => 'directorate_id',
            'status' => 'renamed',
            'note' => 'Nama field diubah menjadi directorate_id dan mengacu ke tabel directorates.',
        ],
        'potongan_JHT' => [
            'target' => 'potongan_jht',
            'status' => 'renamed',
            'note' => 'Nama field diubah menjadi lowercase.',
        ],
        'potongan_JP' => [
            'target' => 'potongan_jp',
            'status' => 'renamed',
            'note' => 'Nama field diubah menjadi lowercase.',
        ],
        'potongan_JKK' => [
            'target' => 'potongan_jkk',
            'status' => 'renamed',
            'note' => 'Nama field diubah menjadi lowercase.',
        ],
        'potongan_JKM' => [
            'target' => 'potongan_jkm',
            'status' => 'renamed',
            'note' => 'Nama field diubah menjadi lowercase.',
        ],
        'outsource' => [
            'target' => 'outsource',
            'status' => 'transformed',
            'note' => 'Nilai angka 1 menjadi OS dan 0 menjadi Non OS.',
        ],
        'level_jabatan' => [
            'target' => 'job_grade_id',
            'status' => 'renamed',
            'note' => 'Nilai level_jabatan dipetakan langsung ke job_grade_id.',
        ],
        'etnis' => [
            'target' => 'etnis_id',
            'status' => 'renamed',
            'note' => 'Nilai etnis dipetakan ke tabel etnis. Nilai China dinormalisasi menjadi TKA.',
        ],
    ];

    /**
     * @var list<string>
     */
    private const DirectTargetFields = [
        'nama',
        'email',
        'hp',
        'telepon',
        'tempat_lahir',
        'tanggal_lahir',
        'gender',
        'status_pernikahan',
        'golongan_darah',
        'agama',
        'jenis_identitas',
        'no_identitas',
        'alamat_identitas',
        'alamat_tinggal',
        'status_karyawan',
        'tanggal_bergabung',
        'tanggal_resigned',
        'tanggal_blacklist',
        'company_id',
        'department_id',
        'jabatan_id',
        'nama_bank',
        'nomor_rekening',
        'metode_penggajian',
        'gaji_pokok',
        'gaji_tetap',
        'gaji_overtime',
        'gaji_shift_malam_satpam',
        'bonus',
        'tunjangan_jabatan',
        'tunjangan_bahasa',
        'tunjangan_skill',
        'tunjangan_lembur_sabtu',
        'tunjangan_lama_kerja',
        'tunjangan_housing',
        'iuran_air',
        'denda',
        'iuran_locker',
        'gaji_bpjs',
        'no_npwp',
        'ptkp',
        'kontak_darurat',
        'kontak_darurat2',
        'hp1',
        'hp2',
        'hubungan1',
        'hubungan2',
        'tanggungan',
        'tanggal_update',
        'pendidikan',
        'jurusan',
        'nama_kampus',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        $sourceColumns = $this->columnMetadata($this->sourceDatabase(), self::SourceTable);
        $targetColumns = $this->columnMetadata($this->targetDatabase(), self::TargetTable);
        $mappings = $this->buildFieldMappings($sourceColumns, $targetColumns);

        return [
            'source' => [
                'database' => $this->sourceDatabase(),
                'table' => self::SourceTable,
                'row_count' => DB::table($this->sourceTable(self::SourceTable))->count(),
            ],
            'target' => [
                'database' => $this->targetDatabase(),
                'table' => self::TargetTable,
                'row_count' => DB::table(self::TargetTable)->count(),
            ],
            'mappings' => $mappings,
            'summary' => [
                'source_fields' => count($sourceColumns),
                'mapped_fields' => collect($mappings)->where('status', '!=', 'skipped')->where('status', '!=', 'target_only')->count(),
                'renamed_fields' => collect($mappings)->where('status', 'renamed')->count(),
                'transformed_fields' => collect($mappings)->where('status', 'transformed')->count(),
                'skipped_fields' => collect($mappings)->where('status', 'skipped')->count(),
            ],
            'data_quality' => $this->dataQuality(),
            'reference_data' => $this->referenceSummary(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function copy(?callable $onProgress = null): array
    {
        $sourceTable = $this->sourceTable(self::SourceTable);
        $totalRows = DB::table($sourceTable)->count();

        return DB::transaction(function () use ($onProgress, $sourceTable, $totalRows): array {
            $existingKaryawans = DB::table(self::TargetTable)
                ->get(['uuid', 'nip']);

            $existingByNip = $existingKaryawans
                ->keyBy(fn (object $karyawan): int => (int) $karyawan->nip)
                ->all();
            $deletedRows = DB::table(self::TargetTable)->delete();
            $referenceData = $this->synchronizeReferenceData();
            $applicationEmails = User::query()
                ->whereNotNull('email')
                ->pluck('email')
                ->mapWithKeys(fn (string $email): array => [Str::lower(trim($email)) => true])
                ->all();
            $seenEmails = [];
            $seenAccounts = [];
            $statistics = [
                'source_rows' => 0,
                'deleted_rows' => $deletedRows,
                'inserted' => 0,
                'updated' => 0,
                'user_rows_skipped' => 0,
                'emails_cleared' => 0,
                'accounts_cleared' => 0,
                'directorates_cleared' => 0,
            ];
            $processedRows = 0;

            $onProgress?->__invoke(
                $processedRows,
                $totalRows,
                $statistics,
                'Master data referensi selesai',
            );

            DB::table($sourceTable)
                ->orderBy(self::LegacySourceNipField)
                ->chunkById(self::ChunkSize, function ($sourceKaryawans) use (
                    &$processedRows,
                    &$statistics,
                    &$seenEmails,
                    &$seenAccounts,
                    $existingByNip,
                    $applicationEmails,
                    $referenceData,
                    $totalRows,
                    $onProgress,
                ): void {
                    $payload = [];

                    foreach ($sourceKaryawans as $sourceKaryawan) {
                        $source = (array) $sourceKaryawan;
                        $nip = (int) $source[self::LegacySourceNipField];
                        $existing = $existingByNip[$nip] ?? null;

                        $statistics['source_rows']++;

                        if ($this->shouldSkipSourceRowForApplicationUser(
                            email: $this->stringOrNull($source['email'] ?? null),
                            karyawanExists: $existing !== null,
                            applicationEmails: $applicationEmails,
                        )) {
                            $statistics['user_rows_skipped']++;

                            continue;
                        }

                        $row = $this->mapSourceRow($source, $referenceData['maps']);
                        $row['uuid'] = $existing?->uuid ?? (string) Str::uuid();

                        $row['email'] = $this->resolveUniqueValue(
                            value: $source['email'] ?? null,
                            nip: $nip,
                            owners: [],
                            seen: $seenEmails,
                        );
                        $account = $this->stringOrNull($source['nomor_rekening'] ?? null);
                        $row['nomor_rekening_hash'] = $this->resolveUniqueValue(
                            value: karyawan_blind_index($account),
                            nip: $nip,
                            owners: [],
                            seen: $seenAccounts,
                        );
                        $row['nomor_rekening'] = $row['nomor_rekening_hash'] === null ? null : $account;

                        if ($this->hasText($source['email'] ?? null) && $row['email'] === null) {
                            $statistics['emails_cleared']++;
                        }

                        if ($this->hasText($source['nomor_rekening'] ?? null) && $row['nomor_rekening_hash'] === null) {
                            $statistics['accounts_cleared']++;
                        }

                        if (($source['placement_id'] ?? null) !== null && $row['directorate_id'] === null) {
                            $statistics['directorates_cleared']++;
                        }

                        $statistics['inserted']++;
                        $payload[] = $this->encryptSensitiveFields($row);
                    }

                    $processedRows += count($sourceKaryawans);

                    if ($payload !== []) {
                        DB::table(self::TargetTable)->insert($payload);
                    }

                    $onProgress?->__invoke(
                        $processedRows,
                        $totalRows,
                        $statistics,
                        'Menyalin dan menyimpan data karyawan',
                    );
                }, self::LegacySourceNipField, self::LegacySourceNipField);

            return [
                'statistics' => $statistics,
                'reference_data' => $referenceData['summary'],
            ];
        }, attempts: 3);
    }

    /**
     * @return array{
     *     statistics: list<array<string, int|string>>,
     *     totals: array{deleted_rows: int, source_rows: int, created_rows: int, updated_rows: int, skipped_rows: int}
     * }
     */
    public function copyMasterData(): array
    {
        return DB::transaction(function (): array {
            $deletedRows = $this->clearTargetMasterData();
            $statistics = [
                $this->masterDataResult(
                    key: 'company',
                    label: 'Company',
                    sourceTable: 'companies',
                    targetTable: 'companies',
                    result: $this->synchronizeNamedReference(
                        sourceTable: 'companies',
                        sourceNameColumn: 'company_name',
                        targetTable: 'companies',
                    ),
                    deletedRows: $deletedRows['companies'] ?? 0,
                ),
                $this->masterDataResult(
                    key: 'directorate',
                    label: 'Directorate',
                    sourceTable: 'placements',
                    targetTable: 'directorates',
                    result: $this->synchronizeNamedReference(
                        sourceTable: 'placements',
                        sourceNameColumn: 'placement_name',
                        targetTable: 'directorates',
                    ),
                    deletedRows: $deletedRows['directorates'] ?? 0,
                ),
                $this->masterDataResult(
                    key: 'department',
                    label: 'Department',
                    sourceTable: 'departments',
                    targetTable: 'departments',
                    result: $this->synchronizeNamedReference(
                        sourceTable: 'departments',
                        sourceNameColumn: 'nama_department',
                        targetTable: 'departments',
                    ),
                    deletedRows: $deletedRows['departments'] ?? 0,
                ),
                $this->masterDataResult(
                    key: 'jabatan',
                    label: 'Jabatan',
                    sourceTable: 'jabatans',
                    targetTable: 'jabatans',
                    result: $this->synchronizeNamedReference(
                        sourceTable: 'jabatans',
                        sourceNameColumn: 'nama_jabatan',
                        targetTable: 'jabatans',
                    ),
                    deletedRows: $deletedRows['jabatans'] ?? 0,
                ),
                $this->masterDataResult(
                    key: 'job_grade',
                    label: 'Job Grade',
                    sourceTable: $this->jobGradeSourceLabel(),
                    targetTable: 'job_grades',
                    result: $this->synchronizeJobGrades(),
                    deletedRows: $deletedRows['job_grades'] ?? 0,
                ),
            ];

            return [
                'statistics' => $statistics,
                'totals' => [
                    'deleted_rows' => array_sum(array_column($statistics, 'deleted_rows')),
                    'source_rows' => array_sum(array_column($statistics, 'source_rows')),
                    'created_rows' => array_sum(array_column($statistics, 'created_rows')),
                    'updated_rows' => array_sum(array_column($statistics, 'updated_rows')),
                    'skipped_rows' => array_sum(array_column($statistics, 'skipped_rows')),
                ],
            ];
        }, attempts: 3);
    }

    /**
     * @param  list<array<string, mixed>>  $sourceColumns
     * @param  list<array<string, mixed>>  $targetColumns
     * @return list<array<string, mixed>>
     */
    public function buildFieldMappings(array $sourceColumns, array $targetColumns): array
    {
        $targets = collect($targetColumns)->keyBy('column_name');
        $coveredTargets = [];
        $mappings = [];

        foreach ($sourceColumns as $sourceColumn) {
            $sourceName = (string) $sourceColumn['column_name'];
            $override = self::FieldOverrides[$sourceName] ?? null;
            $targetName = $override['target'] ?? ($targets->has($sourceName) ? $sourceName : null);
            $status = $override['status'] ?? ($targetName === null ? 'skipped' : 'direct');
            $note = $override['note'] ?? ($targetName === null
                ? 'Tidak memiliki kolom padanan pada tabel target.'
                : 'Disalin langsung tanpa perubahan struktur.');

            if ($targetName !== null) {
                $coveredTargets[$targetName] = true;
            }

            $mappings[] = [
                'source_field' => $sourceName,
                'target_field' => $targetName,
                'source_type' => $sourceColumn['column_type'],
                'target_type' => $targetName !== null ? $targets->get($targetName)['column_type'] : null,
                'status' => $status,
                'note' => $note,
            ];
        }

        foreach ($targetColumns as $targetColumn) {
            $targetName = (string) $targetColumn['column_name'];

            if (isset($coveredTargets[$targetName])) {
                continue;
            }

            $mappings[] = [
                'source_field' => null,
                'target_field' => $targetName,
                'source_type' => null,
                'target_type' => $targetColumn['column_type'],
                'status' => 'target_only',
                'note' => 'Tidak tersedia di sumber. Nilainya disimpan null atau mengikuti default target.',
            ];
        }

        return $mappings;
    }

    /**
     * @param  array<string, mixed>  $sourceRow
     * @param  array<string, array<int|string, int>>  $referenceMaps
     * @return array<string, mixed>
     */
    public function mapSourceRow(array $sourceRow, array $referenceMaps): array
    {
        $row = [];

        foreach (self::DirectTargetFields as $field) {
            $row[$field] = $sourceRow[$field] ?? null;
        }

        $row['nip'] = $this->integerOrNull($sourceRow[self::LegacySourceNipField] ?? null);
        $row['status_karyawan'] = $row['status_karyawan'] ?: KaryawanStatus::Pkwt->value;
        $row['company_id'] = $this->mappedReference($referenceMaps['company'] ?? [], $sourceRow['company_id'] ?? null);
        $row['directorate_id'] = $this->mappedReference($referenceMaps['directorate'] ?? [], $sourceRow['placement_id'] ?? null);
        $row['department_id'] = $this->mappedReference($referenceMaps['department'] ?? [], $sourceRow['department_id'] ?? null);
        $row['jabatan_id'] = $this->mappedReference($referenceMaps['jabatan'] ?? [], $sourceRow['jabatan_id'] ?? null);
        $row['job_grade_id'] = $this->mappedReference($referenceMaps['job_grade'] ?? [], $sourceRow['level_jabatan'] ?? null);
        $row['etnis_id'] = $this->mappedReference(
            $referenceMaps['etnis'] ?? [],
            mb_strtolower($this->normalizeEtnisName($sourceRow['etnis'] ?? '')),
        );
        $row['outsource'] = (int) ($sourceRow['outsource'] ?? 1) === 0
            ? OutsourceType::NonOs->value
            : OutsourceType::Os->value;
        $row['potongan_jht'] = (bool) ($sourceRow['potongan_JHT'] ?? false);
        $row['potongan_jp'] = (bool) ($sourceRow['potongan_JP'] ?? false);
        $row['potongan_jkk'] = (bool) ($sourceRow['potongan_JKK'] ?? false);
        $row['potongan_jkm'] = (bool) ($sourceRow['potongan_JKM'] ?? false);
        $row['potongan_kesehatan'] = (bool) ($sourceRow['potongan_kesehatan'] ?? false);
        $row['id_file_karyawan'] = $this->stringOrNull($sourceRow['id_file_karyawan'] ?? null);
        $row['email'] = $this->stringOrNull($sourceRow['email'] ?? null);
        $row['nomor_rekening'] = $this->stringOrNull($sourceRow['nomor_rekening'] ?? null);
        $row['created_at'] = $row['created_at'] ?: now()->toDateTimeString();
        $row['updated_at'] = $row['updated_at'] ?: now()->toDateTimeString();

        foreach ([
            'gaji_pokok',
            'gaji_tetap',
            'gaji_overtime',
            'gaji_shift_malam_satpam',
            'bonus',
            'tunjangan_jabatan',
            'tunjangan_bahasa',
            'tunjangan_skill',
            'tunjangan_lembur_sabtu',
            'tunjangan_lama_kerja',
            'tunjangan_housing',
            'iuran_air',
            'denda',
            'iuran_locker',
            'gaji_bpjs',
            'tanggungan',
        ] as $field) {
            $row[$field] = $this->integerOrNull($row[$field]);
        }

        return $row;
    }

    /**
     * @param  array<string, bool>  $applicationEmails
     */
    public function shouldSkipSourceRowForApplicationUser(
        ?string $email,
        bool $karyawanExists,
        array $applicationEmails,
    ): bool {
        if ($email === null || $karyawanExists) {
            return false;
        }

        return isset($applicationEmails[Str::lower(trim($email))]);
    }

    public function sourceDatabase(): string
    {
        $database = trim((string) config('database.source_database'));

        if ($database === '') {
            throw new RuntimeException('DB_SOURCE_DATABASE belum dikonfigurasi.');
        }

        return $database;
    }

    private function sourceTable(string $table): string
    {
        return $this->sourceDatabase().'.'.$table;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columnMetadata(string $database, string $table): array
    {
        $columns = DB::select(
            <<<'SQL'
                SELECT
                    COLUMN_NAME AS column_name,
                    COLUMN_TYPE AS column_type,
                    IS_NULLABLE AS is_nullable,
                    COLUMN_DEFAULT AS column_default,
                    EXTRA AS extra,
                    COLUMN_KEY AS column_key,
                    ORDINAL_POSITION AS ordinal_position
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION
            SQL,
            [$database, $table],
        );

        return array_map(fn (object $column): array => (array) $column, $columns);
    }

    private function targetDatabase(): string
    {
        return (string) DB::connection()->getDatabaseName();
    }

    private function sourceTableExists(string $table): bool
    {
        return DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $this->sourceDatabase())
            ->where('TABLE_NAME', $table)
            ->exists();
    }

    private function sourceJobGradeCount(): int
    {
        if ($this->sourceTableExists('jobgrades')) {
            return DB::table($this->sourceTable('jobgrades'))->count();
        }

        return DB::table($this->sourceTable(self::SourceTable))
            ->whereNotNull('level_jabatan')
            ->where('level_jabatan', '!=', '')
            ->distinct()
            ->count('level_jabatan');
    }

    private function jobGradeSourceLabel(): string
    {
        return $this->sourceTableExists('jobgrades')
            ? 'jobgrades'
            : 'karyawans.level_jabatan';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dataQuality(): array
    {
        $sourceKaryawans = $this->sourceTable(self::SourceTable);
        $uniqueValues = (array) DB::table($sourceKaryawans)
            ->selectRaw("SUM(CASE WHEN email IS NULL OR TRIM(email) = '' THEN 1 ELSE 0 END) AS empty_email_rows")
            ->selectRaw("COUNT(*) - COUNT(DISTINCT NULLIF(LOWER(TRIM(email)), '')) AS duplicate_email_rows")
            ->selectRaw("SUM(CASE WHEN nomor_rekening IS NULL OR TRIM(nomor_rekening) = '' THEN 1 ELSE 0 END) AS empty_account_rows")
            ->selectRaw("COUNT(*) - COUNT(DISTINCT NULLIF(LOWER(TRIM(nomor_rekening)), '')) AS duplicate_account_rows")
            ->first();
        $fileIds = (array) DB::table($sourceKaryawans)
            ->selectRaw("SUM(CASE WHEN id_file_karyawan IS NULL OR TRIM(id_file_karyawan) = '' THEN 1 ELSE 0 END) AS empty_file_id_rows")
            ->selectRaw("SUM(CASE WHEN id_file_karyawan IS NOT NULL AND TRIM(id_file_karyawan) <> '' AND id_file_karyawan NOT REGEXP '^[0-9]+$' THEN 1 ELSE 0 END) AS non_numeric_file_id_rows")
            ->first();
        $referenceProblems = (array) DB::table($sourceKaryawans, 'k')
            ->leftJoin($this->sourceTable('companies').' as c', 'c.id', '=', 'k.company_id')
            ->leftJoin($this->sourceTable('placements').' as p', 'p.id', '=', 'k.placement_id')
            ->leftJoin($this->sourceTable('departments').' as d', 'd.id', '=', 'k.department_id')
            ->leftJoin($this->sourceTable('jabatans').' as j', 'j.id', '=', 'k.jabatan_id')
            ->selectRaw('SUM(CASE WHEN k.company_id IS NOT NULL AND c.id IS NULL THEN 1 ELSE 0 END) AS missing_company_rows')
            ->selectRaw('SUM(CASE WHEN k.placement_id IS NOT NULL AND p.id IS NULL THEN 1 ELSE 0 END) AS missing_directorate_rows')
            ->selectRaw('SUM(CASE WHEN k.department_id IS NOT NULL AND d.id IS NULL THEN 1 ELSE 0 END) AS missing_department_rows')
            ->selectRaw('SUM(CASE WHEN k.jabatan_id IS NOT NULL AND j.id IS NULL THEN 1 ELSE 0 END) AS missing_jabatan_rows')
            ->first();

        $metrics = array_map(
            fn (mixed $value): int => (int) $value,
            [...$uniqueValues, ...$fileIds, ...$referenceProblems],
        );

        return [
            [
                'key' => 'duplicate_email_rows',
                'label' => 'Email duplikat',
                'count' => $metrics['duplicate_email_rows'],
                'severity' => 'warning',
                'description' => 'Email duplikat atau bentrok dengan data target akan dikosongkan agar unique constraint tetap valid.',
            ],
            [
                'key' => 'duplicate_account_rows',
                'label' => 'Nomor rekening duplikat',
                'count' => $metrics['duplicate_account_rows'],
                'severity' => 'warning',
                'description' => 'Nomor rekening duplikat atau kosong akan dikosongkan. Nilai pertama yang tidak bentrok tetap disalin.',
            ],
            [
                'key' => 'non_numeric_file_id_rows',
                'label' => 'ID file non-numerik',
                'count' => $metrics['non_numeric_file_id_rows'],
                'severity' => 'info',
                'description' => 'Nilai non-numerik terdeteksi dan tetap disalin karena kolom target menggunakan varchar.',
            ],
            [
                'key' => 'missing_reference_rows',
                'label' => 'Relasi master tidak ditemukan',
                'count' => $metrics['missing_company_rows'] + $metrics['missing_directorate_rows'] + $metrics['missing_department_rows'] + $metrics['missing_jabatan_rows'],
                'severity' => 'info',
                'description' => 'Master company, directorate, department, dan jabatan akan disinkronkan berdasarkan nama sebelum karyawan disalin.',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function referenceSummary(): array
    {
        $references = [
            ['key' => 'company', 'label' => 'Company', 'source_table' => 'companies', 'target_table' => 'companies'],
            ['key' => 'directorate', 'label' => 'Directorate', 'source_table' => 'placements', 'target_table' => 'directorates'],
            ['key' => 'department', 'label' => 'Department', 'source_table' => 'departments', 'target_table' => 'departments'],
            ['key' => 'jabatan', 'label' => 'Jabatan', 'source_table' => 'jabatans', 'target_table' => 'jabatans'],
            ['key' => 'job_grade', 'label' => 'Job Grade', 'source_table' => 'jobgrades', 'target_table' => 'job_grades'],
            ['key' => 'etnis', 'label' => 'Etnis', 'source_table' => 'karyawans.etnis', 'target_table' => 'etnis'],
        ];

        return array_map(function (array $reference): array {
            $sourceCount = match ($reference['key']) {
                'etnis' => DB::table($this->sourceTable(self::SourceTable))
                    ->whereNotNull('etnis')
                    ->where('etnis', '!=', '')
                    ->distinct()
                    ->count('etnis'),
                'job_grade' => $this->sourceJobGradeCount(),
                default => DB::table($this->sourceTable($reference['source_table']))->count(),
            };
            $sourceTable = $reference['key'] === 'job_grade'
                ? $this->jobGradeSourceLabel()
                : $reference['source_table'];

            return [
                ...$reference,
                'source_table' => $this->sourceTable($sourceTable),
                'source_count' => $sourceCount,
                'target_count' => DB::table($reference['target_table'])->count(),
            ];
        }, $references);
    }

    /**
     * @return array{maps: array<string, array<int|string, int>>, summary: list<array<string, mixed>>}
     */
    private function synchronizeReferenceData(): array
    {
        $maps = [
            'company' => $this->synchronizeNamedReference(
                sourceTable: 'companies',
                sourceNameColumn: 'company_name',
                targetTable: 'companies',
            ),
            'directorate' => $this->synchronizeNamedReference(
                sourceTable: 'placements',
                sourceNameColumn: 'placement_name',
                targetTable: 'directorates',
            ),
            'department' => $this->synchronizeNamedReference(
                sourceTable: 'departments',
                sourceNameColumn: 'nama_department',
                targetTable: 'departments',
            ),
            'jabatan' => $this->synchronizeNamedReference(
                sourceTable: 'jabatans',
                sourceNameColumn: 'nama_jabatan',
                targetTable: 'jabatans',
            ),
            'job_grade' => $this->synchronizeJobGrades(),
            'etnis' => $this->synchronizeEtnis(),
        ];

        $summary = [];

        foreach ($maps as $key => $map) {
            $summary[] = [
                'key' => $key,
                'source_count' => $map['source_count'],
                'mapped_count' => count($map['maps']),
                'created_count' => $map['created_count'],
            ];
        }

        return [
            'maps' => collect($maps)->mapWithKeys(
                fn (array $map, string $key): array => [$key => $map['maps']],
            )->all(),
            'summary' => $summary,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function clearTargetMasterData(): array
    {
        $deletedRows = [];

        foreach (self::TargetMasterTables as $table) {
            $deletedRows[$table] = DB::table($table)->delete();
        }

        return $deletedRows;
    }

    /**
     * @param  array{source_count: int, created_count: int, updated_count: int, skipped_count: int, maps: array<int|string, int>}  $result
     * @return array<string, int|string>
     */
    private function masterDataResult(
        string $key,
        string $label,
        string $sourceTable,
        string $targetTable,
        array $result,
        int $deletedRows,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'source_table' => $this->sourceTable($sourceTable),
            'target_table' => $targetTable,
            'deleted_rows' => $deletedRows,
            'source_rows' => $result['source_count'],
            'created_rows' => $result['created_count'],
            'updated_rows' => $result['updated_count'],
            'skipped_rows' => $result['skipped_count'],
            'target_rows' => DB::table($targetTable)->count(),
        ];
    }

    /**
     * @return array{source_count: int, created_count: int, updated_count: int, skipped_count: int, maps: array<int|string, int>}
     */
    private function synchronizeNamedReference(
        string $sourceTable,
        string $sourceNameColumn,
        string $targetTable,
    ): array {
        $sourceRows = DB::table($this->sourceTable($sourceTable))
            ->select('id', "{$sourceNameColumn} as name", 'created_at', 'updated_at')
            ->orderBy('id')
            ->get();
        $existingNames = $this->targetIdsByName($targetTable);
        $validRows = $sourceRows
            ->map(function (object $sourceRow): object {
                $sourceRow->name = trim((string) $sourceRow->name);

                return $sourceRow;
            })
            ->filter(fn (object $sourceRow): bool => $sourceRow->name !== '')
            ->unique('name')
            ->values();
        $createdCount = $validRows
            ->reject(fn (object $sourceRow): bool => isset($existingNames[$sourceRow->name]))
            ->count();
        $updatedCount = $validRows->count() - $createdCount;
        $payload = [];

        foreach ($validRows as $sourceRow) {
            $payload[] = [
                'name' => $sourceRow->name,
                'created_at' => $sourceRow->created_at ?: now()->toDateTimeString(),
                'updated_at' => $sourceRow->updated_at ?: now()->toDateTimeString(),
            ];
        }

        if ($payload !== []) {
            DB::table($targetTable)->upsert($payload, ['name'], ['updated_at']);
        }

        $targetIdsByName = $this->targetIdsByName($targetTable);
        $maps = [];

        foreach ($sourceRows as $sourceRow) {
            $name = trim((string) $sourceRow->name);

            if ($name !== '' && isset($targetIdsByName[$name])) {
                $maps[$sourceRow->id] = (int) $targetIdsByName[$name];
            }
        }

        return [
            'source_count' => $sourceRows->count(),
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'skipped_count' => $sourceRows->count() - $validRows->count(),
            'maps' => $maps,
        ];
    }

    /**
     * @return array{source_count: int, created_count: int, updated_count: int, skipped_count: int, maps: array<int|string, int>}
     */
    private function synchronizeJobGrades(): array
    {
        if (! $this->sourceTableExists('jobgrades')) {
            return $this->synchronizeJobGradesFromLevelValues();
        }

        $sourceRows = DB::table($this->sourceTable('jobgrades'))
            ->select('id', 'grade', 'grade_name', 'created_at', 'updated_at')
            ->orderBy('id')
            ->get();
        $existingGrades = DB::table('job_grades')->pluck('id', 'grade')->all();
        $validRows = $sourceRows
            ->filter(fn (object $sourceRow): bool => filled($sourceRow->grade))
            ->unique('grade')
            ->values();
        $createdCount = $validRows
            ->reject(fn (object $sourceRow): bool => isset($existingGrades[$sourceRow->grade]))
            ->count();
        $updatedCount = $validRows->count() - $createdCount;
        $payload = [];

        foreach ($validRows as $sourceRow) {
            $payload[] = [
                'grade' => $sourceRow->grade,
                'grade_name' => $sourceRow->grade_name,
                'created_at' => $sourceRow->created_at ?: now()->toDateTimeString(),
                'updated_at' => $sourceRow->updated_at ?: now()->toDateTimeString(),
            ];
        }

        if ($payload !== []) {
            DB::table('job_grades')->upsert($payload, ['grade'], ['grade_name', 'updated_at']);
        }

        $targetIdsByGrade = DB::table('job_grades')->pluck('id', 'grade')->all();
        $maps = [];

        foreach ($sourceRows as $sourceRow) {
            if (isset($targetIdsByGrade[$sourceRow->grade])) {
                $maps[$sourceRow->id] = (int) $targetIdsByGrade[$sourceRow->grade];
            }
        }

        return [
            'source_count' => $sourceRows->count(),
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'skipped_count' => $sourceRows->count() - $validRows->count(),
            'maps' => $maps,
        ];
    }

    /**
     * @return array{source_count: int, created_count: int, updated_count: int, skipped_count: int, maps: array<int|string, int>}
     */
    private function synchronizeJobGradesFromLevelValues(): array
    {
        $sourceValues = DB::table($this->sourceTable(self::SourceTable))
            ->whereNotNull('level_jabatan')
            ->where('level_jabatan', '!=', '')
            ->distinct()
            ->orderBy('level_jabatan')
            ->pluck('level_jabatan')
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();
        $existingGrades = DB::table('job_grades')->pluck('id', 'grade')->all();
        $createdCount = $sourceValues
            ->reject(fn (string $grade): bool => isset($existingGrades[$grade]))
            ->count();
        $updatedCount = $sourceValues->count() - $createdCount;
        $payload = $sourceValues
            ->map(fn (string $grade): array => [
                'grade' => $grade,
                'grade_name' => self::JobGradeNames[$grade] ?? $grade,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ])
            ->all();

        if ($payload !== []) {
            DB::table('job_grades')->upsert($payload, ['grade'], ['grade_name', 'updated_at']);
        }

        $targetIdsByGrade = DB::table('job_grades')->pluck('id', 'grade')->all();
        $maps = $sourceValues
            ->filter(fn (string $grade): bool => isset($targetIdsByGrade[$grade]))
            ->mapWithKeys(fn (string $grade): array => [$grade => (int) $targetIdsByGrade[$grade]])
            ->all();

        return [
            'source_count' => $sourceValues->count(),
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'skipped_count' => 0,
            'maps' => $maps,
        ];
    }

    /**
     * @return array{source_count: int, created_count: int, maps: array<string, int>}
     */
    private function synchronizeEtnis(): array
    {
        $sourceNames = DB::table($this->sourceTable(self::SourceTable))
            ->whereNotNull('etnis')
            ->where('etnis', '!=', '')
            ->distinct()
            ->orderBy('etnis')
            ->pluck('etnis')
            ->map(fn (string $name): string => $this->normalizeEtnisName($name))
            ->filter()
            ->unique()
            ->values();
        $existingNames = $this->targetIdsByName('etnis');
        $createdCount = $sourceNames->reject(fn (string $name): bool => isset($existingNames[$name]))->count();

        if ($sourceNames->isNotEmpty()) {
            DB::table('etnis')->upsert(
                $sourceNames->map(fn (string $name): array => [
                    'name' => $name,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ])->all(),
                ['name'],
                ['updated_at'],
            );
        }

        $targetIdsByName = $this->targetIdsByName('etnis');
        $maps = $sourceNames
            ->filter(fn (string $name): bool => isset($targetIdsByName[$name]))
            ->mapWithKeys(fn (string $name): array => [mb_strtolower($name) => $targetIdsByName[$name]])
            ->all();

        return [
            'source_count' => $sourceNames->count(),
            'created_count' => $createdCount,
            'maps' => $maps,
        ];
    }

    /**
     * @param  array<int|string, int>  $map
     */
    private function mappedReference(array $map, mixed $sourceId): ?int
    {
        if ($sourceId === null || $sourceId === '') {
            return null;
        }

        return $map[$sourceId] ?? null;
    }

    /**
     * @return array<string, int>
     */
    private function targetIdsByName(string $targetTable): array
    {
        return DB::table($targetTable)
            ->whereNotNull('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (object $row): array => [trim((string) $row->name) => (int) $row->id])
            ->all();
    }

    /**
     * @param  array<string, int|string>  $owners
     * @param  array<string, bool>  $seen
     */
    private function resolveUniqueValue(
        mixed $value,
        int $nip,
        array $owners,
        array &$seen,
    ): ?string {
        $normalizedValue = $this->stringOrNull($value);

        if ($normalizedValue === null) {
            return null;
        }

        $key = Str::lower($normalizedValue);
        $owner = $owners[$key] ?? null;

        if (isset($seen[$key]) || ($owner !== null && (int) $owner !== $nip)) {
            return null;
        }

        $seen[$key] = true;

        return $normalizedValue;
    }

    /**
     * @param  Collection<int, object>  $karyawans
     * @return array<string, int|string>
     */
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function encryptSensitiveFields(array $row): array
    {
        foreach (['no_identitas', 'nomor_rekening', 'no_npwp'] as $field) {
            $value = $this->stringOrNull($row[$field] ?? null);

            $row[$field] = $value === null ? null : Crypt::encryptString($value);
        }

        return $row;
    }

    private function integerOrNull(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $integer = filter_var(trim((string) $value), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);

        return $integer === false ? null : $integer;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizeEtnisName(mixed $name): string
    {
        $normalized = trim((string) $name);

        return strcasecmp($normalized, 'China') === 0 ? 'TKA' : $normalized;
    }

    private function hasText(mixed $value): bool
    {
        return $this->stringOrNull($value) !== null;
    }
}
