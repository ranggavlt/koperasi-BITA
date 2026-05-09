<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_umum', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->index();
            $table->string('nomor_bukti')->nullable()->index();
            $table->string('keterangan')->nullable();
            $table->string('referensi_tipe')->nullable()->index();
            $table->unsignedBigInteger('referensi_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('jurnal_umum_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurnal_umum_id')->constrained('jurnal_umum')->cascadeOnDelete();
            $table->string('akun_kode')->index();
            $table->string('akun_nama');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_umum_detail');
        Schema::dropIfExists('jurnal_umum');
    }
};

