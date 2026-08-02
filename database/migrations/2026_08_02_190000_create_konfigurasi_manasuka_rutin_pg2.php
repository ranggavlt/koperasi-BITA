<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('konfigurasi_manasuka_rutin')) {
            Schema::create('konfigurasi_manasuka_rutin', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('anggota_id')->constrained('anggota')->restrictOnDelete();
                $table->foreignId('siklus_keanggotaan_id')->constrained('siklus_keanggotaan')->restrictOnDelete();
                $table->string('status', 20);
                $table->decimal('nominal_snapshot', 15, 2);
                $table->date('berlaku_mulai');
                $table->text('alasan');
                $table->string('idempotency_key', 191)->unique();
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->index(
                    ['anggota_id', 'siklus_keanggotaan_id', 'berlaku_mulai'],
                    'manasuka_rutin_anggota_siklus_periode_index'
                );
                $table->index(['status', 'berlaku_mulai'], 'manasuka_rutin_status_periode_index');
            });
        }

        if (Schema::hasTable('simpanan') && ! Schema::hasColumn('simpanan', 'konfigurasi_manasuka_rutin_id')) {
            Schema::table('simpanan', function (Blueprint $table): void {
                $table->foreignId('konfigurasi_manasuka_rutin_id')
                    ->nullable()
                    ->after('siklus_keanggotaan_id')
                    ->constrained('konfigurasi_manasuka_rutin')
                    ->restrictOnDelete();
                $table->index(
                    ['konfigurasi_manasuka_rutin_id', 'status'],
                    'simpanan_manasuka_rutin_config_status_index'
                );
            });
        }

        $this->backfillLegacyProjection();
    }

    public function down(): void
    {
        if (Schema::hasTable('simpanan') && Schema::hasColumn('simpanan', 'konfigurasi_manasuka_rutin_id')) {
            Schema::table('simpanan', function (Blueprint $table): void {
                try {
                    $table->dropIndex('simpanan_manasuka_rutin_config_status_index');
                } catch (Throwable) {
                }

                $table->dropConstrainedForeignId('konfigurasi_manasuka_rutin_id');
            });
        }

        Schema::dropIfExists('konfigurasi_manasuka_rutin');
    }

    private function backfillLegacyProjection(): void
    {
        if (! Schema::hasTable('anggota')
            || ! Schema::hasTable('siklus_keanggotaan')
            || ! Schema::hasColumn('anggota', 'manasuka_rutin_nominal')
            || ! Schema::hasColumn('anggota', 'is_manasuka_rutin_active')) {
            return;
        }

        $berlakuMulai = now(config('app.timezone', 'Asia/Jakarta'))
            ->addMonthNoOverflow()
            ->startOfMonth()
            ->toDateString();

        DB::table('anggota')
            ->where(function ($query): void {
                $query->where('is_manasuka_rutin_active', true)
                    ->orWhere('manasuka_rutin_nominal', '>', 0);
            })
            ->orderBy('id')
            ->get()
            ->each(function ($anggota) use ($berlakuMulai): void {
                $siklusId = DB::table('siklus_keanggotaan')
                    ->where('anggota_id', $anggota->id)
                    ->orderByDesc('siklus_ke')
                    ->value('id');

                if (! $siklusId) {
                    return;
                }

                DB::table('konfigurasi_manasuka_rutin')->insertOrIgnore([
                    'anggota_id' => $anggota->id,
                    'siklus_keanggotaan_id' => $siklusId,
                    'status' => $anggota->is_manasuka_rutin_active ? 'aktif' : 'dijeda',
                    'nominal_snapshot' => $anggota->manasuka_rutin_nominal,
                    'berlaku_mulai' => $berlakuMulai,
                    'alasan' => 'Migrasi aman dari konfigurasi PG-2 legacy.',
                    'idempotency_key' => 'manasuka-rutin:migrasi-legacy:'.$anggota->id.':'.Str::uuid(),
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }
};
