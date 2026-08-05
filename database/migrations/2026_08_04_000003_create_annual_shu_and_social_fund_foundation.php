<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shu_config', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('versi')->unique();
            $table->date('berlaku_mulai')->index();
            $table->string('dasar_keputusan', 255);
            $table->decimal('persen_dana_cadangan', 5, 2);
            $table->decimal('persen_shu_anggota', 5, 2);
            $table->decimal('persen_pengurus', 5, 2);
            $table->decimal('persen_pengawas', 5, 2);
            $table->decimal('persen_pembina', 5, 2);
            $table->decimal('persen_dana_sosial', 5, 2);
            $table->decimal('persen_dana_pendidikan', 5, 2);
            $table->decimal('persen_jasa_modal', 5, 2);
            $table->decimal('persen_jasa_usaha', 5, 2);
            $table->foreignId('created_by')->constrained('users', indexName: 'shu_config_creator_fk')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('shu_koperasi', function (Blueprint $table): void {
            $table->foreignId('periode_akuntansi_id')->nullable()->after('id')->constrained('periode_akuntansi', indexName: 'shu_period_fk')->restrictOnDelete()->unique();
            $table->foreignId('shu_config_id')->nullable()->after('periode_akuntansi_id')->constrained('shu_config', indexName: 'shu_config_fk')->restrictOnDelete();
            $table->json('config_snapshot')->nullable()->after('shu_config_id');
            $table->string('status', 30)->default('draft')->after('judul')->index();
            $table->decimal('total_dibayar', 15, 2)->default(0)->after('shu_total');
            $table->decimal('total_belum_dibayar', 15, 2)->default(0)->after('total_dibayar');
            $table->foreignId('created_by')->nullable()->after('keterangan')->constrained('users', indexName: 'shu_creator_fk')->restrictOnDelete();
            $table->foreignId('calculated_by')->nullable()->after('created_by')->constrained('users', indexName: 'shu_calculator_fk')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->after('calculated_by')->constrained('users', indexName: 'shu_submitter_fk')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->after('submitted_by')->constrained('users', indexName: 'shu_approver_fk')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('approved_by');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->timestamp('completed_at')->nullable()->after('approved_at');
            $table->string('idempotency_key', 191)->nullable()->after('completed_at')->unique();
        });

        Schema::create('shu_penerima', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shu_koperasi_id')->constrained('shu_koperasi', indexName: 'shu_recipient_shu_fk')->restrictOnDelete();
            $table->string('jenis_penerima', 30);
            $table->foreignId('anggota_id')->nullable()->constrained('anggota', indexName: 'shu_recipient_member_fk')->restrictOnDelete();
            $table->foreignId('pengurus_koperasi_id')->nullable()->constrained('pengurus_koperasi', indexName: 'shu_recipient_officer_fk')->restrictOnDelete();
            $table->string('nama_snapshot', 150);
            $table->string('jabatan_snapshot', 120)->nullable();
            $table->decimal('basis_jasa_modal', 15, 2)->default(0);
            $table->decimal('basis_jasa_usaha', 15, 2)->default(0);
            $table->decimal('nominal_jasa_modal', 15, 2)->default(0);
            $table->decimal('nominal_jasa_usaha', 15, 2)->default(0);
            $table->decimal('nominal_hak', 15, 2);
            $table->string('status_pembayaran', 20)->default('belum_dibayar');
            $table->json('formula_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['shu_koperasi_id', 'jenis_penerima', 'anggota_id'], 'shu_recipient_member_uq');
            $table->index(['shu_koperasi_id', 'jenis_penerima', 'status_pembayaran'], 'shu_recipient_type_status_idx');
        });

        Schema::create('pembayaran_shu', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shu_penerima_id')->constrained('shu_penerima', indexName: 'shu_payment_recipient_fk')->restrictOnDelete()->unique();
            $table->foreignId('dompet_id')->constrained('dompet_koperasi', indexName: 'shu_payment_wallet_fk')->restrictOnDelete();
            $table->string('metode', 30);
            $table->decimal('jumlah', 15, 2);
            $table->date('tanggal_bayar');
            $table->string('nomor_referensi', 120)->nullable();
            $table->foreignId('created_by')->constrained('users', indexName: 'shu_payment_creator_fk')->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();
        });

        Schema::create('dana_sosial_sumber', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_sumber', 40)->unique();
            $table->string('jenis', 30);
            $table->foreignId('shu_koperasi_id')->nullable()->constrained('shu_koperasi', indexName: 'social_source_shu_fk')->restrictOnDelete()->unique();
            $table->foreignId('dompet_id')->nullable()->constrained('dompet_koperasi', indexName: 'social_source_wallet_fk')->restrictOnDelete();
            $table->decimal('jumlah', 15, 2);
            $table->decimal('saldo_tersedia', 15, 2);
            $table->date('tanggal');
            $table->string('nomor_referensi', 120)->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->constrained('users', indexName: 'social_source_creator_fk')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users', indexName: 'social_source_approver_fk')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('status', 20)->default('approved');
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();
        });

        Schema::create('dana_sosial_limit', function (Blueprint $table): void {
            $table->id();
            $table->string('kategori', 40)->unique();
            $table->string('label', 100);
            $table->decimal('maksimal', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'social_limit_updater_fk')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('klaim_dana_sosial', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_klaim', 40)->unique();
            $table->foreignId('anggota_id')->nullable()->constrained('anggota', indexName: 'social_claim_member_fk')->restrictOnDelete();
            $table->string('penerima_manfaat', 150);
            $table->string('kategori', 40);
            $table->date('tanggal_kejadian');
            $table->decimal('nominal_diajukan', 15, 2);
            $table->text('catatan')->nullable();
            $table->string('dokumen_path')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('created_by')->constrained('users', indexName: 'social_claim_creator_fk')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users', indexName: 'social_claim_approver_fk')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('dompet_id')->nullable()->constrained('dompet_koperasi', indexName: 'social_claim_wallet_fk')->restrictOnDelete();
            $table->string('metode_pembayaran', 30)->nullable();
            $table->date('tanggal_bayar')->nullable();
            $table->string('nomor_referensi', 120)->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users', indexName: 'social_claim_payer_fk')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();
        });

        Schema::create('alokasi_klaim_dana_sosial', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('klaim_dana_sosial_id')->constrained('klaim_dana_sosial', indexName: 'social_alloc_claim_fk')->restrictOnDelete();
            $table->foreignId('dana_sosial_sumber_id')->constrained('dana_sosial_sumber', indexName: 'social_alloc_source_fk')->restrictOnDelete();
            $table->decimal('jumlah', 15, 2);
            $table->timestamps();
            $table->unique(['klaim_dana_sosial_id', 'dana_sosial_sumber_id'], 'social_claim_source_allocation_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alokasi_klaim_dana_sosial');
        Schema::dropIfExists('klaim_dana_sosial');
        Schema::dropIfExists('dana_sosial_limit');
        Schema::dropIfExists('dana_sosial_sumber');
        Schema::dropIfExists('pembayaran_shu');
        Schema::dropIfExists('shu_penerima');
        Schema::table('shu_koperasi', function (Blueprint $table): void {
            foreach (['shu_period_fk','shu_config_fk','shu_creator_fk','shu_calculator_fk','shu_submitter_fk','shu_approver_fk'] as $foreign) $table->dropForeign($foreign);
            $table->dropUnique(['periode_akuntansi_id']);
            $table->dropUnique('shu_koperasi_idempotency_key_unique');
            $table->dropColumn(['periode_akuntansi_id','shu_config_id','config_snapshot','status','total_dibayar','total_belum_dibayar','created_by','calculated_by','submitted_by','approved_by','submitted_at','approved_at','completed_at','idempotency_key']);
        });
        Schema::dropIfExists('shu_config');
    }
};
