<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simpanan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('karyawan_id')
                  ->constrained('karyawan')
                  ->cascadeOnDelete();

            $table->foreignId('jenis_simpanan_id')
                  ->constrained('jenis_simpanan')
                  ->cascadeOnDelete();

            $table->decimal('jumlah', 15, 2);

            $table->date('tanggal');

            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simpanan');
    }
};