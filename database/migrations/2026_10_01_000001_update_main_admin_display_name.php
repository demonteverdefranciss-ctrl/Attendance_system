<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('username', 'admin')
            ->whereIn('role_id', DB::table('roles')->select('id')->where('name', 'admin'))
            ->update(['name' => 'Celenia A. Molinyawe', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // The prior display name is unknown; retain the account's current name.
    }
};
