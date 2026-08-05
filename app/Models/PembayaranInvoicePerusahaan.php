<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PembayaranInvoicePerusahaan extends Model
{
    public const STATUS_PAID = 'paid';

    protected $table = 'pembayaran_invoice_perusahaan';
    protected $fillable = ['kode_pembayaran', 'invoice_penagihan_id', 'dompet_id', 'metode_pembayaran', 'jumlah_bayar', 'tanggal_bayar', 'nomor_referensi', 'status', 'created_by', 'idempotency_key'];
    protected $casts = ['jumlah_bayar' => 'decimal:2', 'tanggal_bayar' => 'date'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Pembayaran perusahaan final tidak boleh dihapus. Gunakan reversal.'));
    }

    public function invoice() { return $this->belongsTo(InvoicePenagihan::class, 'invoice_penagihan_id'); }
    public function dompet() { return $this->belongsTo(DompetKoperasi::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function mutasiKas() { return $this->morphMany(MutasiKas::class, 'referensi', 'referensi_tipe', 'referensi_id'); }
    public function jurnal() { return $this->morphMany(JurnalUmum::class, 'referensi', 'referensi_tipe', 'referensi_id'); }
}
