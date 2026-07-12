<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class Pembayaran extends Model
{
    public const METODE_TUNAI = 'tunai';

    public const METODE_TRANSFER_BANK = 'transfer_bank';

    public const METODE_QRIS = 'qris';

    public const METODE_POTONG_GAJI = 'potong_gaji';

    public const STATUS_PENDING_PAYROLL = 'pending_payroll';

    public const STATUS_PAID = 'paid';

    public const STATUS_OUTSTANDING_CASH = 'outstanding_cash';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_SETTLED_CASH = 'settled_cash';

    protected $table = 'pembayaran';

    protected $fillable = [
        'idempotency_key',
        'penjualan_id',
        'metode_pembayaran',
        'status',
        'dompet_id',
        'pemakaian_potong_gaji_id',
        'reversal_transaksi_id',
        'jumlah_bayar',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'jumlah_bayar' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Pembayaran $_pembayaran): void {
            throw new RuntimeException('Pembayaran tidak boleh dihapus permanen.');
        });

        static::updating(function (Pembayaran $pembayaran): void {
            if ($pembayaran->getOriginal('status') === self::STATUS_PAID && $pembayaran->isDirty()) {
                $dirty = array_keys($pembayaran->getDirty());
                sort($dirty);
                $allowed = ['reversal_transaksi_id', 'status', 'updated_at'];
                sort($allowed);

                if ($dirty !== array_intersect($dirty, $allowed) || ! in_array($pembayaran->status, [self::STATUS_REFUNDED], true)) {
                    throw new RuntimeException('Pembayaran yang sudah paid tidak boleh diedit. Gunakan reversal/refund.');
                }
            }
        });
    }

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'penjualan_id');
    }

    public function dompet()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_id');
    }

    public function ledger()
    {
        return $this->belongsTo(PemakaianPotongGaji::class, 'pemakaian_potong_gaji_id');
    }

    public function reversal()
    {
        return $this->belongsTo(ReversalTransaksi::class, 'reversal_transaksi_id');
    }
}
