<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatLimitPotongGaji extends Model
{
    public $timestamps = false;

    protected $table = 'riwayat_limit_potong_gaji';

    protected $fillable = [
        'limit_potong_gaji_anggota_id',
        'nominal_sebelum',
        'nominal_sesudah',
        'alasan',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'nominal_sebelum' => 'decimal:2',
        'nominal_sesudah' => 'decimal:2',
        'changed_at' => 'datetime',
    ];

    public function limit()
    {
        return $this->belongsTo(LimitPotongGajiAnggota::class, 'limit_potong_gaji_anggota_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
