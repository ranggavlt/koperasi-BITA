<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cicilan_pinjaman', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pinjaman_id')
                  ->constrained('pinjaman')
                  ->cascadeOnDelete();

            $table->decimal('jumlah_cicilan', 15, 2);

            $table->string('periode', 7); // contoh: 2026-02

            $table->enum('status', ['belum_bayar', 'sudah_bayar'])
                  ->default('belum_bayar');

            $table->date('tanggal_bayar')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cicilan_pinjaman');
    }
};