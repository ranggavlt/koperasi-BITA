<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran_konsinyasi', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pembayaran')->unique();
            $table->foreignId('reseller_id')
                ->constrained('reseller')
                ->cascadeOnDelete();
            $table->foreignId('dompet_id')
                ->constrained('dompet_koperasi')
                ->cascadeOnDelete();
            $table->date('tanggal_bayar');
            $table->unsignedInteger('total_qty')->default(0);
            $table->unsignedBigInteger('total_jual')->default(0);
            $table->unsignedBigInteger('total_bayar')->default(0);
            $table->bigInteger('total_margin')->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran_konsinyasi');
    }
};
