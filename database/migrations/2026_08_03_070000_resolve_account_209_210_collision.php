<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('akun')) {
            return;
        }

        DB::transaction(function (): void {
            $account209 = DB::table('akun')->where('kode_akun', '209')->lockForUpdate()->first();
            $account210 = DB::table('akun')->where('kode_akun', '210')->lockForUpdate()->first();

            if ($account209 && ! in_array($account209->nama_akun, [
                'Utang Vendor Sewa Mobil',
                'Dana Sosial Tersedia',
            ], true)) {
                throw new RuntimeException('Migration CORE-1 berhenti: kode akun 209 dipakai akun lain dan tidak boleh ditimpa otomatis.');
            }

            if ($account210 && $account210->nama_akun !== 'Dana Sosial Tersedia') {
                throw new RuntimeException('Migration CORE-1 berhenti: kode akun 210 sudah dipakai akun lain dan tidak boleh ditimpa otomatis.');
            }

            if (! $account209) {
                $account209Id = DB::table('akun')->insertGetId($this->accountPayload(
                    '209',
                    'Utang Vendor Sewa Mobil',
                    'Kewajiban kepada vendor eksternal atas biaya dasar sewa mobil vendor-based.'
                ));
            } else {
                $account209Id = (int) $account209->id;
                DB::table('akun')->where('id', $account209Id)->update($this->accountPayload(
                    '209',
                    'Utang Vendor Sewa Mobil',
                    'Kewajiban kepada vendor eksternal atas biaya dasar sewa mobil vendor-based.',
                    false
                ));
            }

            if (! $account210) {
                $account210Id = DB::table('akun')->insertGetId($this->accountPayload(
                    '210',
                    'Dana Sosial Tersedia',
                    'Saldo dana sosial yang telah disetujui dan belum dibayarkan sebagai klaim.'
                ));
            } else {
                $account210Id = (int) $account210->id;
                DB::table('akun')->where('id', $account210Id)->update($this->accountPayload(
                    '210',
                    'Dana Sosial Tersedia',
                    'Saldo dana sosial yang telah disetujui dan belum dibayarkan sebagai klaim.',
                    false
                ));
            }

            $this->reclassifyDanaSosialJournalLines($account209Id, $account210Id);
        });
    }

    public function down(): void
    {
        // Pemisahan COA tidak boleh dibalik karena akan mencampur kembali histori
        // Utang Vendor Sewa Mobil dengan Dana Sosial.
    }

    private function accountPayload(string $code, string $name, string $description, bool $includeCreatedAt = true): array
    {
        $payload = [
            'kode_akun' => $code,
            'nama_akun' => $name,
            'kategori' => 'kewajiban',
            'posisi_saldo' => 'kredit',
            'is_aktif' => true,
            'is_sistem' => true,
            'keterangan' => $description,
        ];

        if (Schema::hasColumn('akun', 'is_beban_operasional')) {
            $payload['is_beban_operasional'] = false;
        }

        if (Schema::hasColumn('akun', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        if ($includeCreatedAt && Schema::hasColumn('akun', 'created_at')) {
            $payload['created_at'] = now();
        }

        return $payload;
    }

    private function reclassifyDanaSosialJournalLines(int $account209Id, int $account210Id): void
    {
        if (! Schema::hasTable('jurnal_umum') || ! Schema::hasTable('jurnal_umum_detail')) {
            return;
        }

        $journalIds = DB::table('jurnal_umum')
            ->where(function ($query): void {
                $query->where('idempotency_key', 'like', 'dana-%')
                    ->orWhereIn('referensi_tipe', [
                        'App\\Models\\DanaSosialSumber',
                        'App\\Models\\KlaimDanaSosial',
                    ]);
            })
            ->pluck('id');

        if ($journalIds->isEmpty()) {
            return;
        }

        DB::table('jurnal_umum_detail')
            ->whereIn('jurnal_umum_id', $journalIds)
            ->where(function ($query) use ($account209Id): void {
                $query->where('akun_id', $account209Id)
                    ->orWhere('akun_kode', '209')
                    ->orWhere('akun_nama', 'Dana Sosial Tersedia');
            })
            ->update([
                'akun_id' => $account210Id,
                'akun_kode' => '210',
                'akun_nama' => 'Dana Sosial Tersedia',
                'updated_at' => now(),
            ]);
    }
};
