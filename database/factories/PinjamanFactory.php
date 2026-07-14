<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\pinjaman>
 */
class PinjamanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jumlah_pinjaman' => 1000000,
            'plafon_pinjaman_snapshot' => 1000000,
            'bunga_persen' => 0,
            'tenor_bulan' => 10,
            'sisa_pinjaman' => 1000000,
            'status' => 'aktif',
            'tanggal_pinjaman' => now()->toDateString(),
        ];
    }
}
