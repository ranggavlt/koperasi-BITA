<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pinjaman') || ! Schema::hasTable('siklus_keanggotaan')) {
            return;
        }

        Schema::table('pinjaman', function (Blueprint $table): void {
            if (! Schema::hasColumn('pinjaman', 'siklus_keanggotaan_id')) {
                $table->foreignId('siklus_keanggotaan_id')
                    ->nullable()
                    ->after('anggota_id')
                    ->constrained('siklus_keanggotaan')
                    ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pinjaman') || ! Schema::hasColumn('pinjaman', 'siklus_keanggotaan_id')) {
            return;
        }

        Schema::table('pinjaman', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('siklus_keanggotaan_id');
        });
    }
};
