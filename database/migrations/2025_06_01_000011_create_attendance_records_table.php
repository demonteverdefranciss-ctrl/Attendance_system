<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->dateTime('time_in')->nullable();
            $table->enum('method', ['face', 'manual'])->default('manual');
            $table->decimal('confidence', 5, 4)->nullable();   // recognition score 0..1
            $table->foreignId('camera_id')->nullable()->constrained('cameras')->nullOnDelete();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('client_uuid')->nullable()->unique(); // idempotent offline sync key
            $table->timestamps();
            // One record per student per session => prevents duplicate attendance.
            $table->unique(['session_id', 'student_id']);
            $table->index(['student_id', 'time_in']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
