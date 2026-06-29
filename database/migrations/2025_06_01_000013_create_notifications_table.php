<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('channel', ['push', 'email', 'sms'])->default('push');
            $table->string('type', 50);                        // arrival, absent, late, announcement
            $table->string('title', 150)->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed', 'read'])->default('pending');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
            $table->index(['guardian_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
