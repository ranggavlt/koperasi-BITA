<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JurnalUmum extends Model
{
    protected $table = 'jurnal_umum';

    protected $fillable = [
        'tanggal',
        'nomor_bukti',
        'keterangan',
        'referensi_tipe',
        'referensi_id',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function details()
    {
        return $this->hasMany(JurnalUmumDetail::class, 'jurnal_umum_id');
    }
}

