<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beban_operasional', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_beban', 32)->unique();
            $table->date('tanggal_beban');
            $table->foreignId('dompet_id')
                ->nullable()
                ->constrained('dompet_koperasi')
                ->restrictOnDelete();
            $table->string('metode_pembayaran', 30)->nullable();
            $table->decimal('total_beban', 15, 2)->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->text('keterangan')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('alasan_reversal')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('posted_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('reversed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('reversal_transaksi_id')
                ->nullable()
                ->constrained('reversal_transaksi')
                ->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->index(['tanggal_beban', 'status'], 'beban_operasional_tanggal_status_index');
            $table->index(['dompet_id', 'status'], 'beban_operasional_dompet_status_index');
        });

        Schema::create('beban_operasional_detail', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('beban_operasional_id')
                ->constrained('beban_operasional')
                ->restrictOnDelete();
            $table->foreignId('akun_id')
                ->constrained('akun')
                ->restrictOnDelete();
            $table->foreignId('aset_koperasi_id')
                ->nullable()
                ->constrained('aset_koperasi')
                ->restrictOnDelete();
            $table->string('kode_akun_snapshot', 30)->nullable();
            $table->string('nama_akun_snapshot', 150)->nullable();
            $table->string('kode_aset_snapshot', 30)->nullable();
            $table->string('nama_aset_snapshot', 180)->nullable();
            $table->text('keterangan');
            $table->decimal('nominal', 15, 2);
            $table->timestamps();

            $table->index(['akun_id', 'beban_operasional_id'], 'beban_operasional_detail_akun_index');
            $table->index(['aset_koperasi_id', 'beban_operasional_id'], 'beban_operasional_detail_aset_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beban_operasional_detail');
        Schema::dropIfExists('beban_operasional');
    }
};
