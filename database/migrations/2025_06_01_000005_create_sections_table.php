<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adviser_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('name', 100);                       // e.g. "Mabini"
            $table->string('grade_level', 20)->default('Grade 6');
            $table->string('school_year', 20);                 // e.g. "2026-2027"
            $table->timestamps();
            $table->unique(['name', 'grade_level', 'school_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
