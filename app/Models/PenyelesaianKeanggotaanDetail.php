<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PenyelesaianKeanggotaanDetail extends Model
{
    public const TIPE_HAK = 'hak';

    public const TIPE_KEWAJIBAN = 'kewajiban';

    public const TIPE_PEMBATALAN_WAJIB = 'pembatalan_wajib';

    public const KATEGORI_PINJAMAN = 'pinjaman';

    public const KATEGORI_CICILAN = 'cicilan';

    public const KATEGORI_POS = 'pos';

    public const KATEGORI_SIMPANAN = 'simpanan';

    public const KATEGORI_SIMPANAN_POKOK = 'simpanan_pokok';

    public const KATEGORI_SIMPANAN_WAJIB = 'simpanan_wajib';

    public const KATEGORI_SIMPANAN_MANASUKA = 'simpanan_manasuka';

    public const KATEGORI_KREDIT_REFUND = 'kredit_refund';

    public const KATEGORI_PEMBATALAN_WAJIB = 'pembatalan_wajib';

    public const KATEGORI_LAINNYA = 'lainnya';

    public const STATUS_OPEN = 'open';

    public const STATUS_OFFSET = 'offset';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_SETTLED_CASH = 'settled_cash';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'penyelesaian_keanggotaan_detail';

    protected $fillable = [
        'penyelesaian_keanggotaan_id',
        'tipe_detail',
        'kategori_sumber',
        'source_type',
        'source_id',
        'akun_id',
        'akun_kode_snapshot',
        'akun_nama_snapshot',
        'nominal_hak_awal',
        'nominal_dipakai_offset',
        'nominal_direfund',
        'nominal_dibatalkan',
        'nominal_kewajiban_awal',
        'nominal_offset',
        'nominal_dibayar_tunai',
        'nominal_sisa',
        'urutan_alokasi',
        'status',
        'processed_by',
        'processed_at',
        'idempotency_key',
    ];

    protected $casts = [
        'nominal_hak_awal' => 'decimal:2',
        'nominal_dipakai_offset' => 'decimal:2',
        'nominal_direfund' => 'decimal:2',
        'nominal_dibatalkan' => 'decimal:2',
        'nominal_kewajiban_awal' => 'decimal:2',
        'nominal_offset' => 'decimal:2',
        'nominal_dibayar_tunai' => 'decimal:2',
        'nominal_sisa' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuntimeException('Detail penyelesaian keanggotaan tidak boleh dihapus permanen.');
        });
    }

    public function penyelesaian()
    {
        return $this->belongsTo(PenyelesaianKeanggotaan::class, 'penyelesaian_keanggotaan_id');
    }

    public function source()
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => $this->tipe_detail === self::TIPE_HAK ? 'Menunggu Alokasi' : 'Menunggu Penyelesaian',
            self::STATUS_PARTIAL => 'Sebagian Diselesaikan',
            self::STATUS_OFFSET => 'Dikompensasikan ke Kewajiban',
            self::STATUS_SETTLED_CASH => 'Dibayar Tunai',
            self::STATUS_REFUNDED => 'Dikembalikan kepada Anggota',
            self::STATUS_CANCELLED => 'Tagihan Wajib Dibatalkan',
            default => str_replace('_', ' ', (string) $this->status),
        };
    }
}
