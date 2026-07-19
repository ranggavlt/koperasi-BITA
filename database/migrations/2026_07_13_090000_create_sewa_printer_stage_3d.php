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
            $table->foreignId('karyawan_id')
                ->constrained('karyawan')
                ->restrictOnDelete();
            $table->date('mulai_tanggal');
            $table->date('selesai_tanggal');
            $table->text('kebutuhan')->nullable();
            $table->string('vendor_nama', 150);
            $table->string('vendor_kontak', 80);
            $table->text('vendor_alamat');
            $table->unsignedBigInteger('total_harga_vendor')->default(0);
            $table->unsignedBigInteger('total_margin')->default(0);
            $table->unsignedBigInteger('total_tagihan_perusahaan')->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->string('status_pembayaran', 30)->default('belum_bayar')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
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
            $table->index(['karyawan_id', 'status'], 'sewa_printer_karyawan_status_index');
            $table->index(['recorded_by', 'status'], 'sewa_printer_recorded_status_index');
            $table->index(['vendor_nama', 'status'], 'sewa_printer_vendor_status_index');
        });

        Schema::create('sewa_printer_detail', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sewa_printer_id')
                ->constrained('sewa_printer')
                ->restrictOnDelete();
            $table->string('jenis_model_printer', 150);
            $table->text('spesifikasi_kebutuhan')->nullable();
            $table->unsignedInteger('kuantitas');
            $table->unsignedBigInteger('harga_vendor_per_unit');
            $table->unsignedTinyInteger('margin_persen_snapshot')->default(15);
            $table->unsignedBigInteger('margin_per_unit');
            $table->unsignedBigInteger('harga_tagihan_per_unit');
            $table->unsignedBigInteger('subtotal_harga_vendor');
            $table->unsignedBigInteger('subtotal_margin');
            $table->unsignedBigInteger('subtotal_tagihan');
            $table->timestamps();

            $table->index(['sewa_printer_id', 'jenis_model_printer'], 'sewa_printer_detail_model_index');
        });

        Schema::create('pembayaran_sewa_printer', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sewa_printer_id')
                ->unique()
                ->constrained('sewa_printer')
                ->restrictOnDelete();
            $table->foreignId('dompet_penerimaan_id')
                ->constrained('dompet_koperasi')
                ->restrictOnDelete();
            $table->foreignId('dompet_vendor_id')
                ->constrained('dompet_koperasi')
                ->restrictOnDelete();
            $table->string('metode_penerimaan', 30);
            $table->string('metode_pembayaran_vendor', 30);
            $table->unsignedBigInteger('jumlah_diterima');
            $table->unsignedBigInteger('jumlah_bayar_vendor');
            $table->string('status', 30)->default('paid')->index();
            $table->timestamp('paid_at');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->index(['metode_penerimaan', 'status'], 'pembayaran_sewa_printer_metode_status_index');
            $table->index(['dompet_penerimaan_id', 'status'], 'pembayaran_sewa_printer_penerimaan_status_index');
            $table->index(['dompet_vendor_id', 'status'], 'pembayaran_sewa_printer_vendor_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran_sewa_printer');
        Schema::dropIfExists('sewa_printer_detail');
        Schema::dropIfExists('sewa_printer');
    }
};
