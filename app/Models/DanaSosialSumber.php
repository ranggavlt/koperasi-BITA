<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class DanaSosialSumber extends Model
{
    public const JENIS_SHU = 'shu';
    public const JENIS_DONASI = 'donasi_resmi';
    public const JENIS_TAMBAHAN = self::JENIS_DONASI;
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_REVERSED = 'reversed';

    protected $table = 'dana_sosial_sumber';
    protected $fillable = ['kode_sumber', 'nama_sumber', 'jenis_sumber', 'shu_koperasi_id', 'dompet_id', 'metode_penerimaan', 'tanggal_diterima', 'nomor_referensi', 'bukti_penerimaan', 'nominal_awal', 'saldo_tersedia', 'status', 'keterangan', 'created_by', 'approved_by', 'approved_at', 'approval_reason', 'reversal_journal_id', 'reversed_by', 'reversed_at', 'reversal_reason', 'idempotency_key'];
    protected $casts = ['nominal_awal' => 'decimal:2', 'saldo_tersedia' => 'decimal:2', 'tanggal_diterima' => 'date', 'approved_at' => 'datetime', 'reversed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Sumber Dana Sosial merupakan histori dan tidak boleh dihapus.'));
        static::updating(function (self $source): void {
            if (in_array($source->getOriginal('status'), [self::STATUS_ACTIVE, self::STATUS_CLOSED, self::STATUS_REVERSED], true)) {
                $allowed = ['saldo_tersedia', 'status', 'reversal_journal_id', 'reversed_by', 'reversed_at', 'reversal_reason', 'updated_at'];
                if (array_diff(array_keys($source->getDirty()), $allowed) !== []) {
                    throw new RuntimeException('Sumber Dana Sosial final tidak dapat diedit. Gunakan reversal/counter-entry.');
                }
            }
        });
    }

    public function shuKoperasi() { return $this->belongsTo(ShuKoperasi::class); }
    public function claims() { return $this->hasMany(KlaimDanaSosial::class, 'sumber_dana_sosial_id'); }
    public function mutations() { return $this->hasMany(MutasiDanaSosial::class, 'dana_sosial_sumber_id'); }
    public function dompet() { return $this->belongsTo(DompetKoperasi::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function reverser() { return $this->belongsTo(User::class, 'reversed_by'); }
    public function reversalJournal() { return $this->belongsTo(JurnalUmum::class, 'reversal_journal_id'); }
}
