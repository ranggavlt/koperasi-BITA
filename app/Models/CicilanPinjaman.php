<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}