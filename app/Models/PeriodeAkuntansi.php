<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PeriodeAkuntansi extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSING = 'closing';
    public const STATUS_CLOSED = 'closed';

    protected $table = 'periode_akuntansi';

    protected $fillable = [
        'kode', 'nama', 'tanggal_mulai', 'tanggal_selesai', 'status',
        'total_pendapatan', 'total_beban', 'laba_bersih', 'jumlah_jurnal',
        'checksum', 'closing_snapshot', 'closing_journal_id', 'created_by',
        'closed_by', 'closed_at', 'closing_reason', 'idempotency_key',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'total_pendapatan' => 'decimal:2',
        'total_beban' => 'decimal:2',
        'laba_bersih' => 'decimal:2',
        'jumlah_jurnal' => 'integer',
        'closing_snapshot' => 'array',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Periode akuntansi merupakan histori audit dan tidak boleh dihapus.'));
        static::updating(function (self $period): void {
            if ($period->getOriginal('status') === self::STATUS_CLOSED) {
                throw new RuntimeException('Periode akuntansi yang sudah ditutup bersifat immutable.');
            }
        });
    }

    public function journals()
    {
        return $this->hasMany(JurnalUmum::class, 'periode_akuntansi_id');
    }

    public function closingJournal()
    {
        return $this->belongsTo(JurnalUmum::class, 'closing_journal_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function shuKoperasi()
    {
        return $this->hasOne(ShuKoperasi::class, 'periode_akuntansi_id');
    }
}
