<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PembayaranKonsinyasi extends Model
{
    protected $table = 'pembayaran_konsinyasi';

    protected $fillable = [
        'kode_pembayaran',
        'reseller_id',
        'dompet_id',
        'tanggal_bayar',
        'total_qty',
        'total_jual',
        'total_bayar',
        'total_margin',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_bayar' => 'date',
    ];

    public function reseller()
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }

    public function dompet()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_id');
    }

    public function hutangReseller()
    {
        return $this->hasMany(HutangReseller::class, 'pembayaran_konsinyasi_id');
    }

    public function mutasiKas()
    {
        return $this->hasOne(MutasiKas::class, 'referensi_id')
            ->where('referensi_tipe', self::class);
    }
}
