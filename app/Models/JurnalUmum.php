<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class JurnalUmum extends Model
{
    protected $table = 'jurnal_umum';

    protected $fillable = [
        'idempotency_key',
        'periode_akuntansi_id',
        'status',
        'posted_at',
        'is_adjustment',
        'correction_period_id',
        'correction_reason',
        'tanggal',
        'nomor_bukti',
        'keterangan',
        'referensi_tipe',
        'referensi_id',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'posted_at' => 'datetime',
        'is_adjustment' => 'boolean',
    ];

    public function details()
    {
        return $this->hasMany(JurnalUmumDetail::class, 'jurnal_umum_id');
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Jurnal tidak boleh dihapus permanen. Gunakan reversal/counter-entry.'));
        static::updating(function (self $journal): void {
            if ($journal->getOriginal('status') === self::STATUS_POSTED) {
                throw new RuntimeException('Jurnal posted bersifat immutable. Gunakan reversal/counter-entry.');
            }
        });
    }

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';

    public function periodeAkuntansi()
    {
        return $this->belongsTo(PeriodeAkuntansi::class, 'periode_akuntansi_id');
    }

    public function correctionPeriod()
    {
        return $this->belongsTo(PeriodeAkuntansi::class, 'correction_period_id');
    }
}
