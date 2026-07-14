<?php

namespace Database\Factories;

use App\Models\PeriodePotongGaji;
use Illuminate\Database\Eloquent\Factories\Factory;

class PeriodePotongGajiFactory extends Factory
{
    protected $model = PeriodePotongGaji::class;

    public function definition(): array
    {
        return [
            'periode' => fake()->dateTimeBetween('-6 months', '+6 months')->format('Y-m-01'),
            'status' => PeriodePotongGaji::STATUS_DRAFT,
        ];
    }
}
