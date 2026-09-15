<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('no_class_days', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('name');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('no_class_days', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
