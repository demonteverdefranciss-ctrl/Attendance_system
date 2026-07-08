<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_photo_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->boolean('consent_acknowledged')->default(false);
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['guardian_id', 'status']);
        });

        Schema::create('biometric_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('biometric_photo_submissions')->cascadeOnDelete();
            $table->string('storage_path', 255);
            $table->string('original_name', 255)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_photos');
        Schema::dropIfExists('biometric_photo_submissions');
    }
};
