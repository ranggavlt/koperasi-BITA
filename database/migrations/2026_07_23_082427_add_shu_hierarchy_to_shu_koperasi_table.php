<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shu_koperasi', function (Blueprint $table) {
            $table->decimal('persen_pengawas', 5, 2)->default(5)->after('persen_shu_anggota');
            $table->decimal('persen_pembina', 5, 2)->default(5)->after('persen_pengawas');
            $table->decimal('nominal_pengawas', 15, 2)->default(0)->after('nominal_shu_anggota');
            $table->decimal('nominal_pembina', 15, 2)->default(0)->after('nominal_pengawas');
            $table->json('json_pengurus_split')->nullable()->after('nominal_pengurus');
        });

        Schema::table('dompet_koperasi', function (Blueprint $table) {
            $table->decimal('saldo_dana_sosial', 15, 2)->default(0)->after('saldo');
            $table->decimal('saldo_sumbangan_anggota', 15, 2)->default(0)->after('saldo_dana_sosial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shu_koperasi', function (Blueprint $table) {
            $table->dropColumn([
                'persen_pengawas',
                'persen_pembina',
                'nominal_pengawas',
                'nominal_pembina',
                'json_pengurus_split'
            ]);
        });

        Schema::table('dompet_koperasi', function (Blueprint $table) {
            $table->dropColumn(['saldo_dana_sosial', 'saldo_sumbangan_anggota']);
        });
    }
};
