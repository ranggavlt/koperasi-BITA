<?php

namespace Database\Factories;

use App\Models\AsetKoperasi;
use App\Models\AsetPrinter;
use Illuminate\Database\Eloquent\Factories\Factory;

class AsetPrinterFactory extends Factory
{
    protected $model = AsetPrinter::class;

    public function definition(): array
    {
        return [
            'aset_koperasi_id' => AsetKoperasi::factory()->printer(),
            'nomor_seri' => strtoupper(fake()->bothify('PRN-####-????')),
            'lokasi' => fake()->randomElement(['Kantor Koperasi', 'Area Kasir', 'Ruang Admin']),
        ];
    }
}
