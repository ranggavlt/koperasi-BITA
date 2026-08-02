<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ShuConfig extends Model
{
    protected $fillable = [
        'persen_pembina',
        'persen_pengawas',
        'persen_pengurus',
        'persen_anggota',
        'persen_dana_sosial',
        'persen_dana_cadangan',
        'persen_dana_pendidikan',
        'persen_jasa_modal',
        'persen_jasa_usaha',
        'status_persetujuan',
        'berlaku_mulai',
        'dasar_persetujuan',
        'approved_by',
        'approved_at',
    ];

    protected $casts = ['berlaku_mulai' => 'date', 'approved_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Konfigurasi SHU merupakan histori dan tidak boleh dihapus.'));
    }
}
