<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->binary('embedding')->nullable();           // ArcFace/FaceNet vector
            $table->integer('lbph_label')->nullable();         // label id for LBPH phase
            $table->string('image_path', 255)->nullable();     // reference enrollment image
            $table->unsignedInteger('model_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['student_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_data');
    }
};
