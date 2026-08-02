<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OverrideLimitPotongGajiAnggota extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'override_limit_potong_gaji_anggota';

    protected $fillable = [
        'anggota_id',
        'nominal_override',
        'status',
        'berlaku_mulai_periode',
        'alasan_limit_override',
        'override_created_by',
        'override_updated_by',
        'override_updated_at',
        'reset_by',
        'reset_at',
        'reset_reason',
        'kredit_waserba_enabled',
        'kredit_waserba_disabled_by',
        'kredit_waserba_disabled_at',
        'kredit_waserba_disabled_reason',
        'kredit_waserba_enabled_by',
        'kredit_waserba_enabled_at',
        'kredit_waserba_enabled_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'nominal_override' => 'decimal:2',
        'berlaku_mulai_periode' => 'date',
        'override_updated_at' => 'datetime',
        'reset_at' => 'datetime',
        'kredit_waserba_enabled' => 'boolean',
        'kredit_waserba_disabled_at' => 'datetime',
        'kredit_waserba_enabled_at' => 'datetime',
    ];

    public function anggota()
    {
        return $this->belongsTo(Anggota::class);
    }

    public function riwayat()
    {
        return $this->hasMany(RiwayatOverrideLimitPotongGaji::class, 'override_limit_potong_gaji_anggota_id');
    }
}
