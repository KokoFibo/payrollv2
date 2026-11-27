<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('karyawans')) {
            return;
        }

        Schema::create('karyawans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('nip')->unique();
            $table->string('nama');
            $table->string('email')->nullable()->unique();
            $table->string('hp')->nullable();
            $table->string('telepon')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('gender')->nullable();
            $table->string('status_pernikahan')->nullable();
            $table->string('golongan_darah')->nullable();
            $table->string('agama')->nullable();
            $table->foreignId('etnis_id')->nullable()->constrained('etnis')->nullOnDelete();
            $table->string('jenis_identitas')->nullable();
            $table->text('no_identitas')->nullable();
            $table->string('alamat_identitas')->nullable();
            $table->string('alamat_tinggal_sekarang')->nullable();
            $table->string('status_karyawan')->index();
            $table->date('tanggal_bergabung')->nullable();
            $table->date('tanggal_resigned')->nullable();
            $table->date('tanggal_blacklist')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('directorate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('jabatan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_grade_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nama_bank')->nullable();
            $table->text('nomor_rekening')->nullable();
            $table->string('nomor_rekening_hash', 64)->nullable()->unique();
            $table->string('outsource')->default('OS');
            $table->string('metode_penggajian')->nullable();
            $table->unsignedBigInteger('gaji_pokok')->nullable();
            $table->unsignedBigInteger('gaji_tetap')->nullable();
            $table->unsignedBigInteger('gaji_overtime')->nullable();
            $table->unsignedBigInteger('gaji_shift_malam_satpam')->nullable();
            $table->unsignedBigInteger('bonus')->nullable();
            $table->unsignedBigInteger('tunjangan_jabatan')->nullable();
            $table->unsignedBigInteger('tunjangan_bahasa')->nullable();
            $table->unsignedBigInteger('tunjangan_skill')->nullable();
            $table->unsignedBigInteger('tunjangan_lembur_sabtu')->nullable();
            $table->unsignedBigInteger('tunjangan_lama_kerja')->nullable();
            $table->unsignedBigInteger('tunjangan_housing')->nullable();
            $table->unsignedBigInteger('iuran_air')->nullable();
            $table->unsignedBigInteger('denda')->nullable();
            $table->unsignedBigInteger('iuran_locker')->nullable();
            $table->unsignedBigInteger('gaji_bpjs')->nullable();
            $table->boolean('potongan_jht')->default(false);
            $table->boolean('potongan_jp')->default(false);
            $table->boolean('potongan_jkk')->default(false);
            $table->boolean('potongan_jkm')->default(false);
            $table->boolean('potongan_kesehatan')->default(false);
            $table->text('no_npwp')->nullable();
            $table->string('ptkp')->nullable();
            $table->string('nama_contact_darurat')->nullable();
            $table->string('nama_contact_darurat_2')->nullable();
            $table->string('contact_darurat_1')->nullable();
            $table->string('contact_darurat_2')->nullable();
            $table->string('hubungan_1')->nullable();
            $table->string('hubungan_2')->nullable();
            $table->unsignedInteger('tanggungan')->nullable();
            $table->string('id_file_karyawan')->nullable();
            $table->date('tanggal_update')->nullable()->index();
            $table->string('pendidikan')->nullable();
            $table->string('jurusan')->nullable();
            $table->string('nama_kampus')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // This duplicate migration must never drop the canonical table.
    }
};
