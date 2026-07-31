<?php

namespace Database\Factories;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\SaldoSimpananManasuka;
use App\Models\SiklusKeanggotaan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaldoSimpananManasuka>
 */
class SaldoSimpananManasukaFactory extends Factory
{
    protected $model = SaldoSimpananManasuka::class;

    public function definition(): array
    {
        return [
            'anggota_id' => Anggota::factory(),
            'siklus_keanggotaan_id' => fn (array $attributes) => SiklusKeanggotaan::factory()
                ->create(['anggota_id' => $attributes['anggota_id']])
                ->id,
            'jenis_simpanan_id' => fn () => JenisSimpanan::query()
                ->where('kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
                ->value('id')
                ?: JenisSimpanan::factory()->manasuka()->create()->id,
            'saldo' => '0.00',
        ];
    }
}
