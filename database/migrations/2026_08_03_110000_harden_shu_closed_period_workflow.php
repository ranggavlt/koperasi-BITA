<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shu_koperasi', function (Blueprint $table): void {
            if (! Schema::hasColumn('shu_koperasi', 'periode_akuntansi_id')) $table->foreignId('periode_akuntansi_id')->nullable()->after('id')->unique()->constrained('periode_akuntansi')->restrictOnDelete();
            if (! Schema::hasColumn('shu_koperasi', 'created_by')) $table->foreignId('created_by')->nullable()->after('idempotency_key')->constrained('users')->restrictOnDelete();
            if (! Schema::hasColumn('shu_koperasi', 'calculated_by')) $table->foreignId('calculated_by')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
            if (! Schema::hasColumn('shu_koperasi', 'calculated_at')) $table->timestamp('calculated_at')->nullable()->after('calculated_by');
            if (! Schema::hasColumn('shu_koperasi', 'approved_by')) $table->foreignId('approved_by')->nullable()->after('calculated_at')->constrained('users')->restrictOnDelete();
            if (! Schema::hasColumn('shu_koperasi', 'approved_at')) $table->timestamp('approved_at')->nullable()->after('approved_by');
            if (! Schema::hasColumn('shu_koperasi', 'approval_reason')) $table->text('approval_reason')->nullable()->after('approved_at');
            if (! Schema::hasColumn('shu_koperasi', 'allocation_journal_id')) $table->foreignId('allocation_journal_id')->nullable()->after('approval_reason')->constrained('jurnal_umum')->restrictOnDelete();
            if (! Schema::hasColumn('shu_koperasi', 'posted_by')) $table->foreignId('posted_by')->nullable()->after('allocation_journal_id')->constrained('users')->restrictOnDelete();
            if (! Schema::hasColumn('shu_koperasi', 'posted_at')) $table->timestamp('posted_at')->nullable()->after('posted_by');
            if (! Schema::hasColumn('shu_koperasi', 'reversal_journal_id')) $table->foreignId('reversal_journal_id')->nullable()->after('posted_at')->constrained('jurnal_umum')->restrictOnDelete();
            if (! Schema::hasColumn('shu_koperasi', 'reversed_by')) $table->foreignId('reversed_by')->nullable()->after('reversal_journal_id')->constrained('users')->restrictOnDelete();
            if (! Schema::hasColumn('shu_koperasi', 'reversed_at')) $table->timestamp('reversed_at')->nullable()->after('reversed_by');
            if (! Schema::hasColumn('shu_koperasi', 'reversal_reason')) $table->text('reversal_reason')->nullable()->after('reversed_at');
        });

        $now = now();
        foreach (['utang_shu_anggota', 'utang_shu_pengurus', 'utang_shu_pengawas', 'utang_shu_pembina', 'dana_pendidikan'] as $key) {
            $account = config('account_map.accounts.'.$key);
            DB::table('akun')->upsert([[
                'kode_akun' => $account['kode_akun'],
                'nama_akun' => $account['nama_akun'],
                'kategori' => $account['kategori'],
                'posisi_saldo' => $account['posisi_saldo'],
                'is_aktif' => true,
                'is_sistem' => true,
                'keterangan' => $account['keterangan'],
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['kode_akun'], ['nama_akun', 'kategori', 'posisi_saldo', 'is_aktif', 'is_sistem', 'keterangan', 'updated_at']);
        }
    }

    public function down(): void
    {
        Schema::table('shu_koperasi', function (Blueprint $table): void {
            foreach (['periode_akuntansi_id', 'created_by', 'calculated_by', 'approved_by', 'allocation_journal_id', 'posted_by', 'reversal_journal_id', 'reversed_by'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropColumn(['calculated_at', 'approved_at', 'approval_reason', 'posted_at', 'reversed_at', 'reversal_reason']);
        });
    }
};
