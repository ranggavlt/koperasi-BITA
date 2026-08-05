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

        $ids = \App\Models\Perusahaan::query()->whereIn('kode', ['BEE', 'BBS', 'BKM'])->orderBy('kode')->pluck('id')->values();
        if ($ids->count() === 3) {
            \App\Models\Karyawan::query()->whereNull('perusahaan_id')->orderBy('id')->get()
                ->each(fn ($employee, $index) => $employee->update(['perusahaan_id' => $ids[$index % 3]]));
        }
    }
}
