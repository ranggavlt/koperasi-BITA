<?php

namespace Database\Factories;

use App\Models\Anggota;
use App\Models\PengurusKoperasi;
use Illuminate\Database\Eloquent\Factories\Factory;

class PengurusKoperasiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'anggota_id' => Anggota::factory(),
            'jabatan' => fake()->randomElement(PengurusKoperasi::JABATAN),
            'status' => PengurusKoperasi::STATUS_AKTIF,
        ];
    }
}
