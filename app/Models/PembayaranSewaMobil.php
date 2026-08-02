<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PembayaranSewaMobil extends Model
{
    use HasFactory;

    public const METODE_TUNAI = 'tunai';

    public const METODE_TRANSFER_BANK = 'transfer_bank';

    public const STATUS_PAID = 'paid';

    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'pembayaran_sewa_mobil';

    protected $fillable = [
        'sewa_mobil_id',
        'dompet_id',
        'dompet_penerimaan_id',
        'metode_pembayaran',
        'metode_penerimaan',
        'jumlah_bayar',
        'jumlah_diterima',
        'received_at',
        'dompet_vendor_id',
        'metode_pembayaran_vendor',
        'jumlah_bayar_vendor',
        'vendor_paid_at',
        'status',
        'paid_at',
        'refunded_at',
        'refunded_by',
        'refund_reason',
        'reversal_transaksi_id',
        'created_by',
        'idempotency_key',
    ];

    protected $casts = [
        'jumlah_bayar' => 'integer',
        'jumlah_diterima' => 'integer',
        'jumlah_bayar_vendor' => 'integer',
        'paid_at' => 'datetime',
        'received_at' => 'datetime',
        'vendor_paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuntimeException('Pembayaran Sewa Mobil tidak boleh dihapus permanen. Gunakan refund.');
        });
    }

    public function sewaMobil()
    {
        return $this->belongsTo(SewaMobil::class, 'sewa_mobil_id');
    }

    public function dompet()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_id');
    }

    public function dompetPenerimaan()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_penerimaan_id');
    }

    public function dompetVendor()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_vendor_id');
    }

    public function reversal()
    {
        return $this->belongsTo(ReversalTransaksi::class, 'reversal_transaksi_id');
    }

    public function mutasiKas()
    {
        return $this->morphMany(MutasiKas::class, 'referensi', 'referensi_tipe', 'referensi_id');
    }

    public function jurnal()
    {
        return $this->morphMany(JurnalUmum::class, 'referensi', 'referensi_tipe', 'referensi_id');
    }
}
