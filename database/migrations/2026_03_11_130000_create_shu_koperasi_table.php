<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shu_koperasi', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->decimal('persen_dana_cadangan', 5, 2)->default(40);
            $table->decimal('persen_shu_anggota', 5, 2)->default(40);
            $table->decimal('persen_pengurus', 5, 2)->default(10);
            $table->decimal('persen_dana_sosial', 5, 2)->default(5);
            $table->decimal('persen_dana_pendidikan', 5, 2)->default(5);
            $table->decimal('persen_jasa_modal', 5, 2)->default(50);
            $table->decimal('persen_jasa_usaha', 5, 2)->default(50);
            $table->decimal('total_pendapatan', 15, 2)->default(0);
            $table->decimal('total_biaya', 15, 2)->default(0);
            $table->decimal('shu_total', 15, 2)->default(0);
            $table->decimal('nominal_dana_cadangan', 15, 2)->default(0);
            $table->decimal('nominal_shu_anggota', 15, 2)->default(0);
            $table->decimal('nominal_pengurus', 15, 2)->default(0);
            $table->decimal('nominal_dana_sosial', 15, 2)->default(0);
            $table->decimal('nominal_dana_pendidikan', 15, 2)->default(0);
            $table->decimal('nominal_jasa_modal', 15, 2)->default(0);
            $table->decimal('nominal_jasa_usaha', 15, 2)->default(0);
            $table->decimal('total_bobot_modal', 15, 2)->default(0);
            $table->decimal('total_bobot_usaha', 15, 2)->default(0);
            $table->timestamp('dihitung_pada')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shu_koperasi');
    }
};
