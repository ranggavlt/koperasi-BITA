<?php

namespace Database\Factories;

use App\Models\JadwalCicilanPinjaman;
use App\Models\Pinjaman;
use Illuminate\Database\Eloquent\Factories\Factory;

class JadwalCicilanPinjamanFactory extends Factory
{
    protected $model = JadwalCicilanPinjaman::class;

    public function definition(): array
    {
        return [
            'pinjaman_id' => Pinjaman::factory(),
            'angsuran_ke' => fake()->unique()->numberBetween(1, 12),
            'periode' => fake()->dateTimeBetween('now', '+12 months')->format('Y-m-01'),
            'nominal_pokok' => fake()->randomElement([100000, 250000, 500000]),
            'status' => JadwalCicilanPinjaman::STATUS_SCHEDULED,
            'metode_penyelesaian' => null,
            'paid_at' => null,
        ];
    }
}
