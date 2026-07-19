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
        'metode_pembayaran',
        'jumlah_bayar',
        'status',
        'paid_at',
        'refunded_at',
        'created_by',
        'idempotency_key',
    ];

    protected $casts = [
        'jumlah_bayar' => 'integer',
        'paid_at' => 'datetime',
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

    public function mutasiKas()
    {
        return $this->morphMany(MutasiKas::class, 'referensi', 'referensi_tipe', 'referensi_id');
    }

    public function jurnal()
    {
        return $this->morphMany(JurnalUmum::class, 'referensi', 'referensi_tipe', 'referensi_id');
    }
}
