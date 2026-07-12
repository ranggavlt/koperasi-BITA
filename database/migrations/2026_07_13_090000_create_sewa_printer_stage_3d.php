<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sewa_printer', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_sewa', 24)->unique();
            $table->string('nama_perusahaan_snapshot', 150);
            $table->foreignId('karyawan_pic_id')
                ->constrained('karyawan')
                ->restrictOnDelete();
            $table->date('mulai_tanggal');
            $table->date('selesai_tanggal');
            $table->decimal('total_harga_dasar', 15, 2)->default(0);
            $table->decimal('total_margin', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->string('status_pembayaran', 30)->default('belum_bayar')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->index(['mulai_tanggal', 'selesai_tanggal', 'status'], 'sewa_printer_periode_status_index');
            $table->index(['karyawan_pic_id', 'status'], 'sewa_printer_pic_status_index');
        });

        Schema::create('sewa_printer_detail', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sewa_printer_id')
                ->constrained('sewa_printer')
                ->restrictOnDelete();
            $table->foreignId('aset_koperasi_id')
                ->constrained('aset_koperasi')
                ->restrictOnDelete();
            $table->string('kode_aset_snapshot', 30);
            $table->string('nomor_seri_snapshot', 100);
            $table->string('merek_snapshot', 100);
            $table->string('model_snapshot', 100);
            $table->decimal('harga_dasar', 15, 2);
            $table->decimal('margin_persen_snapshot', 5, 2)->default(15);
            $table->decimal('margin_nominal', 15, 2);
            $table->decimal('total_harga', 15, 2);
            $table->timestamps();

            $table->unique(['sewa_printer_id', 'aset_koperasi_id'], 'sewa_printer_detail_unique_aset');
            $table->index(['aset_koperasi_id', 'sewa_printer_id'], 'sewa_printer_detail_aset_index');
        });

        Schema::create('pembayaran_sewa_printer', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sewa_printer_id')
                ->unique()
                ->constrained('sewa_printer')
                ->restrictOnDelete();
            $table->foreignId('dompet_id')
                ->constrained('dompet_koperasi')
                ->restrictOnDelete();
            $table->string('metode_pembayaran', 30);
            $table->decimal('jumlah_bayar', 15, 2);
            $table->string('status', 30)->default('paid')->index();
            $table->timestamp('paid_at');
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->index(['metode_pembayaran', 'status'], 'pembayaran_sewa_printer_metode_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran_sewa_printer');
        Schema::dropIfExists('sewa_printer_detail');
        Schema::dropIfExists('sewa_printer');
    }
};
