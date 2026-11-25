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
        Schema::create('calendar_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();
            $table->foreignId('directorate_id')
                ->nullable()
                ->constrained('directorates')
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();
            $table->date('calendar_date');
            $table->string('name', 150)->nullable();
            $table->boolean('is_holiday_pay')->default(false);
            $table->string('schedule_day_type', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'calendar_date']);
            $table->index(['company_id', 'directorate_id', 'department_id', 'calendar_date'], 'calendar_overrides_scope_date_index');
            $table->index('calendar_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_overrides');
    }
};
