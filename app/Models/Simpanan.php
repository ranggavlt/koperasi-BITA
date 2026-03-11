<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\MutasiKas;

class Simpanan extends Model
{
    protected $table = 'simpanan';

    protected $fillable = [
        'karyawan_id',
        'jenis_simpanan_id',
        'jumlah',
        'tanggal',
        'keterangan'
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function jenisSimpanan()
    {
        return $this->belongsTo(JenisSimpanan::class);
    }

    public function mutasiKas()
    {
        return $this->hasOne(MutasiKas::class, 'referensi_id')
            ->where('referensi_tipe', self::class);
    }
}
