<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_enrollment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lrn', 20);
            $table->string('relationship', 50)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['guardian_id', 'status']);
            $table->index(['lrn', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_enrollment_requests');
    }
};
