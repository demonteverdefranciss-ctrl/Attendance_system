<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->string('lrn', 20)->nullable()->unique();   // Learner Reference Number (DepEd)
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('birthdate')->nullable();
            $table->boolean('consent_biometric')->default(false); // RA 10173 parental consent
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
