<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class KonfigurasiManasukaRutin extends Model
{
    public const STATUS_AKTIF = 'aktif';

    public const STATUS_DIJEDA = 'dijeda';

    public const STATUS_DIHENTIKAN = 'dihentikan';

    protected $table = 'konfigurasi_manasuka_rutin';

    protected $fillable = [
        'anggota_id',
        'siklus_keanggotaan_id',
        'status',
        'nominal_snapshot',
        'berlaku_mulai',
        'alasan',
        'idempotency_key',
        'created_by',
    ];

    protected $casts = [
        'nominal_snapshot' => 'decimal:2',
        'berlaku_mulai' => 'date',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Konfigurasi Manasuka rutin bersifat immutable. Buat perubahan baru untuk periode berikutnya.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Konfigurasi Manasuka rutin tidak boleh dihapus permanen.');
        });
    }

    public static function statuses(): array
    {
        return [self::STATUS_AKTIF, self::STATUS_DIJEDA, self::STATUS_DIHENTIKAN];
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'anggota_id');
    }

    public function siklusKeanggotaan()
    {
        return $this->belongsTo(SiklusKeanggotaan::class, 'siklus_keanggotaan_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function simpanan()
    {
        return $this->hasMany(Simpanan::class, 'konfigurasi_manasuka_rutin_id');
    }
}
