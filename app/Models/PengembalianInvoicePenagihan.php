<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PengembalianInvoicePenagihan extends Model
{
    protected $table = 'pengembalian_invoice_penagihan';

    protected $fillable = [
        'invoice_penagihan_detail_id', 'dompet_id', 'metode', 'jumlah',
        'tanggal_pengembalian', 'nomor_referensi', 'alasan', 'created_by', 'idempotency_key',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
        'tanggal_pengembalian' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Pengembalian invoice final tidak boleh dihapus.'));
    }

    public function detail()
    {
        return $this->belongsTo(InvoicePenagihanDetail::class, 'invoice_penagihan_detail_id');
    }

    public function dompet()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_id');
    }
}
