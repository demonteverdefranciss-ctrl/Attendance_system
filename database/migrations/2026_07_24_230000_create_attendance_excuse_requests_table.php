<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_excuse_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('streak_count')->default(3);
            $table->json('attendance_record_ids');
            $table->json('streak_summary')->nullable(); // dates + statuses for display
            $table->enum('status', ['awaiting_letter', 'pending', 'approved', 'rejected'])
                ->default('awaiting_letter');
            $table->text('letter_body')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['guardian_id', 'status']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_excuse_requests');
    }
};
