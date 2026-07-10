<?php

namespace App\Services;

use App\Models\Akun;
use LogicException;
use RuntimeException;

class AkunResolver
{
    public function resolve(string $key): Akun
    {
        $definition = config("account_map.accounts.{$key}");

        if (! is_array($definition) || empty($definition['kode_akun'])) {
            throw new LogicException("Pemetaan akun [{$key}] belum didefinisikan di config/account_map.php.");
        }

        $akun = Akun::query()
            ->where('kode_akun', (string) $definition['kode_akun'])
            ->first();

        if (! $akun) {
            throw new RuntimeException(
                "Akun sistem [{$key}] dengan kode {$definition['kode_akun']} belum tersedia. Jalankan migration/seeder COA."
            );
        }

        if (! $akun->is_aktif) {
            throw new RuntimeException("Akun {$akun->kode_akun} - {$akun->nama_akun} sedang tidak aktif.");
        }

        foreach (['nama_akun', 'kategori', 'posisi_saldo'] as $field) {
            if ((string) $akun->{$field} !== (string) ($definition[$field] ?? '')) {
                throw new RuntimeException(
                    "Master akun {$akun->kode_akun} tidak sesuai dengan config/account_map.php pada field {$field}."
                );
            }
        }

        return $akun;
    }

    public function posting(string $path): Akun
    {
        $key = config("account_map.postings.{$path}");

        if (! is_string($key) || $key === '') {
            throw new LogicException("Pemetaan posting [{$path}] belum didefinisikan di config/account_map.php.");
        }

        return $this->resolve($key);
    }

    /**
     * @return array{akun_id:int, akun_kode:string, akun_nama:string, debit:float, kredit:float}
     */
    public function line(Akun $akun, string $posisi, float|int|string $jumlah): array
    {
        $nominal = round((float) $jumlah, 2);

        if (! is_finite($nominal) || $nominal <= 0) {
            throw new RuntimeException('Nominal baris jurnal harus lebih besar dari nol.');
        }

        if (! in_array($posisi, ['debit', 'kredit'], true)) {
            throw new LogicException('Posisi jurnal hanya boleh debit atau kredit.');
        }

        return [
            'akun_id' => $akun->id,
            'akun_kode' => $akun->kode_akun,
            'akun_nama' => $akun->nama_akun,
            'debit' => $posisi === 'debit' ? $nominal : 0,
            'kredit' => $posisi === 'kredit' ? $nominal : 0,
        ];
    }
}
