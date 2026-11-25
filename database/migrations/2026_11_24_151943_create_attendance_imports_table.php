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
        Schema::create('attendance_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->unsignedInteger('file_size')->default(0);
            $table->date('attendance_date');
            $table->unsignedInteger('source_employee_count')->default(0);
            $table->unsignedInteger('processed_employee_count')->default(0);
            $table->unsignedInteger('skipped_without_punch_count')->default(0);
            $table->unsignedInteger('unmatched_employee_count')->default(0);
            $table->unsignedInteger('raw_punch_count')->default(0);
            $table->unsignedInteger('duplicate_punch_count')->default(0);
            $table->unsignedInteger('missing_punch_count')->default(0);
            $table->unsignedInteger('late_count')->default(0);
            $table->unsignedInteger('early_leave_count')->default(0);
            $table->json('unmatched_user_ids')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->index(['attendance_date', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_imports');
    }
};
