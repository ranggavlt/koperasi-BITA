<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_akuntansi', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama', 120);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('status', 20)->default('open');
            $table->decimal('total_pendapatan', 15, 2)->default(0);
            $table->decimal('total_beban', 15, 2)->default(0);
            $table->decimal('laba_bersih', 15, 2)->default(0);
            $table->foreignId('created_by')->constrained('users', indexName: 'period_creator_fk')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users', indexName: 'period_closer_fk')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closing_journal_id')->nullable()->constrained('jurnal_umum', indexName: 'period_closing_journal_fk')->restrictOnDelete();
            $table->string('closing_idempotency_key', 191)->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'tanggal_mulai', 'tanggal_selesai'], 'accounting_period_status_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_akuntansi');
    }
};
