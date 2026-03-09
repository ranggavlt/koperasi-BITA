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
        Schema::table('detail_penjualan', function (Blueprint $table) {
            $table->boolean('konsinyasi')->default(false)->after('subtotal');

    $table->foreignId('reseller_id')
        ->nullable()
        ->after('konsinyasi')
        ->constrained('reseller')
        ->nullOnDelete();

    $table->integer('harga_setor')->default(0)->after('reseller_id');
    $table->integer('subtotal_setor')->default(0)->after('harga_setor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_penjualan', function (Blueprint $table) {
            //
        });
    }
};
