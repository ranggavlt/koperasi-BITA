<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinjaman', function (Blueprint $table) {
            $table->id();

            $table->foreignId('karyawan_id')
                  ->constrained('karyawan')
                  ->cascadeOnDelete();

            $table->decimal('jumlah_pinjaman', 15, 2);

            $table->decimal('bunga_persen', 5, 2)->default(0);

            $table->integer('tenor_bulan');

            $table->decimal('sisa_pinjaman', 15, 2);

            $table->enum('status', ['aktif', 'lunas'])
                  ->default('aktif');

            $table->date('tanggal_pinjaman');

            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinjaman');
    }
};