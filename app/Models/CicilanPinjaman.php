<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\MutasiKas;

class CicilanPinjaman extends Model
{
    protected $table = 'cicilan_pinjaman';

    protected $fillable = [
        'pinjaman_id',
        'jumlah_cicilan',
        'periode',
        'status',
        'tanggal_bayar'
    ];

    public function pinjaman()
    {
        return $this->belongsTo(Pinjaman::class);
    }

    public function mutasiKas()
    {
        return $this->hasOne(MutasiKas::class, 'referensi_id')
            ->where('referensi_tipe', self::class);
    }
}
