<?php

namespace Database\Factories;

use App\Models\Anggota;
use App\Models\SiklusKeanggotaan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<SiklusKeanggotaan>
 */
class SiklusKeanggotaanFactory extends Factory
{
    protected $model = SiklusKeanggotaan::class;

    public function definition(): array
    {
        $anggota = Anggota::factory()->create();

        return [
            'anggota_id' => $anggota->id,
            'siklus_ke' => 1,
            'tanggal_mulai' => $anggota->tanggal_bergabung,
            'tanggal_selesai' => null,
            'status' => SiklusKeanggotaan::STATUS_ACTIVE,
            'active_anggota_id' => DB::connection()->getDriverName() === 'mysql' ? null : $anggota->id,
            'alasan_selesai' => null,
            'created_by' => null,
            'closed_by' => null,
        ];
    }
}
