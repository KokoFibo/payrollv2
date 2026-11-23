<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_visibility_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_salary_limit')->default(4_500_000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_visibility_settings');
    }
};
