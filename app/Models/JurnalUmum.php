<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class JurnalUmum extends Model
{
    protected $table = 'jurnal_umum';

    protected $fillable = [
        'idempotency_key',
        'tanggal',
        'nomor_bukti',
        'keterangan',
        'referensi_tipe',
        'referensi_id',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function details()
    {
        return $this->hasMany(JurnalUmumDetail::class, 'jurnal_umum_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (JurnalUmum $jurnal): void {
            if (in_array($jurnal->referensi_tipe, [
                Pinjaman::class,
                CicilanPinjaman::class,
                Penjualan::class,
                Pembayaran::class,
                Simpanan::class,
                PemakaianPotongGaji::class,
                ReversalTransaksi::class,
                PembayaranOutstandingCash::class,
                PembayaranSewaMobil::class,
                SewaMobil::class,
                PembayaranSewaHardware::class,
                SewaHardware::class,
                BebanOperasional::class,
                PenyelesaianKeanggotaan::class,
            ], true)) {
                throw new RuntimeException('Jurnal transaksi koperasi tidak boleh dihapus permanen. Gunakan reversal/adjustment.');
            }
        });
    }
}
