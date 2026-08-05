<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlokasiPembayaranInvoice extends Model
{
    protected $table = 'alokasi_pembayaran_invoice';

    protected $fillable = [
        'pembayaran_invoice_id',
        'invoice_penagihan_detail_id',
        'jumlah',
    ];

    protected $casts = ['jumlah' => 'decimal:2'];

    public function pembayaran()
    {
        return $this->belongsTo(PembayaranInvoicePenagihan::class, 'pembayaran_invoice_id');
    }

    public function detail()
    {
        return $this->belongsTo(InvoicePenagihanDetail::class, 'invoice_penagihan_detail_id');
    }
}
