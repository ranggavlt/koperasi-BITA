<?php

namespace Database\Factories;

use App\Models\Anggota;
use App\Models\JadwalSimpananWajib;
use App\Models\JenisSimpanan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JadwalSimpananWajib>
 */
class JadwalSimpananWajibFactory extends Factory
{
    protected $model = JadwalSimpananWajib::class;

    public function definition(): array
    {
        return [
            'kode_tagihan' => 'SWJ-' . now(config('app.timezone', 'Asia/Jakarta'))->format('Ym') . '-' . str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'anggota_id' => Anggota::factory(),
            'jenis_simpanan_id' => JenisSimpanan::factory(),
            'periode' => now(config('app.timezone', 'Asia/Jakarta'))->startOfMonth()->toDateString(),
            'nominal_snapshot' => '100000.00',
            'interval_bulan_snapshot' => 3,
            'kode_jenis_snapshot' => JenisSimpanan::KODE_SIMPANAN_WAJIB,
            'nama_jenis_snapshot' => 'Simpanan Wajib',
            'status' => JadwalSimpananWajib::STATUS_OUTSTANDING,
        ];
    }
}
