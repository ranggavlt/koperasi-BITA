<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PerusahaanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            ['kode' => 'BEE', 'nama' => 'Bita Enarcon Engineering'],
            ['kode' => 'BBS', 'nama' => 'Bita Bina Semesta'],
            ['kode' => 'BKM', 'nama' => 'Bamko Karsa Mandiri'],
        ];

        foreach ($companies as $c) {
            \App\Models\Perusahaan::updateOrCreate(
                ['kode' => $c['kode']],
                ['nama' => $c['nama']]
            );
        }
    }
}
