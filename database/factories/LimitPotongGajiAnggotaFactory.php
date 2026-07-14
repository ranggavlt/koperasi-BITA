<?php

namespace Database\Factories;

use App\Models\Anggota;
use App\Models\LimitPotongGajiAnggota;
use App\Models\PeriodePotongGaji;
use Illuminate\Database\Eloquent\Factories\Factory;

class LimitPotongGajiAnggotaFactory extends Factory
{
    protected $model = LimitPotongGajiAnggota::class;

    public function definition(): array
    {
        return [
            'periode_potong_gaji_id' => PeriodePotongGaji::factory(),
            'anggota_id' => Anggota::factory(),
            'limit_nominal' => fake()->randomElement([0, 500000, 1000000, 2000000]),
            'status' => LimitPotongGajiAnggota::STATUS_DRAFT,
        ];
    }
}
