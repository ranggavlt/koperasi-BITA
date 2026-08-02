<?php

namespace Database\Factories;

use App\Models\Perusahaan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Karyawan>
 */
class KaryawanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'telepon' => fake()->numerify('08##########'),
            'jabatan' => fake()->randomElement(['Staf Administrasi', 'Staf Gudang', 'Kasir Toko']),
            'status_kerja' => 'aktif',
            'tanggal_berhenti' => null,
            'perusahaan_id' => fn () => Perusahaan::query()->firstOrCreate(
                ['kode' => 'BEE'],
                ['nama' => 'Bita Enarcon Engineering']
            )->id,
        ];
    }
}
