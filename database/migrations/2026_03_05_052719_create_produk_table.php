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
        Schema::create('produk', function (Blueprint $table) {
            $table->id();
            $table->string('nama_produk');

        $table->foreignId('kategori_id')
            ->constrained('kategori_produk');

        $table->integer('harga_beli')->default(0);
        $table->integer('harga_jual');

        $table->integer('stok')->default(0);

        $table->boolean('konsinyasi')->default(false);

        $table->foreignId('reseller_id')
            ->nullable()
            ->constrained('reseller');

        $table->integer('harga_setor')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produk');
    }
};
