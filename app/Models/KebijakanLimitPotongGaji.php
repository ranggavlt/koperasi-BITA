<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KebijakanLimitPotongGaji extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'kebijakan_limit_potong_gaji';

    protected $fillable = [
        'nominal_limit',
        'status',
        'berlaku_mulai_periode',
        'berlaku_sampai_periode',
        'alasan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'nominal_limit' => 'decimal:2',
        'berlaku_mulai_periode' => 'date',
        'berlaku_sampai_periode' => 'date',
    ];

    public function riwayat()
    {
        return $this->hasMany(RiwayatKebijakanLimitPotongGaji::class, 'kebijakan_limit_potong_gaji_id');
    }
}
