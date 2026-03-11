<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hutang_reseller', function (Blueprint $table) {
            $table->foreignId('pembayaran_konsinyasi_id')
                ->nullable()
                ->after('detail_penjualan_id')
                ->constrained('pembayaran_konsinyasi')
                ->nullOnDelete();
            $table->date('tanggal_bayar')
                ->nullable()
                ->after('tanggal');
        });
    }

    public function down(): void
    {
        Schema::table('hutang_reseller', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pembayaran_konsinyasi_id');
            $table->dropColumn('tanggal_bayar');
        });
    }
};
