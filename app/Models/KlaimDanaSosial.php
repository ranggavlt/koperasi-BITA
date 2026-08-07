<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class KlaimDanaSosial extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_WAITING_FUNDS = 'waiting_funds';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CORRECTED = 'corrected';

    // Alias pembacaan untuk kode lama.
    public const DRAFT = self::STATUS_DRAFT;
    public const MENUNGGU = self::STATUS_SUBMITTED;
    public const DISETUJUI = self::STATUS_APPROVED;
    public const DIBAYAR = self::STATUS_PAID;
    public const STATUS_DIAJUKAN = self::STATUS_SUBMITTED;
    public const STATUS_DISETUJUI = self::STATUS_APPROVED;
    public const STATUS_DITOLAK = self::STATUS_REJECTED;
    public const STATUS_REVERSED = self::STATUS_CORRECTED;

    protected $table = 'klaim_dana_sosial';
    protected $guarded = [];
    protected $casts = [
        'tanggal_kejadian' => 'date', 'tanggal_pengajuan' => 'date', 'tanggal_bayar' => 'date',
        'nominal_diajukan' => 'decimal:2', 'nominal_disetujui' => 'decimal:2',
        'nominal' => 'decimal:2', 'batas_nominal_snapshot' => 'decimal:2',
        'batas_berlaku_snapshot' => 'date', 'submitted_at' => 'datetime',
        'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'paid_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $claim): void {
            if ($claim->getOriginal('status') === self::STATUS_PAID) {
                $allowed = ['status', 'reversed_by', 'reversed_at', 'reversal_reason', 'reversal_journal_id', 'updated_at'];
                if (array_diff(array_keys($claim->getDirty()), $allowed) !== []) {
                    throw new RuntimeException('Klaim yang sudah dicairkan hanya dapat dikoreksi melalui reversal resmi.');
                }
            }
        });
        static::deleting(fn () => throw new RuntimeException('Klaim Dana Sosial tidak boleh dihapus.'));
    }

    public function anggota() { return $this->belongsTo(Anggota::class); }
    public function karyawan() { return $this->belongsTo(Karyawan::class); }
    public function kebijakan() { return $this->belongsTo(KebijakanManfaatDanaSosial::class, 'kebijakan_manfaat_id'); }
    public function sumber() { return $this->belongsTo(DanaSosialSumber::class, 'sumber_dana_sosial_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function payer() { return $this->belongsTo(User::class, 'paid_by'); }
    public function dompet() { return $this->belongsTo(DompetKoperasi::class); }
    public function allocations() { return $this->hasMany(AlokasiKlaimDanaSosial::class, 'klaim_dana_sosial_id'); }
    public function reverser() { return $this->belongsTo(User::class, 'reversed_by'); }
    public function reversalJournal() { return $this->belongsTo(JurnalUmum::class, 'reversal_journal_id'); }
    public function mutasiDana() { return $this->hasMany(MutasiDanaSosial::class, 'klaim_dana_sosial_id'); }
    public function mutasiKas() { return $this->morphMany(MutasiKas::class, 'referensi', 'referensi_tipe', 'referensi_id'); }
    public function jurnal() { return $this->morphMany(JurnalUmum::class, 'referensi', 'referensi_tipe', 'referensi_id'); }
    public function batasKlaim() { return $this->belongsTo(BatasKlaimDanaSosial::class, 'batas_klaim_id'); }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SUBMITTED => 'Diajukan',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_WAITING_FUNDS => 'Menunggu Dana',
            self::STATUS_PAID => 'Dibayar',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_CORRECTED => 'Dikoreksi',
            default => 'Draft',
        };
    }
}
