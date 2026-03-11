<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HutangReseller extends Model
{
    /** @use HasFactory<\Database\Factories\HutangResellerFactory> */
    use HasFactory;

    protected $table = 'hutang_reseller';

    protected $fillable = [
        'reseller_id',
        'detail_penjualan_id',
        'pembayaran_konsinyasi_id',
        'jumlah',
        'status',
        'tanggal',
        'tanggal_bayar',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tanggal_bayar' => 'date',
    ];

    public function reseller()
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }

    public function detailPenjualan()
    {
        return $this->belongsTo(DetailPenjualan::class, 'detail_penjualan_id');
    }

    public function pembayaranKonsinyasi()
    {
        return $this->belongsTo(PembayaranKonsinyasi::class, 'pembayaran_konsinyasi_id');
    }
}
