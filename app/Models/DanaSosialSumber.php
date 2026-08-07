<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class DanaSosialSumber extends Model
{
    public const JENIS_SHU = 'alokasi_shu';
    public const JENIS_DONASI = 'donasi_resmi'; // histori saja
    public const JENIS_TAMBAHAN = self::JENIS_DONASI;
    public const STATUS_ACTIVE = 'approved';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_REVERSED = 'reversed';

    protected $table = 'dana_sosial_sumber';
    protected $guarded = [];
    protected $casts = [
        'jumlah' => 'decimal:2', 'nominal_awal' => 'decimal:2', 'saldo_tersedia' => 'decimal:2',
        'tanggal' => 'date', 'tanggal_diterima' => 'date', 'approved_at' => 'datetime',
        'reversed_at' => 'datetime', 'is_legacy' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $source): void {
            if ($source->is_legacy) {
                throw new RuntimeException('Sumber Dana Sosial lama hanya dapat dibaca.');
            }
            $allowed = ['saldo_tersedia', 'status', 'reversal_journal_id', 'reversed_by', 'reversed_at', 'reversal_reason', 'updated_at'];
            if (array_diff(array_keys($source->getDirty()), $allowed) !== []) {
                throw new RuntimeException('Sumber Dana Sosial dari SHU tidak boleh diubah.');
            }
        });
        static::deleting(fn () => throw new RuntimeException('Sumber Dana Sosial tidak boleh dihapus.'));
    }

    public function shu() { return $this->belongsTo(ShuKoperasi::class, 'shu_koperasi_id'); }
    public function shuKoperasi() { return $this->shu(); }
    public function periode() { return $this->belongsTo(PeriodeAkuntansi::class, 'periode_akuntansi_id'); }
    public function config() { return $this->belongsTo(ShuConfig::class, 'shu_config_id'); }
    public function allocationJournal() { return $this->belongsTo(JurnalUmum::class, 'allocation_journal_id'); }
    public function dompet() { return $this->belongsTo(DompetKoperasi::class); }
    public function allocations() { return $this->hasMany(AlokasiKlaimDanaSosial::class, 'dana_sosial_sumber_id'); }
    public function claims() { return $this->hasMany(KlaimDanaSosial::class, 'sumber_dana_sosial_id'); }
    public function mutations() { return $this->hasMany(MutasiDanaSosial::class, 'dana_sosial_sumber_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function reverser() { return $this->belongsTo(User::class, 'reversed_by'); }
    public function reversalJournal() { return $this->belongsTo(JurnalUmum::class, 'reversal_journal_id'); }
}
