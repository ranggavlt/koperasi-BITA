<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class Penjualan extends Model
{
    use HasFactory;

    public const TIPE_ANGGOTA = 'anggota';

    public const TIPE_KARYAWAN = 'karyawan';

    public const TIPE_UMUM = 'umum';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'penjualan';

    protected $fillable = [
        'idempotency_key',
        'kode_transaksi',
        'tipe_pelanggan',
        'karyawan_id',
        'anggota_id',
        'tanggal_transaksi',
        'total_harga',
        'diskon',
        'grand_total',
        'status',
        'reversal_transaksi_id',
        'reversed_at',
        'reversed_by',
        'created_by',
    ];

    protected $casts = [
        'tanggal_transaksi' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Penjualan $_penjualan): void {
            throw new RuntimeException('Penjualan tidak boleh dihapus permanen. Gunakan koreksi/reversal pada tahap lanjutan.');
        });
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id');
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'anggota_id');
    }

    public function details()
    {
        return $this->hasMany(DetailPenjualan::class, 'penjualan_id');
    }

    public function mutasiKas()
    {
        return $this->hasOne(MutasiKas::class, 'referensi_id')
            ->where('referensi_tipe', self::class);
    }

    public function pembayaran()
    {
        return $this->hasOne(Pembayaran::class, 'penjualan_id');
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
