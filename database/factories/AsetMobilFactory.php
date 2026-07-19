<?php

namespace Database\Factories;

use App\Models\AsetKoperasi;
use App\Models\AsetMobil;
use Illuminate\Database\Eloquent\Factories\Factory;

class AsetMobilFactory extends Factory
{
    protected $model = AsetMobil::class;

    public function definition(): array
    {
        return [
            'aset_koperasi_id' => AsetKoperasi::factory()->mobil(),
            'plat_nomor' => strtoupper(fake()->bothify('? #### ??')),
            'tahun' => fake()->numberBetween(2015, now(config('app.timezone', 'Asia/Jakarta'))->year),
            'warna' => fake()->randomElement(['Hitam', 'Putih', 'Merah', 'Abu-abu']),
            'tarif_sewa_harian' => fake()->numberBetween(250, 900) * 1000,
        ];
    }
}
