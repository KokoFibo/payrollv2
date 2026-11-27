<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->renameColumn('alamat_tinggal', 'alamat_tinggal_sekarang');
        $this->renameColumn('kontak_darurat', 'nama_contact_darurat');
        $this->renameColumn('kontak_darurat2', 'nama_contact_darurat_2');
        $this->renameColumn('hp1', 'contact_darurat_1');
        $this->renameColumn('hp2', 'contact_darurat_2');
        $this->renameColumn('hubungan1', 'hubungan_1');
        $this->renameColumn('hubungan2', 'hubungan_2');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->renameColumn('alamat_tinggal_sekarang', 'alamat_tinggal');
        $this->renameColumn('nama_contact_darurat', 'kontak_darurat');
        $this->renameColumn('nama_contact_darurat_2', 'kontak_darurat2');
        $this->renameColumn('contact_darurat_1', 'hp1');
        $this->renameColumn('contact_darurat_2', 'hp2');
        $this->renameColumn('hubungan_1', 'hubungan1');
        $this->renameColumn('hubungan_2', 'hubungan2');
    }

    private function renameColumn(string $from, string $to): void
    {
        if (! Schema::hasColumn('karyawans', $from) || Schema::hasColumn('karyawans', $to)) {
            return;
        }

        Schema::table('karyawans', function (Blueprint $table) use ($from, $to): void {
            $table->renameColumn($from, $to);
        });
    }
};
