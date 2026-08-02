<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Akun;
use App\Models\JenisSimpanan;

class JenisSimpananSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $akunWajib = Akun::query()
            ->where('kode_akun', config('account_map.accounts.simpanan_wajib.kode_akun', '301'))
            ->value('id');
        $akunManasuka = Akun::query()
            ->where('kode_akun', config('account_map.accounts.simpanan_manasuka.kode_akun', '202'))
            ->value('id');

        JenisSimpanan::query()
            ->where(function ($query): void {
                $query->where('kategori', JenisSimpanan::KATEGORI_POKOK)
                    ->orWhere('kode', JenisSimpanan::KODE_SIMPANAN_POKOK);
            })
            ->update(['aktif' => false, 'interval_bulan' => null]);

        JenisSimpanan::updateOrCreate(
            ['kode' => JenisSimpanan::KODE_SIMPANAN_WAJIB],
            [
                'akun_id' => $akunWajib,
                'kategori' => JenisSimpanan::KATEGORI_WAJIB,
                'nama_jenis' => 'Simpanan Wajib',
                'wajib' => true,
                'aktif' => true,
                'nominal_default' => '10000.00',
                'interval_bulan' => null,
                'berlaku_mulai' => '2026-01-01',
                'keterangan' => 'Dibayar Rp10.000 satu kali setiap siklus keanggotaan.',
            ]
        );

        JenisSimpanan::updateOrCreate(
            ['kode' => JenisSimpanan::KODE_SIMPANAN_MANASUKA],
            [
                'akun_id' => $akunManasuka,
                'kategori' => JenisSimpanan::KATEGORI_MANASUKA,
                'nama_jenis' => 'Simpanan Manasuka',
                'wajib' => false,
                'aktif' => true,
                'nominal_default' => '0.00',
                'interval_bulan' => null,
                'berlaku_mulai' => '2026-01-01',
                'keterangan' => 'Tabungan pilihan Anggota yang dapat disetor dan ditarik.',
            ]
        );
    }
}
