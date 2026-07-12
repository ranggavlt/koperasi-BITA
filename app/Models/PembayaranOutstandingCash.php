<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PembayaranOutstandingCash extends Model
{
    public const STATUS_PAID = 'paid';

    protected $table = 'pembayaran_outstanding_cash';

    protected $fillable = [
        'kode_pembayaran',
        'source_type',
        'source_id',
        'anggota_id',
        'karyawan_id',
        'dompet_id',
        'nominal',
        'status',
        'paid_at',
        'created_by',
        'idempotency_key',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (PembayaranOutstandingCash $_payment): void {
            throw new RuntimeException('Pembayaran outstanding cash tidak boleh dihapus permanen.');
        });
    }

    public function source()
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class);
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function dompet()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_id');
    }

    public function mutasiKas()
    {
        return $this->hasOne(MutasiKas::class, 'referensi_id')
            ->where('referensi_tipe', self::class);
    }

    public function jurnal()
    {
        return $this->hasOne(JurnalUmum::class, 'referensi_id')
            ->where('referensi_tipe', self::class);
    }
}
