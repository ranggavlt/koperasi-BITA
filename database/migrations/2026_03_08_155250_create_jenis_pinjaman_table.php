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
        Schema::create('jenis_pinjaman', function (Blueprint $table) {

            $table->id();

            $table->string('nama_pinjaman');
            // contoh: Pinjaman Reguler, Pinjaman Darurat

            $table->decimal('bunga_persen',5,2)->default(0);
            // contoh: 1.50 %

            $table->integer('tenor_bulan')->nullable();
            // contoh: 12 bulan

            $table->text('keterangan')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jenis_pinjaman');
    }
};