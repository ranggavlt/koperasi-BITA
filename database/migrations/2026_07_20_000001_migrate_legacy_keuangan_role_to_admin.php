<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('users')
            ->where('role', 'keuangan')
            ->update(['role' => 'admin']);
    }

    public function down(): void
    {
        // No-op by design: reverting every "admin" back to legacy "keuangan"
        // would be lossy and could downgrade real Admin accounts.
    }
};
