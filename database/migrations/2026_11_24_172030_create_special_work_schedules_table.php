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
        Schema::create('special_work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('directorate_id')->nullable()->constrained('directorates')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('schedule_profile', 32)->nullable();
            $table->string('day_type', 32)->nullable();
            $table->string('shift', 16)->nullable();
            $table->time('check_in');
            $table->time('check_out');
            $table->unsignedTinyInteger('check_out_day_offset')->default(0);
            $table->decimal('standard_work_hours', 4, 1)->default(8);
            $table->unsignedTinyInteger('expected_punch_count')->default(4);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
            $table->index(['schedule_profile', 'day_type', 'shift'], 'special_work_schedules_scope_index');
            $table->index(
                ['company_id', 'directorate_id', 'department_id', 'starts_on', 'ends_on'],
                'special_work_schedules_scope_dates_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_work_schedules');
    }
};
