<?php

namespace Database\Seeders;

use App\Models\Akun;
use Illuminate\Database\Seeder;

class AkunSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('account_map.accounts', []) as $account) {
            Akun::query()->updateOrCreate(
                ['kode_akun' => (string) $account['kode_akun']],
                [
                    'nama_akun' => (string) $account['nama_akun'],
                    'kategori' => (string) $account['kategori'],
                    'posisi_saldo' => (string) $account['posisi_saldo'],
                    'is_aktif' => true,
                    'is_sistem' => true,
                    'keterangan' => $account['keterangan'] ?? null,
                ]
            );
        }
    }
}
