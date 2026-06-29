<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('fcm_token', 512)->nullable();   // device push token
            $table->enum('notify_pref', ['push', 'email', 'sms', 'none'])->default('push');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
