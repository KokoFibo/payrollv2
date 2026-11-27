<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('karyawans')
            ->whereRaw('LOWER(TRIM(metode_penggajian)) IN (?, ?)', ['perjam', 'per jam'])
            ->update(['metode_penggajian' => 'hourly']);

        DB::table('karyawans')
            ->whereRaw('LOWER(TRIM(metode_penggajian)) IN (?, ?, ?)', ['perbulan', 'per bulan', 'bulanan'])
            ->update(['metode_penggajian' => 'monthly']);
    }

    public function down(): void
    {
        DB::table('karyawans')
            ->where('metode_penggajian', 'hourly')
            ->update(['metode_penggajian' => 'Perjam']);

        DB::table('karyawans')
            ->where('metode_penggajian', 'monthly')
            ->update(['metode_penggajian' => 'Perbulan']);
    }
};
