<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class BebanOperasional extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    public const METODE_TUNAI = 'tunai';

    public const METODE_TRANSFER_BANK = 'transfer_bank';

    protected $table = 'beban_operasional';

    protected $fillable = [
        'kode_beban',
        'tanggal_beban',
        'dompet_id',
        'metode_pembayaran',
        'total_beban',
        'status',
        'keterangan',
        'nomor_referensi',
        'posted_at',
        'reversed_at',
        'alasan_reversal',
        'created_by',
        'updated_by',
        'posted_by',
        'reversed_by',
        'reversal_transaksi_id',
        'idempotency_key',
    ];

    protected $casts = [
        'tanggal_beban' => 'date',
        'total_beban' => 'decimal:2',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (BebanOperasional $beban): void {
            if ($beban->status !== self::STATUS_DRAFT) {
                throw new RuntimeException('Beban Operasional posted/reversed tidak boleh dihapus permanen. Gunakan reversal.');
            }
        });
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_POSTED,
            self::STATUS_REVERSED,
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_POSTED => 'Posted',
            self::STATUS_REVERSED => 'Reversed',
        ];
    }

    public function details()
    {
        return $this->hasMany(BebanOperasionalDetail::class, 'beban_operasional_id');
    }

    public function dompet()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedBy()
    {
        return $this->belongsTo(User::class, 'reversed_by');
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

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }
}
