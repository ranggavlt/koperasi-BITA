<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ShuKoperasi extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready_for_approval';
    public const STATUS_APPROVED = 'approved';

    // Alias sementara agar histori dan pemanggil lama tetap dapat dibaca selama transisi.
    public const STATUS_CALCULATED = self::STATUS_DRAFT;
    public const STATUS_SUBMITTED = self::STATUS_READY;
    public const STATUS_READY_TO_PAY = self::STATUS_APPROVED;
    public const STATUS_COMPLETED = self::STATUS_APPROVED;

    protected $table = 'shu_koperasi';
    protected $guarded = [];
    protected $casts = [
        'tanggal_mulai' => 'date', 'tanggal_selesai' => 'date',
        'config_snapshot' => 'array', 'source_snapshot' => 'array', 'json_pengurus_split' => 'array',
        'dihitung_pada' => 'datetime', 'calculated_at' => 'datetime', 'submitted_at' => 'datetime',
        'approved_at' => 'datetime', 'posted_at' => 'datetime', 'completed_at' => 'datetime',
        'closed_at' => 'datetime', 'reversed_at' => 'datetime',
        'persen_dana_cadangan' => 'decimal:2', 'persen_shu_anggota' => 'decimal:2',
        'persen_pengawas' => 'decimal:2', 'persen_pembina' => 'decimal:2',
        'persen_pengurus' => 'decimal:2', 'persen_dana_sosial' => 'decimal:2',
        'persen_dana_pendidikan' => 'decimal:2', 'persen_jasa_modal' => 'decimal:2',
        'persen_jasa_usaha' => 'decimal:2', 'total_pendapatan' => 'decimal:2',
        'total_biaya' => 'decimal:2', 'shu_total' => 'decimal:2',
        'nominal_dana_cadangan' => 'decimal:2', 'nominal_shu_anggota' => 'decimal:2',
        'nominal_pengawas' => 'decimal:2', 'nominal_pembina' => 'decimal:2',
        'nominal_pengurus' => 'decimal:2', 'nominal_dana_sosial' => 'decimal:2',
        'nominal_dana_pendidikan' => 'decimal:2', 'nominal_jasa_modal' => 'decimal:2',
        'nominal_jasa_usaha' => 'decimal:2', 'total_bobot_modal' => 'decimal:2',
        'total_bobot_usaha' => 'decimal:2', 'total_dibayar' => 'decimal:2',
        'total_belum_dibayar' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $shu): void {
            if ($shu->getOriginal('status') === self::STATUS_APPROVED) {
                $allowed = ['total_dibayar', 'total_belum_dibayar', 'updated_at'];
                if (array_diff(array_keys($shu->getDirty()), $allowed) !== []) {
                    throw new RuntimeException('SHU yang sudah disetujui bersifat permanen. Koreksi wajib melalui reversal resmi.');
                }
            }
        });
        static::deleting(fn () => throw new RuntimeException('SHU adalah histori audit dan tidak boleh dihapus permanen.'));
    }

    public function periode() { return $this->belongsTo(PeriodeAkuntansi::class, 'periode_akuntansi_id'); }
    public function periodeAkuntansi() { return $this->periode(); }
    public function config() { return $this->belongsTo(ShuConfig::class, 'shu_config_id'); }
    public function recipients() { return $this->hasMany(ShuPenerima::class, 'shu_koperasi_id'); }
    public function allocations() { return $this->hasMany(ShuAlokasi::class, 'shu_koperasi_id'); }
    public function socialFund() { return $this->hasOne(DanaSosialSumber::class, 'shu_koperasi_id'); }
    public function socialFundSource() { return $this->socialFund(); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function calculator() { return $this->belongsTo(User::class, 'calculated_by'); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function poster() { return $this->belongsTo(User::class, 'posted_by'); }
    public function allocationJournal() { return $this->belongsTo(JurnalUmum::class, 'allocation_journal_id'); }
    public function reversalJournal() { return $this->belongsTo(JurnalUmum::class, 'reversal_journal_id'); }
    public function transaksi() { return $this->hasMany(ShuTransaksi::class, 'shu_koperasi_id'); }
    public function anggotaPembagian() { return $this->hasMany(ShuAnggota::class, 'shu_koperasi_id'); }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_READY => 'Siap Disetujui',
            self::STATUS_APPROVED => 'Disetujui',
            default => 'Rancangan',
        };
    }
}
