<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title', 150);
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'created_at']);
            $table->index(['teacher_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_notifications');
    }
};
