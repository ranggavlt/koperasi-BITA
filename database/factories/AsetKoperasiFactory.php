<?php

namespace Database\Factories;

use App\Models\AsetKoperasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AsetKoperasiFactory extends Factory
{
    protected $model = AsetKoperasi::class;

    public function definition(): array
    {
        return [
            'kode_aset' => 'MBL-' . str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'jenis_aset' => AsetKoperasi::JENIS_MOBIL,
            'merek' => fake()->randomElement(['Toyota', 'Daihatsu', 'Epson', 'Canon']),
            'model' => fake()->word(),
            'status' => AsetKoperasi::STATUS_TERSEDIA,
            'keterangan' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
            'updated_by' => null,
            'nonaktif_at' => null,
            'nonaktif_by' => null,
        ];
    }

    public function mobil(): static
    {
        return $this->state(fn () => [
            'kode_aset' => 'MBL-' . str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'jenis_aset' => AsetKoperasi::JENIS_MOBIL,
        ]);
    }

    public function printer(): static
    {
        return $this->state(fn () => [
            'kode_aset' => 'PRT-' . str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'jenis_aset' => AsetKoperasi::JENIS_PRINTER,
        ]);
    }
}
