<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SiklusKeanggotaan extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'siklus_keanggotaan';

    protected $fillable = [
        'anggota_id',
        'siklus_ke',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'active_anggota_id',
        'alasan_selesai',
        'created_by',
        'closed_by',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (SiklusKeanggotaan $siklus): void {
            if (DB::connection()->getDriverName() !== 'mysql') {
                $siklus->active_anggota_id = $siklus->status === self::STATUS_ACTIVE
                    ? $siklus->anggota_id
                    : null;
            }
        });

        static::deleting(function (): void {
            throw new RuntimeException('Siklus keanggotaan tidak boleh dihapus permanen.');
        });
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'anggota_id');
    }

    public function penyelesaian()
    {
        return $this->hasOne(PenyelesaianKeanggotaan::class, 'siklus_keanggotaan_id');
    }

    public function simpanan()
    {
        return $this->hasMany(Simpanan::class, 'siklus_keanggotaan_id');
    }

    public function saldoSimpananSukarela()
    {
        return $this->hasMany(SaldoSimpananSukarela::class, 'siklus_keanggotaan_id');
    }
}
