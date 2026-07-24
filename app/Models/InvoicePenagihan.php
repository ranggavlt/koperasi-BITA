<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePenagihan extends Model
{
    protected $table = 'invoice_penagihan';

    protected $fillable = [
        'nomor_invoice',
        'perusahaan_id',
        'tanggal_invoice',
        'jatuh_tempo',
        'total_tagihan',
        'status',
    ];

    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class, 'perusahaan_id');
    }

    public function detail()
    {
        return $this->hasMany(InvoicePenagihanDetail::class, 'invoice_penagihan_id');
    }
}
