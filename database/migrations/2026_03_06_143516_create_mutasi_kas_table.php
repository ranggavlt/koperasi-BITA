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
        Schema::create('mutasi_kas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dompet_id')
                  ->constrained('dompet_koperasi')
                  ->cascadeOnDelete();

            $table->enum('tipe', ['masuk', 'keluar']);
            $table->decimal('jumlah', 15, 2);

            $table->text('keterangan')->nullable();

            $table->string('referensi_tipe')->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();

            $table->date('tanggal');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mutasi_kas');
    }
};