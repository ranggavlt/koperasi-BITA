<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePenagihan extends Model
{
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';

    protected $table = 'invoice_penagihan';

    protected $fillable = [
        'nomor_invoice',
        'perusahaan_id',
        'tanggal_invoice',
        'jatuh_tempo',
        'total_tagihan',
        'total_dibayar',
        'sisa_tagihan',
        'status',
        'created_by',
        'finalized_at',
        'idempotency_key',
    ];

    protected $casts = [
        'tanggal_invoice' => 'date',
        'jatuh_tempo' => 'date',
        'total_tagihan' => 'decimal:2',
        'total_dibayar' => 'decimal:2',
        'sisa_tagihan' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

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
        return $this->hasMany(PembayaranInvoicePenagihan::class, 'invoice_penagihan_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->status !== self::STATUS_PAID && $this->jatuh_tempo?->isPast()) {
            return 'Jatuh Tempo';
        }

        return match ($this->status) {
            self::STATUS_PARTIAL => 'Dibayar Sebagian',
            self::STATUS_PAID => 'Lunas',
            default => 'Belum Dibayar',
        };
    }
}
