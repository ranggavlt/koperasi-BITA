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
        'total_pendapatan', 'total_beban', 'laba_bersih', 'created_by',
        'closed_by', 'closed_at', 'closing_journal_id', 'closing_idempotency_key',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date', 'tanggal_selesai' => 'date', 'closed_at' => 'datetime',
        'total_pendapatan' => 'decimal:2', 'total_beban' => 'decimal:2', 'laba_bersih' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Periode pembukuan tidak boleh dihapus.'));
    }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function closer() { return $this->belongsTo(User::class, 'closed_by'); }
    public function closingJournal() { return $this->belongsTo(JurnalUmum::class, 'closing_journal_id'); }
    public function shu() { return $this->hasOne(ShuKoperasi::class, 'periode_akuntansi_id'); }

    public function getStatusLabelAttribute(): string
    {
        return $this->status === self::STATUS_CLOSED ? 'Sudah Ditutup' : 'Sedang Berjalan';
    }
}
