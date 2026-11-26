<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admin_visibility_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('attendance_tolerance_minutes')
                ->default(3)
                ->after('admin_salary_limit');
        });

        DB::table('settings')
            ->where('key', 'attendance.late_tolerance_minutes')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'attendance.late_tolerance_minutes'],
            [
                'group' => 'attendance',
                'label' => 'Toleransi Keterlambatan',
                'value' => '3',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        Schema::table('admin_visibility_settings', function (Blueprint $table) {
            $table->dropColumn('attendance_tolerance_minutes');
        });
    }
};
