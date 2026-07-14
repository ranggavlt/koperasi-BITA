<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CicilanPinjaman extends Model
{
    public const METODE_POTONG_GAJI = 'potong_gaji';

    public const METODE_TUNAI = 'tunai';

    public const STATUS_BELUM_BAYAR = 'belum_bayar';

    public const STATUS_SUDAH_BAYAR = 'sudah_bayar';

    public const STATUS_REVERSED = 'reversed';

    protected $table = 'cicilan_pinjaman';

    protected $fillable = [
        'idempotency_key',
        'pinjaman_id',
        'anggota_id',
        'jadwal_cicilan_pinjaman_id',
        'reversal_transaksi_id',
        'jumlah_cicilan',
        'metode_pembayaran',
        'dompet_id',
        'periode',
        'status',
        'created_by',
        'tanggal_bayar',
    ];

    protected $casts = [
        'jumlah_cicilan' => 'decimal:2',
        'tanggal_bayar' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(function (CicilanPinjaman $_cicilan): void {
            throw new RuntimeException('Pembayaran cicilan tidak boleh dihapus permanen.');
        });

        static::updating(function (CicilanPinjaman $cicilan): void {
            if ($cicilan->getOriginal('status') === self::STATUS_SUDAH_BAYAR && $cicilan->isDirty()) {
                $dirty = array_keys($cicilan->getDirty());
                sort($dirty);
                $allowed = ['reversal_transaksi_id', 'status', 'updated_at'];
                sort($allowed);

                if ($dirty !== array_intersect($dirty, $allowed) || $cicilan->status !== self::STATUS_REVERSED) {
                    throw new RuntimeException('Pembayaran cicilan yang sudah berhasil tidak boleh diedit. Gunakan reversal.');
                }
            }
        });
    }

    public function pinjaman()
    {
        return $this->belongsTo(Pinjaman::class);
    }

    public function jadwal()
    {
        return $this->belongsTo(JadwalCicilanPinjaman::class, 'jadwal_cicilan_pinjaman_id');
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class);
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

    public function reversal()
    {
        return $this->belongsTo(ReversalTransaksi::class, 'reversal_transaksi_id');
    }
}
