<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
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
            $table->decimal('night_shift_allowance', 6, 1)
                ->default(0)
                ->after('holiday_overtime_hours');
        });

        DB::table('attendance_records')
            ->where('shift', 'night')
            ->where(function (Builder $query): void {
                $query->where('worked_hours', '>', 0)
                    ->orWhere('holiday_worked_hours', '>', 0);
            })
            ->update(['night_shift_allowance' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('night_shift_allowance');
        });
    }
};
