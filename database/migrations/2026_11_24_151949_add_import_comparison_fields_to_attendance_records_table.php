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
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('attendance_import_id')
                ->nullable()
                ->after('recorded_by')
                ->constrained('attendance_imports')
                ->nullOnDelete();
            $table->string('shift', 16)->nullable()->after('status');
            $table->string('schedule_profile', 32)->nullable()->after('shift');
            $table->time('expected_check_in')->nullable()->after('check_out');
            $table->time('expected_check_out')->nullable()->after('expected_check_in');
            $table->unsignedTinyInteger('check_out_day_offset')->default(0)->after('expected_check_out');
            $table->unsignedSmallInteger('raw_punch_count')->default(0)->after('check_out_day_offset');
            $table->unsignedSmallInteger('effective_punch_count')->default(0)->after('raw_punch_count');
            $table->unsignedSmallInteger('duplicate_punch_count')->default(0)->after('effective_punch_count');
            $table->unsignedInteger('late_minutes')->default(0)->after('duplicate_punch_count');
            $table->unsignedSmallInteger('late_penalty_hours')->nullable()->after('late_minutes');
            $table->unsignedInteger('early_leave_minutes')->default(0)->after('late_penalty_hours');
            $table->boolean('has_missing_punch')->default(false)->after('early_leave_minutes');
            $table->boolean('has_duplicate_punch')->default(false)->after('has_missing_punch');
            $table->boolean('requires_review')->default(false)->after('has_duplicate_punch');
            $table->text('comparison_notes')->nullable()->after('requires_review');

            $table->index(['attendance_date', 'has_missing_punch'], 'attendance_records_date_missing_punch_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('attendance_records_date_missing_punch_index');
            $table->dropConstrainedForeignId('attendance_import_id');
            $table->dropColumn([
                'shift',
                'schedule_profile',
                'expected_check_in',
                'expected_check_out',
                'check_out_day_offset',
                'raw_punch_count',
                'effective_punch_count',
                'duplicate_punch_count',
                'late_minutes',
                'late_penalty_hours',
                'early_leave_minutes',
                'has_missing_punch',
                'has_duplicate_punch',
                'requires_review',
                'comparison_notes',
            ]);
        });
    }
};
