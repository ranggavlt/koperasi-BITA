<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reseller extends Model
{
    /** @use HasFactory<\Database\Factories\ResellerFactory> */
    use HasFactory;

    protected $table = 'reseller';

    protected $fillable = [
        'nama_reseller',
        'telepon',
        'alamat',
    ];

    public function produk()
    {
        return $this->hasMany(Produk::class, 'reseller_id');
    }

    public function hutangReseller()
    {
        return $this->hasMany(HutangReseller::class, 'reseller_id');
    }

    public function pembayaranKonsinyasi()
    {
        return $this->hasMany(PembayaranKonsinyasi::class, 'reseller_id');
    }
}
