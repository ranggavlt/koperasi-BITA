<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shu_anggota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shu_koperasi_id')
                ->constrained('shu_koperasi')
                ->cascadeOnDelete();
            $table->foreignId('karyawan_id')
                ->constrained('karyawan')
                ->cascadeOnDelete();
            $table->decimal('total_simpanan', 15, 2)->default(0);
            $table->decimal('total_transaksi_usaha', 15, 2)->default(0);
            $table->decimal('nominal_jasa_modal', 15, 2)->default(0);
            $table->decimal('nominal_jasa_usaha', 15, 2)->default(0);
            $table->decimal('nominal_shu', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['shu_koperasi_id', 'karyawan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shu_anggota');
    }
};
