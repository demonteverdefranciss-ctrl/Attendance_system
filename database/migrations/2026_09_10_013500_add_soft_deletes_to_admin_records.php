<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'users',
            'teachers',
            'guardians',
            'students',
            'sections',
            'cameras',
            'schedules',
            'no_class_days',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }

        Schema::table('no_class_days', function (Blueprint $table) {
            $table->dropUnique(['date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::table('no_class_days', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->unique('date');
        });

        $tables = [
            'users',
            'teachers',
            'guardians',
            'students',
            'sections',
            'cameras',
            'schedules',
            'no_class_days',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
