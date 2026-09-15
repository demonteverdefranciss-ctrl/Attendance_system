<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_photo_submissions', function (Blueprint $table) {
            $table->timestamp('system_validated_at')->nullable()->after('consent_acknowledged');
            $table->json('validation_summary')->nullable()->after('system_validated_at');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_photo_submissions', function (Blueprint $table) {
            $table->dropColumn(['system_validated_at', 'validation_summary']);
        });
    }
};
