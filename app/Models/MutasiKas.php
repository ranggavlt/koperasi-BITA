<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MutasiKas extends Model
{
    protected $table = 'mutasi_kas';

    protected $fillable = [
        'dompet_id',
        'tipe',
        'jumlah',
        'keterangan',
        'referensi_tipe',
        'referensi_id',
        'tanggal'
    ];

    public function dompet()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_id');
    }

    public function getSumberLabelAttribute(): string
    {
        return match ($this->referensi_tipe) {
            \App\Models\Penjualan::class => 'Penjualan',
            \App\Models\Simpanan::class => 'Simpanan',
            \App\Models\Pinjaman::class => 'Pinjaman',
            \App\Models\CicilanPinjaman::class => 'Cicilan Pinjaman',
            \App\Models\PembayaranKonsinyasi::class => 'Pembayaran Konsinyasi',
            default => 'Manual',
        };
    }
}
