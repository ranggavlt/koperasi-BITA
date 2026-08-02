<?php

namespace Database\Factories;

use App\Models\Akun;
use App\Models\JenisSimpanan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JenisSimpanan>
 */
class JenisSimpananFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $akunId = Akun::query()
            ->where('kode_akun', config('account_map.accounts.simpanan_manasuka.kode_akun'))
            ->value('id');

        return [
            'akun_id' => $akunId,
            'kode' => null,
            'kategori' => null,
            'nama_jenis' => 'Simpanan Tambahan ' . fake()->unique()->numberBetween(100, 999),
            'wajib' => false,
            'aktif' => true,
            'interval_bulan' => null,
            'berlaku_mulai' => now(config('app.timezone', 'Asia/Jakarta'))->toDateString(),
            'nominal_default' => 0,
            'keterangan' => fake()->sentence(),
        ];
    }

    public function manasuka(): static
    {
        return $this->state(fn () => [
            'akun_id' => Akun::query()->where('kode_akun', config('account_map.accounts.simpanan_manasuka.kode_akun'))->value('id'),
            'kode' => JenisSimpanan::KODE_SIMPANAN_MANASUKA,
            'kategori' => JenisSimpanan::KATEGORI_MANASUKA,
            'nama_jenis' => 'Simpanan Manasuka',
            'wajib' => false,
            'aktif' => true,
            'interval_bulan' => null,
            'nominal_default' => 0,
        ]);
    }
}
