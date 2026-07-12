<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsetMobil extends Model
{
    use HasFactory;

    protected $table = 'aset_mobil';

    protected $fillable = [
        'aset_koperasi_id',
        'plat_nomor',
        'tahun',
        'warna',
    ];

    public function aset()
    {
        return $this->belongsTo(AsetKoperasi::class, 'aset_koperasi_id');
    }
}
