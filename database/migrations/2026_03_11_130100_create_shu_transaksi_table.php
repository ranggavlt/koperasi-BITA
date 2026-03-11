<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shu_transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shu_koperasi_id')
                ->constrained('shu_koperasi')
                ->cascadeOnDelete();
            $table->enum('jenis', ['pendapatan', 'biaya']);
            $table->date('tanggal');
            $table->decimal('jumlah', 15, 2);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shu_transaksi');
    }
};
