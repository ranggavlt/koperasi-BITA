<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PembayaranVendorSewa extends Model
{
    public const STATUS_PAID = 'paid';
    public const STATUS_DIBAYAR = 'dibayar';
    public const STATUS_MENUNGGU_PENGEMBALIAN = 'menunggu_pengembalian';
    public const STATUS_DIKEMBALIKAN = 'dikembalikan';

    protected $table = 'pembayaran_vendor_sewa';

    protected $fillable = [
        'kode_pembayaran',
        'sewa_type', 'sewa_id', 'dompet_id', 'metode', 'jumlah', 'tanggal_bayar',
        'metode_pembayaran', 'jumlah_bayar', 'vendor_nama_snapshot',
        'vendor_kontak_snapshot', 'vendor_alamat_snapshot',
        'nomor_referensi', 'status', 'alasan_pengembalian', 'diminta_kembali_pada',
        'diminta_kembali_oleh', 'dikembalikan_pada', 'dikembalikan_oleh',
        'created_by', 'idempotency_key',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
        'jumlah_bayar' => 'decimal:2',
        'tanggal_bayar' => 'date',
        'diminta_kembali_pada' => 'datetime',
        'dikembalikan_pada' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Pembayaran vendor final tidak boleh dihapus.'));
    }

    public function sewa()
    {
        return $this->morphTo(__FUNCTION__, 'sewa_type', 'sewa_id');
    }

    public function dompet()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mutasiKas()
    {
        return $this->morphMany(MutasiKas::class, 'referensi', 'referensi_tipe', 'referensi_id');
    }

    public function jurnal()
    {
        return $this->morphMany(JurnalUmum::class, 'referensi', 'referensi_tipe', 'referensi_id');
    }
}
