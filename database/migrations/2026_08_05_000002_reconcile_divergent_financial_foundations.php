<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('periode_akuntansi') && Schema::hasColumn('periode_akuntansi', 'idempotency_key')) {
            Schema::table('periode_akuntansi', function (Blueprint $table): void {
                $table->string('idempotency_key', 191)->nullable()->change();
            });
        }

        if (Schema::hasTable('pembayaran_vendor_sewa')) {
            Schema::table('pembayaran_vendor_sewa', function (Blueprint $table): void {
                foreach (['kode_pembayaran', 'metode_pembayaran', 'vendor_nama_snapshot'] as $column) {
                    if (Schema::hasColumn('pembayaran_vendor_sewa', $column)) {
                        $table->string($column, $column === 'kode_pembayaran' ? 30 : 150)->nullable()->change();
                    }
                }
                if (Schema::hasColumn('pembayaran_vendor_sewa', 'jumlah_bayar')) {
                    $table->decimal('jumlah_bayar', 15, 2)->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('dana_sosial_sumber')) {
            Schema::table('dana_sosial_sumber', function (Blueprint $table): void {
                if (Schema::hasColumn('dana_sosial_sumber', 'nama_sumber')) {
                    $table->string('nama_sumber', 150)->nullable()->change();
                }
                if (Schema::hasColumn('dana_sosial_sumber', 'jenis_sumber')) {
                    $table->string('jenis_sumber', 30)->nullable()->change();
                }
                if (Schema::hasColumn('dana_sosial_sumber', 'nominal_awal')) {
                    $table->decimal('nominal_awal', 15, 2)->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('klaim_dana_sosial')) {
            Schema::table('klaim_dana_sosial', function (Blueprint $table): void {
                if (Schema::hasColumn('klaim_dana_sosial', 'nama_penerima_snapshot')) {
                    $table->string('nama_penerima_snapshot', 150)->nullable()->change();
                }
                if (Schema::hasColumn('klaim_dana_sosial', 'nominal')) {
                    $table->decimal('nominal', 15, 2)->nullable()->change();
                }
                if (Schema::hasColumn('klaim_dana_sosial', 'tanggal_pengajuan')) {
                    $table->date('tanggal_pengajuan')->nullable()->change();
                }
                if (Schema::hasColumn('klaim_dana_sosial', 'keterangan')) {
                    $table->text('keterangan')->nullable()->change();
                }
            });
        }
    }

    public function down(): void
    {
        // Compatibility columns remain nullable so both audited workflows stay readable.
    }
};
