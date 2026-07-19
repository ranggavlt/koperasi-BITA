<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PembayaranSewaPrinter extends Model
{
    use HasFactory;

    public const METODE_TUNAI = 'tunai';

    public const METODE_TRANSFER_BANK = 'transfer_bank';

    public const STATUS_PAID = 'paid';

    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'pembayaran_sewa_printer';

    protected $fillable = [
        'sewa_printer_id',
        'dompet_penerimaan_id',
        'dompet_vendor_id',
        'metode_penerimaan',
        'metode_pembayaran_vendor',
        'jumlah_diterima',
        'jumlah_bayar_vendor',
        'status',
        'paid_at',
        'created_by',
        'idempotency_key',
    ];

    protected $casts = [
        'jumlah_diterima' => 'integer',
        'jumlah_bayar_vendor' => 'integer',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuntimeException('Pembayaran Sewa Printer tidak boleh dihapus permanen. Gunakan refund.');
        });
    }

    public function sewaPrinter()
    {
        return $this->belongsTo(SewaPrinter::class, 'sewa_printer_id');
    }

    public function dompet()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_penerimaan_id');
    }

    public function dompetPenerimaan()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_penerimaan_id');
    }

    public function dompetVendor()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_vendor_id');
    }

    public function getDompetIdAttribute(): ?int
    {
        return $this->dompet_penerimaan_id;
    }

    public function getMetodePembayaranAttribute(): ?string
    {
        return $this->metode_penerimaan;
    }

    public function getJumlahBayarAttribute(): int
    {
        return (int) ($this->jumlah_diterima ?? 0);
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
