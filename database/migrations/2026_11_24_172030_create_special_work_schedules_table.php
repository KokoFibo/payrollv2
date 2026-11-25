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
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('schedule_profile', 32)->nullable();
            $table->string('day_type', 32)->nullable();
            $table->string('shift', 16)->nullable();
            $table->time('check_in');
            $table->time('check_out');
            $table->unsignedTinyInteger('check_out_day_offset')->default(0);
            $table->unsignedTinyInteger('expected_punch_count')->default(4);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
            $table->index(['schedule_profile', 'day_type', 'shift'], 'special_work_schedules_scope_index');
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
