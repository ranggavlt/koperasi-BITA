<?php

namespace Database\Factories;

use App\Models\Karyawan;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnggotaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'karyawan_id' => Karyawan::factory(),
            'nomor_anggota' => 'AGT-' . str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'tanggal_bergabung' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'alamat' => fake()->streetAddress(),
            'status' => 'aktif',
            'tanggal_nonaktif' => null,
            'plafon_pinjaman' => fake()->randomElement([0, 1000000, 2500000, 5000000]),
        ];
    }
}
