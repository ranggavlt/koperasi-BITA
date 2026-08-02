<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class InvoicePenagihan extends Model
{
    protected $table = 'invoice_penagihan';

    protected $fillable = [
        'nomor_invoice',
        'perusahaan_id',
        'tanggal_invoice',
        'jatuh_tempo',
        'total_tagihan',
        'jumlah_dibayar',
        'sisa_tagihan',
        'status',
        'kode_perusahaan_snapshot',
        'nama_perusahaan_snapshot',
        'finalized_at',
        'created_by',
        'idempotency_key',
    ];

    protected $casts = [
        'tanggal_invoice' => 'date',
        'jatuh_tempo' => 'date',
        'total_tagihan' => 'decimal:2',
        'jumlah_dibayar' => 'decimal:2',
        'sisa_tagihan' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Invoice final tidak boleh dihapus. Gunakan pembatalan/reversal.'));
    }

    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class, 'perusahaan_id');
    }

    public function detail()
    {
        return $this->hasMany(InvoicePenagihanDetail::class, 'invoice_penagihan_id');
    }

    public function pembayaran()
    {
        return $this->hasMany(PembayaranInvoicePerusahaan::class, 'invoice_penagihan_id');
    }

    public function jurnal()
    {
        return $this->morphMany(JurnalUmum::class, 'referensi', 'referensi_tipe', 'referensi_id');
    }
}
