<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('child_enrollment_requests', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('lrn');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->enum('gender', ['male', 'female'])->nullable()->after('last_name');
            $table->string('grade_level', 50)->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('child_enrollment_requests', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'gender', 'grade_level']);
        });
    }
};
