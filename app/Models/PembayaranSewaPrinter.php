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
        'dompet_id',
        'metode_pembayaran',
        'jumlah_bayar',
        'status',
        'paid_at',
        'refunded_at',
        'created_by',
        'idempotency_key',
    ];

    protected $casts = [
        'jumlah_bayar' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
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
        return $this->belongsTo(DompetKoperasi::class, 'dompet_id');
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
