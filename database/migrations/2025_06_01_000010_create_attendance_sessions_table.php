<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A concrete instance of a schedule window on a given date.
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->date('session_date');
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
            $table->index(['section_id', 'session_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
