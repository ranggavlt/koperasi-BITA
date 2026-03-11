<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\MutasiKas;

class Penjualan extends Model
{
    use HasFactory;

    protected $table = 'penjualan';

    protected $fillable = [
        'kode_transaksi', 'karyawan_id', 'total_harga', 'diskon', 'grand_total'
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id');
    }

    public function details()
    {
        return $this->hasMany(DetailPenjualan::class, 'penjualan_id');
    }

    public function mutasiKas()
    {
        return $this->hasOne(MutasiKas::class, 'referensi_id')
            ->where('referensi_tipe', self::class);
    }
}
