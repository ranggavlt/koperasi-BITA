<?php

namespace Database\Factories;

use App\Models\LimitPotongGajiAnggota;
use App\Models\PemakaianPotongGaji;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PemakaianPotongGajiFactory extends Factory
{
    protected $model = PemakaianPotongGaji::class;

    public function definition(): array
    {
        return [
            'limit_potong_gaji_anggota_id' => LimitPotongGajiAnggota::factory(),
            'kategori' => PemakaianPotongGaji::KATEGORI_POS,
            'source_type' => 'dummy_source',
            'source_id' => fake()->unique()->numberBetween(1, 999999),
            'jenis' => PemakaianPotongGaji::JENIS_PEMAKAIAN,
            'nominal' => fake()->randomElement([25000, 50000, 100000]),
            'status' => PemakaianPotongGaji::STATUS_CONSUMED,
            'idempotency_key' => (string) Str::uuid(),
            'occurred_at' => now(),
        ];
    }
}
