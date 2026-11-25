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
        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('karyawan_id')->constrained('karyawans')->cascadeOnDelete();
            $table->unsignedSmallInteger('punch_sequence');
            $table->dateTime('punched_at');
            $table->time('punch_time');
            $table->boolean('is_next_day')->default(false);
            $table->string('raw_value', 64);
            $table->timestamps();

            $table->unique(
                ['attendance_import_id', 'attendance_record_id', 'punch_sequence'],
                'attendance_punches_import_record_sequence_unique',
            );
            $table->index(['attendance_record_id', 'punch_sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
    }
};
