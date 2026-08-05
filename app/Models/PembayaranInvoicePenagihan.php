<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PembayaranInvoicePenagihan extends Model
{
    protected $table = 'pembayaran_invoice_penagihan';

    protected $fillable = [
        'invoice_penagihan_id',
        'dompet_id',
        'metode',
        'jumlah',
        'tanggal_bayar',
        'nomor_referensi',
        'created_by',
        'idempotency_key',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
        'tanggal_bayar' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Pembayaran invoice final tidak boleh dihapus.'));
    }

    public function invoice()
    {
        return $this->belongsTo(InvoicePenagihan::class, 'invoice_penagihan_id');
    }

    public function dompet()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations()
    {
        return $this->hasMany(AlokasiPembayaranInvoice::class, 'pembayaran_invoice_id');
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
