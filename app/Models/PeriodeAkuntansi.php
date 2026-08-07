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
        'checksum', 'closing_snapshot', 'created_by', 'closed_by', 'closed_at',
        'closing_reason', 'closing_journal_id', 'closing_idempotency_key', 'idempotency_key',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'closed_at' => 'datetime',
        'closing_snapshot' => 'array',
        'total_pendapatan' => 'decimal:2',
        'total_beban' => 'decimal:2',
        'laba_bersih' => 'decimal:2',
        'jumlah_jurnal' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $period): void {
            if ($period->getOriginal('status') === self::STATUS_CLOSED) {
                throw new RuntimeException('Periode pembukuan yang sudah ditutup bersifat permanen dan tidak dapat diubah.');
            }
        });
        static::deleting(fn () => throw new RuntimeException('Periode pembukuan tidak boleh dihapus.'));
    }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function closer() { return $this->belongsTo(User::class, 'closed_by'); }
    public function closingJournal() { return $this->belongsTo(JurnalUmum::class, 'closing_journal_id'); }
    public function journals() { return $this->hasMany(JurnalUmum::class, 'periode_akuntansi_id'); }
    public function shu() { return $this->hasOne(ShuKoperasi::class, 'periode_akuntansi_id'); }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_CLOSED => 'Sudah Ditutup',
            self::STATUS_CLOSING => 'Sedang Ditutup',
            default => 'Sedang Berjalan',
        };
    }
}
