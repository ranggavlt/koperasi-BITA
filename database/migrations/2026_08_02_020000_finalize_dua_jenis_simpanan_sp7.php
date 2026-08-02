<?php

use App\Models\JenisSimpanan;
use App\Models\Simpanan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->guardAmbiguousLegacyData();
        $this->syncFinalAccounts();
        $this->extendSimpananForFinalWajib();
        $this->syncFinalJenisSimpanan();
    }

    public function down(): void
    {
        $this->guardRollback();

        if (Schema::hasColumn('simpanan', 'simpanan_wajib_siklus_id')) {
            Schema::table('simpanan', function (Blueprint $table): void {
                try {
                    $table->dropUnique('simpanan_wajib_siklus_unique');
                } catch (\Throwable) {
                }

                $table->dropColumn('simpanan_wajib_siklus_id');
            });
        }

        if (Schema::hasTable('akun')) {
            DB::table('akun')->where('kode_akun', '301')->update([
                'nama_akun' => 'Simpanan Pokok Anggota',
                'kategori' => 'ekuitas',
                'posisi_saldo' => 'kredit',
                'is_aktif' => true,
                'is_sistem' => true,
                'keterangan' => 'Modal tetap yang disetor anggota saat menjadi anggota koperasi.',
                'updated_at' => now(),
            ]);

            DB::table('akun')->where('kode_akun', '302')->update([
                'nama_akun' => 'Simpanan Wajib Anggota',
                'kategori' => 'ekuitas',
                'posisi_saldo' => 'kredit',
                'is_aktif' => true,
                'is_sistem' => true,
                'keterangan' => 'Modal tambahan yang disetor anggota secara berkala.',
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('jenis_simpanan')) {
            $isMysql = Schema::getConnection()->getDriverName() === 'mysql';
            $pokokPayload = [
                'aktif' => true,
                'updated_at' => now(),
            ];

            if (! $isMysql) {
                $pokokPayload['active_kategori_marker'] = JenisSimpanan::KATEGORI_POKOK;
            }

            DB::table('jenis_simpanan')
                ->where('kode', JenisSimpanan::KODE_SIMPANAN_POKOK)
                ->update($pokokPayload);

            DB::table('jenis_simpanan')
                ->where('kode', JenisSimpanan::KODE_SIMPANAN_WAJIB)
                ->update([
                    'nominal_default' => '100000.00',
                    'interval_bulan' => 3,
                    'keterangan' => 'Setoran wajib per penagihan tiga bulanan untuk menjaga likuiditas koperasi.',
                    'updated_at' => now(),
                ]);
        }
    }

    private function guardAmbiguousLegacyData(): void
    {
        if (! Schema::hasTable('simpanan') || ! Schema::hasTable('jenis_simpanan')) {
            return;
        }

        $hasPokokData = DB::table('simpanan')
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->whereNotIn('status', [Simpanan::STATUS_REVERSED, Simpanan::STATUS_REVERSED_DUE_TO_EXIT])
            ->exists();

        $hasWajibData = DB::table('simpanan')
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->whereNotIn('status', [Simpanan::STATUS_REVERSED, Simpanan::STATUS_REVERSED_DUE_TO_EXIT])
            ->exists();

        $hasActivePokok = DB::table('jenis_simpanan')
            ->where('kategori', JenisSimpanan::KATEGORI_POKOK)
            ->where('aktif', true)
            ->exists();

        $hasActiveWajib = DB::table('jenis_simpanan')
            ->where('kategori', JenisSimpanan::KATEGORI_WAJIB)
            ->where('aktif', true)
            ->exists();

        if ($hasPokokData && $hasWajibData && $hasActivePokok && $hasActiveWajib) {
            throw new RuntimeException(
                'Migration SP-7 dibatalkan: ditemukan data Simpanan Pokok dan Simpanan Wajib aktif yang sama-sama sudah dipakai. Sistem tidak akan menggabungkan/mapping otomatis; lakukan rekonsiliasi manual terlebih dahulu.'
            );
        }
    }

    private function syncFinalAccounts(): void
    {
        if (! Schema::hasTable('akun')) {
            return;
        }

        DB::table('akun')->updateOrInsert(
            ['kode_akun' => '301'],
            [
                'nama_akun' => 'Simpanan Wajib Anggota',
                'kategori' => 'ekuitas',
                'posisi_saldo' => 'kredit',
                'is_aktif' => true,
                'is_sistem' => true,
                'keterangan' => 'Simpanan Wajib final Rp10.000 satu kali per siklus keanggotaan.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('akun')->updateOrInsert(
            ['kode_akun' => '302'],
            [
                'nama_akun' => 'Simpanan Wajib Berkala Legacy',
                'kategori' => 'ekuitas',
                'posisi_saldo' => 'kredit',
                'is_aktif' => true,
                'is_sistem' => true,
                'keterangan' => 'Akun histori Wajib berkala lama; tidak digunakan untuk posting baru.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function extendSimpananForFinalWajib(): void
    {
        if (! Schema::hasTable('simpanan')) {
            return;
        }

        Schema::table('simpanan', function (Blueprint $table): void {
            if (! Schema::hasColumn('simpanan', 'simpanan_wajib_siklus_id')) {
                $marker = $table->unsignedBigInteger('simpanan_wajib_siklus_id')
                    ->nullable()
                    ->after('simpanan_pokok_siklus_id');

                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    $marker->storedAs("case when `kode_jenis_snapshot` = 'SIMPANAN_WAJIB' and `status` not in ('reversed', 'reversed_due_to_exit') then `siklus_keanggotaan_id` else null end");
                }

                $marker->unique('simpanan_wajib_siklus_unique');
            }
        });
    }

    private function syncFinalJenisSimpanan(): void
    {
        if (! Schema::hasTable('jenis_simpanan')) {
            return;
        }

        $now = now();
        $akunWajibId = Schema::hasTable('akun')
            ? DB::table('akun')->where('kode_akun', '301')->value('id')
            : null;
        $akunManasukaId = Schema::hasTable('akun')
            ? DB::table('akun')->where('kode_akun', '202')->value('id')
            : null;
        $isMysql = Schema::getConnection()->getDriverName() === 'mysql';

        $legacyInactivePayload = [
            'aktif' => false,
            'keterangan' => DB::raw("COALESCE(keterangan, 'Legacy; tidak digunakan sebagai master aktif SP-7.')"),
            'updated_at' => $now,
        ];

        if (! $isMysql) {
            $legacyInactivePayload['active_kategori_marker'] = null;
        }

        DB::table('jenis_simpanan')
            ->where(function ($query): void {
                $query->where('kategori', JenisSimpanan::KATEGORI_POKOK)
                    ->orWhere('kode', JenisSimpanan::KODE_SIMPANAN_POKOK)
                    ->orWhere('kategori', 'sukarela')
                    ->orWhere('kode', 'SIMPANAN_SUKARELA');
            })
            ->update($legacyInactivePayload);

        $wajibPayload = [
            'akun_id' => $akunWajibId,
            'kode' => JenisSimpanan::KODE_SIMPANAN_WAJIB,
            'kategori' => JenisSimpanan::KATEGORI_WAJIB,
            'interval_bulan' => null,
            'berlaku_mulai' => '2026-01-01',
            'nama_jenis' => 'Simpanan Wajib',
            'wajib' => true,
            'aktif' => true,
            'nominal_default' => '10000.00',
            'keterangan' => 'Dibayar Rp10.000 satu kali setiap siklus keanggotaan.',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (! $isMysql) {
            $wajibPayload['active_kategori_marker'] = JenisSimpanan::KATEGORI_WAJIB;
        }

        DB::table('jenis_simpanan')->updateOrInsert(
            ['kode' => JenisSimpanan::KODE_SIMPANAN_WAJIB],
            $wajibPayload
        );

        $manasukaPayload = [
            'akun_id' => $akunManasukaId,
            'kode' => JenisSimpanan::KODE_SIMPANAN_MANASUKA,
            'kategori' => JenisSimpanan::KATEGORI_MANASUKA,
            'interval_bulan' => null,
            'berlaku_mulai' => '2026-01-01',
            'nama_jenis' => 'Simpanan Manasuka',
            'wajib' => false,
            'aktif' => true,
            'nominal_default' => '0.00',
            'keterangan' => 'Tabungan pilihan Anggota yang dapat disetor dan ditarik.',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (! $isMysql) {
            $manasukaPayload['active_kategori_marker'] = JenisSimpanan::KATEGORI_MANASUKA;
        }

        DB::table('jenis_simpanan')->updateOrInsert(
            ['kode' => JenisSimpanan::KODE_SIMPANAN_MANASUKA],
            $manasukaPayload
        );
    }

    private function guardRollback(): void
    {
        if (! Schema::hasTable('simpanan')) {
            return;
        }

        $hasFinalWajib = DB::table('simpanan')
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->where('idempotency_key', 'like', 'simpanan-wajib:siklus:%')
            ->exists();

        if ($hasFinalWajib) {
            throw new RuntimeException('Rollback SP-7 dibatalkan: sudah ada transaksi Simpanan Wajib final per siklus. Gunakan migration koreksi, bukan rollback destruktif.');
        }
    }
};
