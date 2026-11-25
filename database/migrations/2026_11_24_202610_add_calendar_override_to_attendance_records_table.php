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
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('calendar_override_id')
                ->nullable()
                ->after('attendance_import_id')
                ->constrained('calendar_overrides')
                ->nullOnDelete();
            $table->boolean('is_holiday_pay')
                ->default(false)
                ->after('schedule_profile');
        });

        DB::table('attendance_records')
            ->where(function ($query): void {
                $query->where('holiday_worked_hours', '>', 0)
                    ->orWhere('holiday_overtime_hours', '>', 0);
            })
            ->update(['is_holiday_pay' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('calendar_override_id');
            $table->dropColumn('is_holiday_pay');
        });
    }
};
