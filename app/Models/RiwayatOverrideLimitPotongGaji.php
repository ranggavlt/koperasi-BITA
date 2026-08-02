<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatOverrideLimitPotongGaji extends Model
{
    public const JENIS_SET_OVERRIDE = 'set_override';

    public const JENIS_RESET_TO_GLOBAL = 'reset_to_global';

    public const JENIS_DISABLE_WASERBA = 'disable_waserba';

    public const JENIS_ENABLE_WASERBA = 'enable_waserba';

    public $timestamps = false;

    protected $table = 'riwayat_override_limit_potong_gaji';

    protected $fillable = [
        'override_limit_potong_gaji_anggota_id',
        'anggota_id',
        'jenis_perubahan',
        'nominal_sebelum',
        'nominal_sesudah',
        'kredit_waserba_sebelum',
        'kredit_waserba_sesudah',
        'alasan',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'nominal_sebelum' => 'decimal:2',
        'nominal_sesudah' => 'decimal:2',
        'kredit_waserba_sebelum' => 'boolean',
        'kredit_waserba_sesudah' => 'boolean',
        'changed_at' => 'datetime',
    ];

    public function override()
    {
        return $this->belongsTo(OverrideLimitPotongGajiAnggota::class, 'override_limit_potong_gaji_anggota_id');
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class);
    }
}
