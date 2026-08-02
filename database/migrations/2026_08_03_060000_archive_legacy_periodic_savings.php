<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jadwal_simpanan_wajib')) {
            return;
        }

        if (! Schema::hasColumn('jadwal_simpanan_wajib', 'sp7_archived_at')) {
            Schema::table('jadwal_simpanan_wajib', function (Blueprint $table): void {
                $table->timestamp('sp7_archived_at')->nullable()->after('recovered_at');
            });
        }

        DB::table('jadwal_simpanan_wajib')
            ->whereNull('sp7_archived_at')
            ->update(['sp7_archived_at' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasTable('jadwal_simpanan_wajib')
            && Schema::hasColumn('jadwal_simpanan_wajib', 'sp7_archived_at')) {
            Schema::table('jadwal_simpanan_wajib', function (Blueprint $table): void {
                $table->dropColumn('sp7_archived_at');
            });
        }
    }
};
