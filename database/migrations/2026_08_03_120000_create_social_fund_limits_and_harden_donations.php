<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('batas_klaim_dana_sosial')) Schema::create('batas_klaim_dana_sosial', function (Blueprint $table): void {
            $table->id();
            $table->string('kategori', 30)->index();
            $table->decimal('nominal_maksimal', 15, 2);
            $table->date('berlaku_mulai')->index();
            $table->text('alasan');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['kategori', 'berlaku_mulai'], 'batas_dana_sosial_kategori_berlaku_unique');
        });

        Schema::table('dana_sosial_sumber', function (Blueprint $table): void {
            if (! Schema::hasColumn('dana_sosial_sumber', 'dompet_id')) $table->foreignId('dompet_id')->nullable()->after('shu_koperasi_id')->constrained('dompet_koperasi')->restrictOnDelete();
            if (! Schema::hasColumn('dana_sosial_sumber', 'metode_penerimaan')) $table->string('metode_penerimaan', 30)->nullable()->after('dompet_id');
            if (! Schema::hasColumn('dana_sosial_sumber', 'tanggal_diterima')) $table->date('tanggal_diterima')->nullable()->after('metode_penerimaan');
            if (! Schema::hasColumn('dana_sosial_sumber', 'nomor_referensi')) $table->string('nomor_referensi', 100)->nullable()->after('tanggal_diterima');
            if (! Schema::hasColumn('dana_sosial_sumber', 'bukti_penerimaan')) $table->string('bukti_penerimaan', 255)->nullable()->after('nomor_referensi');
            if (! Schema::hasColumn('dana_sosial_sumber', 'approval_reason')) $table->text('approval_reason')->nullable()->after('approved_at');
            if (! Schema::hasColumn('dana_sosial_sumber', 'reversal_journal_id')) $table->foreignId('reversal_journal_id')->nullable()->after('approval_reason')->constrained('jurnal_umum')->restrictOnDelete();
            if (! Schema::hasColumn('dana_sosial_sumber', 'reversed_by')) $table->foreignId('reversed_by')->nullable()->after('reversal_journal_id')->constrained('users')->restrictOnDelete();
            if (! Schema::hasColumn('dana_sosial_sumber', 'reversed_at')) $table->timestamp('reversed_at')->nullable()->after('reversed_by');
            if (! Schema::hasColumn('dana_sosial_sumber', 'reversal_reason')) $table->text('reversal_reason')->nullable()->after('reversed_at');
        });

        Schema::table('klaim_dana_sosial', function (Blueprint $table): void {
            if (! Schema::hasColumn('klaim_dana_sosial', 'batas_klaim_id')) $table->foreignId('batas_klaim_id')->nullable()->after('kategori')->constrained('batas_klaim_dana_sosial')->restrictOnDelete();
            if (! Schema::hasColumn('klaim_dana_sosial', 'batas_nominal_snapshot')) $table->decimal('batas_nominal_snapshot', 15, 2)->nullable()->after('batas_klaim_id');
            if (! Schema::hasColumn('klaim_dana_sosial', 'batas_berlaku_snapshot')) $table->date('batas_berlaku_snapshot')->nullable()->after('batas_nominal_snapshot');
            if (! Schema::hasColumn('klaim_dana_sosial', 'approval_reason')) $table->text('approval_reason')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('klaim_dana_sosial', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('batas_klaim_id');
            $table->dropColumn(['batas_nominal_snapshot', 'batas_berlaku_snapshot', 'approval_reason']);
        });
        Schema::table('dana_sosial_sumber', function (Blueprint $table): void {
            foreach (['dompet_id', 'reversal_journal_id', 'reversed_by'] as $column) $table->dropConstrainedForeignId($column);
            $table->dropColumn(['metode_penerimaan', 'tanggal_diterima', 'nomor_referensi', 'bukti_penerimaan', 'approval_reason', 'reversed_at', 'reversal_reason']);
        });
        Schema::dropIfExists('batas_klaim_dana_sosial');
    }
};
