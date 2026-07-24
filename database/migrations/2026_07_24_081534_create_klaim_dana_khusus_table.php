<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('klaim_dana_khusus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dompet_id')->constrained('dompet_koperasi')->restrictOnDelete();
            $table->enum('jenis_dana', ['sosial', 'sumbangan']);
            $table->string('kategori', 50); // meninggal, melahirkan, khitan, proposal
            $table->decimal('nominal', 15, 2);
            $table->date('tanggal');
            $table->text('keterangan');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('klaim_dana_khusus');
    }
};
