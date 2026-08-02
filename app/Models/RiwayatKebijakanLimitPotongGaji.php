<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatKebijakanLimitPotongGaji extends Model
{
    public $timestamps = false;

    protected $table = 'riwayat_kebijakan_limit_potong_gaji';

    protected $fillable = [
        'kebijakan_limit_potong_gaji_id',
        'nominal_sebelum',
        'nominal_sesudah',
        'berlaku_mulai_periode',
        'alasan',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'nominal_sebelum' => 'decimal:2',
        'nominal_sesudah' => 'decimal:2',
        'berlaku_mulai_periode' => 'date',
        'changed_at' => 'datetime',
    ];

    public function kebijakan()
    {
        return $this->belongsTo(KebijakanLimitPotongGaji::class, 'kebijakan_limit_potong_gaji_id');
    }
}
