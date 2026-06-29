<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Pre-aggregated daily counts per section so dashboards stay fast.
    public function up(): void
    {
        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('total_students')->default(0);
            $table->unsignedInteger('present')->default(0);
            $table->unsignedInteger('absent')->default(0);
            $table->unsignedInteger('late')->default(0);
            $table->unsignedInteger('excused')->default(0);
            $table->decimal('attendance_rate', 5, 2)->default(0); // percentage 0..100
            $table->timestamps();
            $table->unique(['section_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily');
    }
};
