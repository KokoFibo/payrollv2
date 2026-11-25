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
            $table->foreignId('company_id')
                ->nullable()
                ->after('name')
                ->constrained('companies')
                ->nullOnDelete();
            $table->foreignId('directorate_id')
                ->nullable()
                ->after('company_id')
                ->constrained('directorates')
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->after('directorate_id')
                ->constrained('departments')
                ->nullOnDelete();

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
        Schema::table('special_work_schedules', function (Blueprint $table) {
            $table->dropIndex('special_work_schedules_scope_dates_index');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('directorate_id');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
