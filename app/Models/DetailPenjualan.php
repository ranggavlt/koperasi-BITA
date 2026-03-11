<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailPenjualan extends Model
{
    use HasFactory;

    protected $table = 'detail_penjualan';

    protected $fillable = [
        'penjualan_id', 'produk_id', 'qty', 'harga', 'subtotal',
        'konsinyasi', 'reseller_id', 'harga_setor', 'subtotal_setor'
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'penjualan_id');
    }

    public function reseller()
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }

    public function hutangReseller()
    {
        return $this->hasOne(HutangReseller::class, 'detail_penjualan_id');
    }
}
