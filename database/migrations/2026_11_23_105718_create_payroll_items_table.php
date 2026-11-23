<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('karyawan_id')->constrained('karyawans')->cascadeOnDelete();
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->unsignedBigInteger('fixed_salary')->default(0);
            $table->unsignedBigInteger('overtime_salary')->default(0);
            $table->unsignedBigInteger('night_shift_salary')->default(0);
            $table->unsignedBigInteger('allowances')->default(0);
            $table->unsignedBigInteger('bonus')->default(0);
            $table->unsignedBigInteger('gross_salary')->default(0);
            $table->unsignedBigInteger('bpjs_deduction')->default(0);
            $table->unsignedBigInteger('water_fee')->default(0);
            $table->unsignedBigInteger('locker_fee')->default(0);
            $table->unsignedBigInteger('penalty')->default(0);
            $table->unsignedBigInteger('other_deduction')->default(0);
            $table->unsignedBigInteger('total_deductions')->default(0);
            $table->unsignedBigInteger('net_salary')->default(0);
            $table->timestamps();

            $table->unique(['payroll_period_id', 'karyawan_id']);
            $table->index('karyawan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
