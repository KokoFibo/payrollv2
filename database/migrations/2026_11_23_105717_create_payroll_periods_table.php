<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date');
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedBigInteger('total_gross')->default(0);
            $table->unsignedBigInteger('total_deductions')->default(0);
            $table->unsignedBigInteger('total_net')->default(0);
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
