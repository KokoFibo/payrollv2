<?php

namespace App\Models;

use App\Enums\KaryawanStatus;
use App\Enums\OutsourceType;
use App\Enums\PayrollMethod;
use Database\Factories\KaryawanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'nip',
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
    'etnis_id',
    'jenis_identitas',
    'no_identitas',
    'alamat_identitas',
    'alamat_tinggal_sekarang',
    'status_karyawan',
    'tanggal_bergabung',
    'tanggal_resigned',
    'tanggal_blacklist',
    'company_id',
    'directorate_id',
    'department_id',
    'jabatan_id',
    'job_grade_id',
    'nama_bank',
    'nomor_rekening',
    'outsource',
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
    'potongan_jht',
    'potongan_jp',
    'potongan_jkk',
    'potongan_jkm',
    'potongan_kesehatan',
    'no_npwp',
    'ptkp',
    'nama_contact_darurat',
    'nama_contact_darurat_2',
    'contact_darurat_1',
    'contact_darurat_2',
    'hubungan_1',
    'hubungan_2',
    'tanggungan',
    'id_file_karyawan',
    'tanggal_update',
    'pendidikan',
    'jurusan',
    'nama_kampus',
])]
class Karyawan extends Model
{
    /** @use HasFactory<KaryawanFactory> */
    use HasFactory;

    protected $attributes = [
        'outsource' => OutsourceType::Os->value,
    ];

    protected $hidden = [
        'nomor_rekening_hash',
    ];

    protected function casts(): array
    {
        return [
            'nip' => 'integer',
            'no_identitas' => 'encrypted',
            'nomor_rekening' => 'encrypted',
            'no_npwp' => 'encrypted',
            'tanggal_lahir' => 'date',
            'tanggal_bergabung' => 'date',
            'tanggal_resigned' => 'date',
            'tanggal_blacklist' => 'date',
            'tanggal_update' => 'date',
            'status_karyawan' => KaryawanStatus::class,
            'outsource' => OutsourceType::class,
            'metode_penggajian' => PayrollMethod::class,
            'gaji_pokok' => 'integer',
            'gaji_tetap' => 'integer',
            'gaji_overtime' => 'integer',
            'gaji_shift_malam_satpam' => 'integer',
            'bonus' => 'integer',
            'tunjangan_jabatan' => 'integer',
            'tunjangan_bahasa' => 'integer',
            'tunjangan_skill' => 'integer',
            'tunjangan_lembur_sabtu' => 'integer',
            'tunjangan_lama_kerja' => 'integer',
            'tunjangan_housing' => 'integer',
            'iuran_air' => 'integer',
            'denda' => 'integer',
            'iuran_locker' => 'integer',
            'gaji_bpjs' => 'integer',
            'potongan_jht' => 'boolean',
            'potongan_jp' => 'boolean',
            'potongan_jkk' => 'boolean',
            'potongan_jkm' => 'boolean',
            'potongan_kesehatan' => 'boolean',
            'tanggungan' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Karyawan $karyawan): void {
            if ($karyawan->uuid === null) {
                $karyawan->uuid = (string) Str::uuid();
            }

            $karyawan->nomor_rekening_hash = karyawan_blind_index($karyawan->nomor_rekening);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function etnis(): BelongsTo
    {
        return $this->belongsTo(Etnis::class);
    }

    public function jobGrade(): BelongsTo
    {
        return $this->belongsTo(JobGrade::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function lamaBekerja(): string
    {
        if ($this->tanggal_bergabung === null) {
            return '-';
        }

        $tanggalSelesai = $this->tanggal_resigned
            ?? $this->tanggal_blacklist
            ?? now();

        if ($tanggalSelesai->lt($this->tanggal_bergabung)) {
            return '0 hari';
        }

        $durasi = $this->tanggal_bergabung->diff($tanggalSelesai);

        if ($durasi->y > 0) {
            return $durasi->m > 0
                ? "{$durasi->y} th {$durasi->m} bln"
                : "{$durasi->y} th";
        }

        if ($durasi->m > 0) {
            return "{$durasi->m} bln {$durasi->d} hari";
        }

        return "{$durasi->d} hari";
    }
}
