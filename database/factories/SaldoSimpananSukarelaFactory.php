<?php

namespace Database\Factories;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\SaldoSimpananSukarela;
use App\Models\SiklusKeanggotaan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaldoSimpananSukarela>
 */
class SaldoSimpananSukarelaFactory extends Factory
{
    protected $model = SaldoSimpananSukarela::class;

    public function definition(): array
    {
        return [
            'anggota_id' => Anggota::factory(),
            'siklus_keanggotaan_id' => fn (array $attributes) => SiklusKeanggotaan::factory()
                ->create(['anggota_id' => $attributes['anggota_id']])
                ->id,
            'jenis_simpanan_id' => fn () => JenisSimpanan::query()
                ->where('kode', JenisSimpanan::KODE_SIMPANAN_SUKARELA)
                ->value('id')
                ?: JenisSimpanan::factory()->sukarela()->create()->id,
            'saldo' => '0.00',
        ];
    }
}
