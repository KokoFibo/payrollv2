<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'companies',
            'directorates',
            'departments',
            'jabatans',
            'etnis',
        ] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable()->unique();
                $table->timestamps();
            });
        }

        Schema::create('job_grades', function (Blueprint $table) {
            $table->id();
            $table->string('grade')->unique();
            $table->string('grade_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_grades');
        Schema::dropIfExists('etnis');
        Schema::dropIfExists('jabatans');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('directorates');
        Schema::dropIfExists('companies');
    }
};
