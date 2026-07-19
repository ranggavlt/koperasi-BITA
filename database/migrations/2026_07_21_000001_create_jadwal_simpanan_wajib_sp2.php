<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jadwal_simpanan_wajib')) {
            Schema::create('jadwal_simpanan_wajib', function (Blueprint $table): void {
                $table->id();
                $table->string('kode_tagihan', 24)->unique();
                $table->foreignId('anggota_id')
                    ->constrained('anggota')
                    ->restrictOnDelete();
                $table->foreignId('siklus_keanggotaan_id')
                    ->nullable()
                    ->constrained('siklus_keanggotaan')
                    ->restrictOnDelete();
                $table->foreignId('jenis_simpanan_id')
                    ->constrained('jenis_simpanan')
                    ->restrictOnDelete();
                $table->date('periode');
                $table->decimal('nominal_snapshot', 15, 2);
                $table->unsignedTinyInteger('interval_bulan_snapshot');
                $table->string('kode_jenis_snapshot', 60);
                $table->string('nama_jenis_snapshot', 120);
                $table->string('status', 40)->default('outstanding');
                $table->timestamp('reserved_at')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignId('settled_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->unique(
                    ['anggota_id', 'siklus_keanggotaan_id', 'jenis_simpanan_id', 'periode'],
                    'jadwal_swj_anggota_siklus_jenis_periode_unique'
                );
                $table->index(['anggota_id', 'status', 'periode'], 'jadwal_swj_anggota_status_periode_index');
                $table->index(['periode', 'status'], 'jadwal_swj_periode_status_index');
                $table->index(['jenis_simpanan_id', 'periode'], 'jadwal_swj_jenis_periode_index');
            });
        }

        Schema::table('simpanan', function (Blueprint $table): void {
            if (! Schema::hasColumn('simpanan', 'jadwal_simpanan_wajib_id')) {
                $table->foreignId('jadwal_simpanan_wajib_id')
                    ->nullable()
                    ->constrained('jadwal_simpanan_wajib')
                    ->restrictOnDelete();
                $table->unique('jadwal_simpanan_wajib_id', 'simpanan_jadwal_swj_unique');
            }
        });

        $this->dropLegacyGlobalSourceUnique();
    }

    public function down(): void
    {
        if (Schema::hasColumn('simpanan', 'jadwal_simpanan_wajib_id')) {
            Schema::table('simpanan', function (Blueprint $table): void {
                try {
                    $table->dropUnique('simpanan_jadwal_swj_unique');
                } catch (\Throwable) {
                }

                try {
                    $table->dropConstrainedForeignId('jadwal_simpanan_wajib_id');
                } catch (\Throwable) {
                    $table->dropColumn('jadwal_simpanan_wajib_id');
                }
            });
        }

        Schema::dropIfExists('jadwal_simpanan_wajib');

        $this->restoreLegacyGlobalSourceUnique();
    }

    private function dropLegacyGlobalSourceUnique(): void
    {
        if (! Schema::hasTable('pemakaian_potong_gaji')) {
            return;
        }

        try {
            Schema::table('pemakaian_potong_gaji', function (Blueprint $table): void {
                $table->dropUnique('pemakaian_pg_source_unique');
            });
        } catch (\Throwable) {
        }
    }

    private function restoreLegacyGlobalSourceUnique(): void
    {
        if (! Schema::hasTable('pemakaian_potong_gaji')) {
            return;
        }

        try {
            Schema::table('pemakaian_potong_gaji', function (Blueprint $table): void {
                $table->unique(['source_type', 'source_id', 'kategori'], 'pemakaian_pg_source_unique');
            });
        } catch (\Throwable) {
        }
    }
};
