<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->guardDuplicateRentalReferences();

        Schema::table('invoice_penagihan', function (Blueprint $table): void {
            $table->decimal('total_dibayar', 15, 2)->default(0)->after('total_tagihan');
            $table->decimal('sisa_tagihan', 15, 2)->default(0)->after('total_dibayar');
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users', indexName: 'invoice_b2b_creator_fk')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('created_by');
            $table->string('idempotency_key', 191)->nullable()->after('finalized_at')->unique();
            $table->index(['perusahaan_id', 'status', 'jatuh_tempo'], 'invoice_b2b_company_status_due_idx');
        });

        DB::table('invoice_penagihan')->update([
            'sisa_tagihan' => DB::raw('total_tagihan'),
        ]);

        Schema::table('invoice_penagihan_detail', function (Blueprint $table): void {
            $table->string('status', 20)->default('aktif')->after('nominal');
            $table->decimal('total_dikembalikan', 15, 2)->default(0)->after('status');
            $table->unique(['referensi_type', 'referensi_id'], 'invoice_detail_rental_reference_uq');
        });

        Schema::create('pembayaran_invoice_penagihan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_penagihan_id')->constrained('invoice_penagihan', indexName: 'invoice_payment_invoice_fk')->restrictOnDelete();
            $table->foreignId('dompet_id')->constrained('dompet_koperasi', indexName: 'invoice_payment_wallet_fk')->restrictOnDelete();
            $table->string('metode', 30);
            $table->decimal('jumlah', 15, 2);
            $table->date('tanggal_bayar');
            $table->string('nomor_referensi', 120)->nullable();
            $table->foreignId('created_by')->constrained('users', indexName: 'invoice_payment_creator_fk')->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->index(['invoice_penagihan_id', 'tanggal_bayar'], 'invoice_payment_invoice_date_idx');
        });

        Schema::create('alokasi_pembayaran_invoice', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pembayaran_invoice_id')->constrained('pembayaran_invoice_penagihan', indexName: 'invoice_alloc_payment_fk')->restrictOnDelete();
            $table->foreignId('invoice_penagihan_detail_id')->constrained('invoice_penagihan_detail', indexName: 'invoice_alloc_detail_fk')->restrictOnDelete();
            $table->decimal('jumlah', 15, 2);
            $table->timestamps();

            $table->unique(['pembayaran_invoice_id', 'invoice_penagihan_detail_id'], 'invoice_payment_allocation_uq');
        });

        Schema::create('pembayaran_vendor_sewa', function (Blueprint $table): void {
            $table->id();
            $table->string('sewa_type');
            $table->unsignedBigInteger('sewa_id');
            $table->foreignId('dompet_id')->constrained('dompet_koperasi', indexName: 'vendor_payment_wallet_fk')->restrictOnDelete();
            $table->string('metode', 30);
            $table->decimal('jumlah', 15, 2);
            $table->date('tanggal_bayar');
            $table->string('nomor_referensi', 120)->nullable();
            $table->string('status', 30)->default('dibayar');
            $table->text('alasan_pengembalian')->nullable();
            $table->timestamp('diminta_kembali_pada')->nullable();
            $table->foreignId('diminta_kembali_oleh')->nullable()->constrained('users', indexName: 'vendor_refund_requester_fk')->restrictOnDelete();
            $table->timestamp('dikembalikan_pada')->nullable();
            $table->foreignId('dikembalikan_oleh')->nullable()->constrained('users', indexName: 'vendor_refund_confirmer_fk')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users', indexName: 'vendor_payment_creator_fk')->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->unique(['sewa_type', 'sewa_id'], 'vendor_rental_payment_reference_uq');
            $table->index(['status', 'tanggal_bayar'], 'vendor_rental_payment_status_date_idx');
        });

        Schema::create('pengembalian_invoice_penagihan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_penagihan_detail_id')->constrained('invoice_penagihan_detail', indexName: 'invoice_refund_detail_fk')->restrictOnDelete();
            $table->foreignId('dompet_id')->constrained('dompet_koperasi', indexName: 'invoice_refund_wallet_fk')->restrictOnDelete();
            $table->string('metode', 30);
            $table->decimal('jumlah', 15, 2);
            $table->date('tanggal_pengembalian');
            $table->string('nomor_referensi', 120)->nullable();
            $table->text('alasan');
            $table->foreignId('created_by')->constrained('users', indexName: 'invoice_refund_creator_fk')->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->index(['invoice_penagihan_detail_id', 'tanggal_pengembalian'], 'invoice_refund_detail_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengembalian_invoice_penagihan');
        Schema::dropIfExists('pembayaran_vendor_sewa');
        Schema::dropIfExists('alokasi_pembayaran_invoice');
        Schema::dropIfExists('pembayaran_invoice_penagihan');

        Schema::table('invoice_penagihan_detail', function (Blueprint $table): void {
            $table->dropUnique('invoice_detail_rental_reference_uq');
            $table->dropColumn(['status', 'total_dikembalikan']);
        });

        Schema::table('invoice_penagihan', function (Blueprint $table): void {
            $table->dropIndex('invoice_b2b_company_status_due_idx');
            $table->dropUnique('invoice_penagihan_idempotency_key_unique');
            $table->dropForeign('invoice_b2b_creator_fk');
            $table->dropColumn(['total_dibayar', 'sisa_tagihan', 'created_by', 'finalized_at', 'idempotency_key']);
        });
    }

    private function guardDuplicateRentalReferences(): void
    {
        $duplicate = DB::table('invoice_penagihan_detail')
            ->select('referensi_type', 'referensi_id')
            ->whereNotNull('referensi_type')
            ->whereNotNull('referensi_id')
            ->groupBy('referensi_type', 'referensi_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new \RuntimeException(
                'Migration invoice B2B dibatalkan: satu kontrak ditemukan pada lebih dari satu invoice. '
                . 'Selesaikan duplikasi referensi invoice terlebih dahulu tanpa menghapus histori.'
            );
        }
    }
};
