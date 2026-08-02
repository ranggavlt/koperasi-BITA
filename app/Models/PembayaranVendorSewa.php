<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PembayaranVendorSewa extends Model
{
    public const STATUS_PAID = 'paid';

    protected $table = 'pembayaran_vendor_sewa';
    protected $fillable = ['kode_pembayaran', 'sewa_type', 'sewa_id', 'dompet_id', 'metode_pembayaran', 'jumlah_bayar', 'vendor_nama_snapshot', 'vendor_kontak_snapshot', 'vendor_alamat_snapshot', 'tanggal_bayar', 'status', 'created_by', 'idempotency_key'];
    protected $casts = ['jumlah_bayar' => 'decimal:2', 'tanggal_bayar' => 'date'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Pembayaran vendor final tidak boleh dihapus. Gunakan reversal.'));
    }

    public function sewa() { return $this->morphTo(); }
    public function dompet() { return $this->belongsTo(DompetKoperasi::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function mutasiKas() { return $this->morphMany(MutasiKas::class, 'referensi', 'referensi_tipe', 'referensi_id'); }
    public function jurnal() { return $this->morphMany(JurnalUmum::class, 'referensi', 'referensi_tipe', 'referensi_id'); }
}
