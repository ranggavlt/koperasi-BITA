<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MutasiKas extends Model
{
    protected $table = 'mutasi_kas';

    protected $fillable = [
        'dompet_id',
        'tipe',
        'jumlah',
        'keterangan',
        'referensi_type',
        'referensi_id',
        'tanggal'
    ];

    public function dompet()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_id');
    }
}