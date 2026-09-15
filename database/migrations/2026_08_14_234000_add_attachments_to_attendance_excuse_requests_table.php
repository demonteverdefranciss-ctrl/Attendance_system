<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_excuse_requests', function (Blueprint $table) {
            $table->string('letter_pdf_path')->nullable()->after('letter_body');
            $table->string('letter_pdf_name')->nullable()->after('letter_pdf_path');
            $table->string('photo_path')->nullable()->after('letter_pdf_name');
            $table->string('photo_name')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_excuse_requests', function (Blueprint $table) {
            $table->dropColumn(['letter_pdf_path', 'letter_pdf_name', 'photo_path', 'photo_name']);
        });
    }
};
