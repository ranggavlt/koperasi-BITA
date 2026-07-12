<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class AlokasiKreditPotongGaji extends Model
{
    public const STATUS_APPLIED = 'applied';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'alokasi_kredit_potong_gaji';

    protected $fillable = [
        'kredit_potong_gaji_anggota_id',
        'limit_potong_gaji_anggota_id',
        'nominal_dialokasikan',
        'nominal_diterapkan',
        'status',
        'applied_at',
        'created_by',
        'idempotency_key',
    ];

    protected $casts = [
        'nominal_dialokasikan' => 'decimal:2',
        'nominal_diterapkan' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (AlokasiKreditPotongGaji $_alokasi): void {
            throw new RuntimeException('Alokasi kredit potong gaji tidak boleh dihapus permanen.');
        });
    }

    public function kredit()
    {
        return $this->belongsTo(KreditPotongGajiAnggota::class, 'kredit_potong_gaji_anggota_id');
    }

    public function limit()
    {
        return $this->belongsTo(LimitPotongGajiAnggota::class, 'limit_potong_gaji_anggota_id');
    }
}
