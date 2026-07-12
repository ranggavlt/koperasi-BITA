<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class MutasiKas extends Model
{
    protected $table = 'mutasi_kas';

    protected $fillable = [
        'idempotency_key',
        'dompet_id',
        'tipe',
        'jumlah',
        'keterangan',
        'referensi_tipe',
        'referensi_id',
        'tanggal'
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (MutasiKas $mutasi): void {
            if (in_array($mutasi->referensi_tipe, [
                Pinjaman::class,
                CicilanPinjaman::class,
                Penjualan::class,
                Pembayaran::class,
                Simpanan::class,
                PemakaianPotongGaji::class,
                ReversalTransaksi::class,
                PembayaranOutstandingCash::class,
                PembayaranSewaMobil::class,
                PembayaranSewaPrinter::class,
            ], true)) {
                throw new RuntimeException('Mutasi transaksi koperasi tidak boleh dihapus permanen. Gunakan reversal/adjustment.');
            }
        });
    }

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
            \App\Models\ReversalTransaksi::class => 'Reversal Transaksi',
            \App\Models\PembayaranOutstandingCash::class => 'Pembayaran Outstanding Cash',
            \App\Models\PembayaranSewaMobil::class => 'Pembayaran Sewa Mobil',
            \App\Models\PembayaranSewaPrinter::class => 'Pembayaran Sewa Printer',
            default => 'Manual',
        };
    }
}
