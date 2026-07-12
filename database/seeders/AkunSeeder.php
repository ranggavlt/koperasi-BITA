<?php

namespace Database\Seeders;

use App\Models\Akun;
use App\Models\RiwayatAkunBebanOperasional;
use Illuminate\Database\Seeder;

class AkunSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('account_map.accounts', []) as $account) {
            $akun = Akun::query()->updateOrCreate(
                ['kode_akun' => (string) $account['kode_akun']],
                [
                    'nama_akun' => (string) $account['nama_akun'],
                    'kategori' => (string) $account['kategori'],
                    'posisi_saldo' => (string) $account['posisi_saldo'],
                    'is_aktif' => true,
                    'is_sistem' => true,
                    'is_beban_operasional' => (bool) ($account['is_beban_operasional'] ?? false),
                    'keterangan' => $account['keterangan'] ?? null,
                ]
            );

            if ((bool) ($account['is_beban_operasional'] ?? false)) {
                RiwayatAkunBebanOperasional::query()->firstOrCreate(
                    [
                        'akun_id' => $akun->id,
                        'nilai_sesudah' => true,
                        'alasan' => 'Eligibility awal dari account_map seeder.',
                    ],
                    [
                        'nilai_sebelum' => false,
                        'changed_by' => null,
                        'changed_at' => now(),
                    ]
                );
            }
        }
    }
}
