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
        Schema::create('hutang_reseller', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reseller_id')
                  ->constrained('reseller')
                  ->cascadeOnDelete();

            $table->foreignId('detail_penjualan_id')
                  ->constrained('detail_penjualan')
                  ->cascadeOnDelete();

            $table->decimal('jumlah', 15, 2);

            $table->enum('status', ['belum_dibayar', 'sudah_dibayar'])
                  ->default('belum_dibayar');

            $table->date('tanggal');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hutang_reseller');
    }
};