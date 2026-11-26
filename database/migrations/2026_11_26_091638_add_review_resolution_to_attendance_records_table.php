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
            $table->timestamp('review_resolved_at')->nullable()->after('comparison_notes');
            $table->foreignId('review_resolved_by')
                ->nullable()
                ->after('review_resolved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('review_resolution_note', 500)->nullable()->after('review_resolved_by');

            $table->index(
                ['karyawan_id', 'attendance_date', 'requires_review'],
                'attendance_records_monthly_review_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('attendance_records_monthly_review_index');
            $table->dropConstrainedForeignId('review_resolved_by');
            $table->dropColumn([
                'review_resolved_at',
                'review_resolution_note',
            ]);
        });
    }
};
