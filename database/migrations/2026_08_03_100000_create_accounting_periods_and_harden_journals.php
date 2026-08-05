<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_akuntansi', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama', 150);
            $table->date('tanggal_mulai')->index();
            $table->date('tanggal_selesai')->index();
            $table->string('status', 20)->default('open')->index();
            $table->decimal('total_pendapatan', 18, 2)->default(0);
            $table->decimal('total_beban', 18, 2)->default(0);
            $table->decimal('laba_bersih', 18, 2)->default(0);
            $table->unsignedInteger('jumlah_jurnal')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->json('closing_snapshot')->nullable();
            $table->foreignId('closing_journal_id')->nullable()->constrained('jurnal_umum')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('closing_reason')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->unique(['tanggal_mulai', 'tanggal_selesai'], 'periode_akuntansi_range_unique');
        });

        Schema::table('jurnal_umum', function (Blueprint $table): void {
            $table->foreignId('periode_akuntansi_id')->nullable()->after('idempotency_key')->constrained('periode_akuntansi')->restrictOnDelete();
            $table->string('status', 20)->default('posted')->after('periode_akuntansi_id')->index();
            $table->timestamp('posted_at')->nullable()->after('status');
            $table->boolean('is_adjustment')->default(false)->after('posted_at')->index();
            $table->foreignId('correction_period_id')->nullable()->after('is_adjustment')->constrained('periode_akuntansi')->restrictOnDelete();
            $table->text('correction_reason')->nullable()->after('correction_period_id');
        });

        DB::table('jurnal_umum')->update([
            'status' => 'posted',
            'posted_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('jurnal_umum', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('correction_period_id');
            $table->dropConstrainedForeignId('periode_akuntansi_id');
            $table->dropColumn(['status', 'posted_at', 'is_adjustment', 'correction_reason']);
        });

        Schema::dropIfExists('periode_akuntansi');
    }
};
