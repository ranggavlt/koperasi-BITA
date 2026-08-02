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
        'referensi_type',
        'referensi_id',
        'kode_sewa_snapshot',
        'vendor_nama_snapshot',
        'harga_vendor_snapshot',
        'margin_snapshot',
    ];

    protected $casts = ['nominal' => 'decimal:2', 'harga_vendor_snapshot' => 'decimal:2', 'margin_snapshot' => 'decimal:2'];

    public function invoice()
    {
        return $this->belongsTo(InvoicePenagihan::class, 'invoice_penagihan_id');
    }

    public function referensi()
    {
        return $this->morphTo();
    }
}
