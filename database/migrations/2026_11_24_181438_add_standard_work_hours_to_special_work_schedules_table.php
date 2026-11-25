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
        Schema::table('special_work_schedules', function (Blueprint $table) {
            $table->decimal('standard_work_hours', 4, 1)
                ->default(8)
                ->after('check_out_day_offset');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('special_work_schedules', function (Blueprint $table) {
            $table->dropColumn('standard_work_hours');
        });
    }
};
