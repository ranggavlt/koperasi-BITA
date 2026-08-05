<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePenagihanDetail extends Model
{
    protected $table = 'invoice_penagihan_detail';

    protected $fillable = [
        'invoice_penagihan_id',
        'deskripsi',
        'nominal',
        'status',
        'total_dikembalikan',
        'referensi_type',
        'referensi_id',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'total_dikembalikan' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(InvoicePenagihan::class, 'invoice_penagihan_id');
    }

    public function referensi()
    {
        return $this->morphTo();
    }

    public function allocations()
    {
        return $this->hasMany(AlokasiPembayaranInvoice::class, 'invoice_penagihan_detail_id');
    }

    public function pengembalian()
    {
        return $this->hasMany(PengembalianInvoicePenagihan::class, 'invoice_penagihan_detail_id');
    }

    public function getTotalDibayarAttribute(): float
    {
        return (float) $this->allocations()->sum('jumlah');
    }

    public function getTotalDikembalikanAttribute(): float
    {
        return (float) $this->pengembalian()->sum('jumlah');
    }
}
