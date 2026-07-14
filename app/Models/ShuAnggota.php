<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShuAnggota extends Model
{
    use HasFactory;

    protected $table = 'shu_anggota';

    protected $fillable = [
        'shu_koperasi_id',
        'karyawan_id',
        'anggota_id',
        'total_simpanan',
        'total_transaksi_usaha',
        'nominal_jasa_modal',
        'nominal_jasa_usaha',
        'nominal_shu',
    ];

    protected $casts = [
        'total_simpanan' => 'decimal:2',
        'total_transaksi_usaha' => 'decimal:2',
        'nominal_jasa_modal' => 'decimal:2',
        'nominal_jasa_usaha' => 'decimal:2',
        'nominal_shu' => 'decimal:2',
    ];

    public function shuKoperasi()
    {
        return $this->belongsTo(ShuKoperasi::class, 'shu_koperasi_id');
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class);
    }
}
