<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produk extends Model
{
    /** @use HasFactory<\Database\Factories\ProdukFactory> */
    use HasFactory;

    protected $table = 'produk';

    protected $fillable = [
        'nama_produk',
        'foto',
        'kategori_id',
        'harga_beli',
        'harga_jual',
        'stok',
        'konsinyasi',
        'reseller_id',
        'harga_setor',
    ];

    public function kategori()
    {
        return $this->belongsTo(KategoriProduk::class, 'kategori_id');
    }

    public function reseller()
{
    return $this->belongsTo(Reseller::class, 'reseller_id');
}
}
