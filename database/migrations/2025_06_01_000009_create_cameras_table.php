<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Registers the recognition node / Tapo camera as a machine identity.
    public function up(): void
    {
        Schema::create('cameras', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('location', 150)->nullable();
            $table->string('rtsp_url', 255)->nullable();
            $table->string('api_key_hash', 255);          // hashed device API key
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cameras');
    }
};
