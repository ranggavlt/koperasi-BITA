<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simpanan', function (Blueprint $table) {
            $table->dropForeign(['karyawan_id']);
            $table->dropForeign(['jenis_simpanan_id']);
            $table->foreign('karyawan_id')->references('id')->on('karyawan')->restrictOnDelete();
            $table->foreign('jenis_simpanan_id')->references('id')->on('jenis_simpanan')->restrictOnDelete();
        });

        Schema::table('pinjaman', function (Blueprint $table) {
            $table->dropForeign(['karyawan_id']);
            $table->foreign('karyawan_id')->references('id')->on('karyawan')->restrictOnDelete();
        });

        Schema::table('cicilan_pinjaman', function (Blueprint $table) {
            $table->dropForeign(['pinjaman_id']);
            $table->foreign('pinjaman_id')->references('id')->on('pinjaman')->restrictOnDelete();
        });

        Schema::table('shu_anggota', function (Blueprint $table) {
            $table->dropForeign(['karyawan_id']);
            $table->dropForeign(['shu_koperasi_id']);
            $table->foreign('karyawan_id')->references('id')->on('karyawan')->restrictOnDelete();
            $table->foreign('shu_koperasi_id')->references('id')->on('shu_koperasi')->restrictOnDelete();
        });

        Schema::table('shu_transaksi', function (Blueprint $table) {
            $table->dropForeign(['shu_koperasi_id']);
            $table->foreign('shu_koperasi_id')->references('id')->on('shu_koperasi')->restrictOnDelete();
        });

        Schema::table('mutasi_kas', function (Blueprint $table) {
            $table->dropForeign(['dompet_id']);
            $table->foreign('dompet_id')->references('id')->on('dompet_koperasi')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Rollback tidak boleh menghidupkan kembali cascade berbahaya yang
        // dapat menghapus histori keuangan melalui penghapusan master data.
        Schema::table('simpanan', function (Blueprint $table) {
            $table->dropForeign(['karyawan_id']);
            $table->dropForeign(['jenis_simpanan_id']);
            $table->foreign('karyawan_id')->references('id')->on('karyawan')->restrictOnDelete();
            $table->foreign('jenis_simpanan_id')->references('id')->on('jenis_simpanan')->restrictOnDelete();
        });

        Schema::table('pinjaman', function (Blueprint $table) {
            $table->dropForeign(['karyawan_id']);
            $table->foreign('karyawan_id')->references('id')->on('karyawan')->restrictOnDelete();
        });

        Schema::table('cicilan_pinjaman', function (Blueprint $table) {
            $table->dropForeign(['pinjaman_id']);
            $table->foreign('pinjaman_id')->references('id')->on('pinjaman')->restrictOnDelete();
        });

        Schema::table('shu_anggota', function (Blueprint $table) {
            $table->dropForeign(['karyawan_id']);
            $table->dropForeign(['shu_koperasi_id']);
            $table->foreign('karyawan_id')->references('id')->on('karyawan')->restrictOnDelete();
            $table->foreign('shu_koperasi_id')->references('id')->on('shu_koperasi')->restrictOnDelete();
        });

        Schema::table('shu_transaksi', function (Blueprint $table) {
            $table->dropForeign(['shu_koperasi_id']);
            $table->foreign('shu_koperasi_id')->references('id')->on('shu_koperasi')->restrictOnDelete();
        });

        Schema::table('mutasi_kas', function (Blueprint $table) {
            $table->dropForeign(['dompet_id']);
            $table->foreign('dompet_id')->references('id')->on('dompet_koperasi')->restrictOnDelete();
        });
    }
};
