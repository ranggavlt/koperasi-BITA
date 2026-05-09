<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JurnalUmumDetail extends Model
{
    protected $table = 'jurnal_umum_detail';

    protected $fillable = [
        'jurnal_umum_id',
        'akun_kode',
        'akun_nama',
        'debit',
        'kredit',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'kredit' => 'decimal:2',
    ];

    public function jurnal()
    {
        return $this->belongsTo(JurnalUmum::class, 'jurnal_umum_id');
    }
}

