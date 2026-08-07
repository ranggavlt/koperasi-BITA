<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shu_koperasi', function (Blueprint $table): void {
            if (! Schema::hasColumn('shu_koperasi', 'source_snapshot')) {
                $table->json('source_snapshot')->nullable()->after('config_snapshot');
            }
        });

        Schema::table('klaim_dana_sosial', function (Blueprint $table): void {
            if (! Schema::hasColumn('klaim_dana_sosial', 'karyawan_id')) {
                $table->foreignId('karyawan_id')->nullable()->after('anggota_id')->constrained('karyawan')->restrictOnDelete();
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'nama_penerima_snapshot')) {
                $table->string('nama_penerima_snapshot', 150)->nullable()->after('penerima_manfaat');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'nominal')) {
                $table->decimal('nominal', 15, 2)->nullable()->after('nominal_diajukan');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'tanggal_pengajuan')) {
                $table->date('tanggal_pengajuan')->nullable()->after('tanggal_kejadian');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('catatan');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'alasan_penolakan')) {
                $table->text('alasan_penolakan')->nullable()->after('catatan_persetujuan');
            }
        });
    }

    public function down(): void
    {
        // Rekonsiliasi histori keuangan sengaja tidak dihapus saat rollback.
    }
};
