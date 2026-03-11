<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\MutasiKas;

class Pinjaman extends Model
{
    protected $table = 'pinjaman';

    protected $fillable = [
        'karyawan_id',
        'jumlah_pinjaman',
        'bunga_persen',
        'tenor_bulan',
        'sisa_pinjaman',
        'status',
        'tanggal_pinjaman',
        'keterangan'
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function mutasiKas()
    {
        return $this->hasOne(MutasiKas::class, 'referensi_id')
            ->where('referensi_tipe', self::class);
    }
}
