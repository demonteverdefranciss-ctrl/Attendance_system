<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('camera_id')
                ->nullable()
                ->after('adviser_id')
                ->constrained('cameras')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('camera_id');
        });
    }
};
