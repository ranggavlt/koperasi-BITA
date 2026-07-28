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
        Schema::create('vendor', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('kontak', 100)->nullable();
            $table->text('alamat')->nullable();
            $table->timestamps();
        });

        Schema::table('aset_koperasi', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->constrained('vendor')->nullOnDelete();
            $table->decimal('harga_dasar_vendor', 15, 2)->default(0);
        });

        Schema::create('invoice_penagihan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_invoice', 50)->unique();
            $table->foreignId('perusahaan_id')->constrained('perusahaan')->restrictOnDelete();
            $table->date('tanggal_invoice');
            $table->date('jatuh_tempo');
            $table->decimal('total_tagihan', 15, 2)->default(0);
            $table->string('status', 20)->default('unpaid'); // unpaid, paid
            $table->timestamps();
        });
        
        Schema::create('invoice_penagihan_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_penagihan_id')->constrained('invoice_penagihan')->cascadeOnDelete();
            $table->string('deskripsi', 255);
            $table->decimal('nominal', 15, 2)->default(0);
            $table->nullableMorphs('referensi'); // e.g. sewa_mobil, sewa_printer
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_penagihan_detail');
        Schema::dropIfExists('invoice_penagihan');
        
        Schema::table('aset_koperasi', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['vendor_id', 'harga_dasar_vendor']);
        });

        Schema::dropIfExists('vendor');
    }
};
