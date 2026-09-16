<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recognition_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->unsignedBigInteger('camera_id')->index();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('session_id');
            $table->string('event_type', 8);
            $table->dateTime('captured_at');
            $table->dateTime('received_at');
            $table->boolean('was_offline')->default(false);
            $table->json('response');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recognition_events');
    }
};
