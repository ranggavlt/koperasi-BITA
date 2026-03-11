<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShuTransaksi extends Model
{
    use HasFactory;

    protected $table = 'shu_transaksi';

    protected $fillable = [
        'shu_koperasi_id',
        'jenis',
        'tanggal',
        'jumlah',
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jumlah' => 'decimal:2',
    ];

    public function shuKoperasi()
    {
        return $this->belongsTo(ShuKoperasi::class, 'shu_koperasi_id');
    }
}
