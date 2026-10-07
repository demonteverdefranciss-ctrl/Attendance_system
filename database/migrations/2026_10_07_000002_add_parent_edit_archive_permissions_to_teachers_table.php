<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->boolean('can_edit_parents')->default(false)->after('can_add_parents');
            $table->boolean('can_archive_parents')->default(false)->after('can_edit_parents');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['can_edit_parents', 'can_archive_parents']);
        });
    }
};
