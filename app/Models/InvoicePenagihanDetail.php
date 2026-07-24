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
    ];

    public function invoice()
    {
        return $this->belongsTo(InvoicePenagihan::class, 'invoice_penagihan_id');
    }

    public function referensi()
    {
        return $this->morphTo();
    }
}
