<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawans')->cascadeOnDelete();
            $table->foreignId('attendance_import_id')->nullable()->constrained('attendance_imports')->nullOnDelete();
            $table->foreignId('calendar_override_id')->nullable()->constrained('calendar_overrides')->nullOnDelete();
            $table->date('attendance_date');
            $table->string('status', 32);
            $table->string('shift', 16)->nullable();
            $table->string('schedule_profile', 32)->nullable();
            $table->boolean('is_holiday_pay')->default(false);
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->unsignedTinyInteger('check_out_day_offset')->default(0);
            $table->unsignedTinyInteger('first_out_day_offset')->default(0);
            $table->time('expected_check_in')->nullable();
            $table->time('expected_check_out')->nullable();
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedSmallInteger('raw_punch_count')->default(0);
            $table->unsignedSmallInteger('effective_punch_count')->default(0);
            $table->unsignedSmallInteger('duplicate_punch_count')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('late_penalty_hours')->nullable();
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->boolean('has_missing_punch')->default(false);
            $table->boolean('has_duplicate_punch')->default(false);
            $table->boolean('requires_review')->default(false);
            $table->text('comparison_notes')->nullable();
            $table->timestamp('review_resolved_at')->nullable();
            $table->foreignId('review_resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_resolution_note', 500)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['karyawan_id', 'attendance_date']);
            $table->index(['attendance_date', 'status']);
            $table->index(['attendance_date', 'has_missing_punch'], 'attendance_records_date_missing_punch_index');
            $table->index(
                ['karyawan_id', 'attendance_date', 'requires_review'],
                'attendance_records_monthly_review_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
