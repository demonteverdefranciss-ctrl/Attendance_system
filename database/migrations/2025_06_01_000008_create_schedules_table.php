<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Recurring attendance windows per section; drives automatic session activation.
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');   // 1=Mon .. 7=Sun (ISO-8601)
            $table->time('start_time');
            $table->time('end_time');
            $table->time('late_after')->nullable();       // arrivals after this = late
            $table->enum('type', ['am', 'pm', 'custom'])->default('am');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['section_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
