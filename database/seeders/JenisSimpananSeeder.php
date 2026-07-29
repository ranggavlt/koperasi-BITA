<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\JenisSimpanan;

class JenisSimpananSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        JenisSimpanan::create([
            'kode' => JenisSimpanan::KODE_SIMPANAN_MANASUKA,
            'kategori' => JenisSimpanan::KATEGORI_MANASUKA,
            'nama_jenis' => 'Simpanan Manasuka',
            'wajib' => false,
            'keterangan' => 'Setoran manasuka anggota di luar kewajiban rutin.',
        ]);
    }
}
