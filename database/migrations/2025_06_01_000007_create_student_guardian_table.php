<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Junction table: a student can have many guardians and vice versa.
    public function up(): void
    {
        Schema::create('student_guardian', function (Blueprint $table) {
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 50)->nullable(); // mother, father, guardian
            $table->boolean('is_primary')->default(false);
            $table->primary(['student_id', 'guardian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardian');
    }
};
