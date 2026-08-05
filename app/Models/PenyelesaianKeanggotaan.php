<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PenyelesaianKeanggotaan extends Model
{
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_WAITING_SETTLEMENT = 'waiting_settlement';

    public const STATUS_READY_TO_COMPLETE = 'ready_to_complete';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_DEACTIVATION_CANCELLED = 'dibatalkan_penonaktifan';

    public const METODE_TUNAI = 'tunai';

    public const METODE_TRANSFER_BANK = 'transfer_bank';

    protected $table = 'penyelesaian_keanggotaan';

    protected $fillable = [
        'kode_penyelesaian',
        'anggota_id',
        'siklus_keanggotaan_id',
        'tanggal_keluar',
        'simpanan_pokok_snapshot',
        'kredit_refund_snapshot',
        'total_hak_anggota',
        'total_kewajiban_awal',
        'total_offset',
        'total_refund',
        'sisa_kewajiban',
        'status',
        'siklus_final_id',
        'dompet_refund_id',
        'metode_refund',
        'processed_at',
        'completed_at',
        'alasan',
        'created_by',
        'processed_by',
        'completed_by',
        'deactivation_cancelled_by',
        'deactivation_cancelled_at',
        'deactivation_cancel_reason',
        're_registered_by',
        're_registered_at',
        're_register_reason',
        're_registered_cycle_id',
        're_registration_idempotency_key',
        'idempotency_key',
    ];

    protected $casts = [
        'tanggal_keluar' => 'date',
        'simpanan_pokok_snapshot' => 'decimal:2',
        'kredit_refund_snapshot' => 'decimal:2',
        'total_hak_anggota' => 'decimal:2',
        'total_kewajiban_awal' => 'decimal:2',
        'total_offset' => 'decimal:2',
        'total_refund' => 'decimal:2',
        'sisa_kewajiban' => 'decimal:2',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'deactivation_cancelled_at' => 'datetime',
        're_registered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (PenyelesaianKeanggotaan $penyelesaian): void {
            if (DB::connection()->getDriverName() !== 'mysql') {
                $penyelesaian->siklus_final_id = ! in_array($penyelesaian->status, [
                    self::STATUS_CANCELLED,
                    self::STATUS_DEACTIVATION_CANCELLED,
                ], true)
                    ? $penyelesaian->siklus_keanggotaan_id
                    : null;
            }
        });

        static::deleting(function (): void {
            throw new RuntimeException('Penyelesaian keanggotaan tidak boleh dihapus permanen.');
        });
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING_REVIEW,
            self::STATUS_WAITING_SETTLEMENT,
            self::STATUS_READY_TO_COMPLETE,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_DEACTIVATION_CANCELLED,
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_REVIEW => 'Menunggu Penyelesaian',
            self::STATUS_WAITING_SETTLEMENT => 'Masih Ada Utang',
            self::STATUS_READY_TO_COMPLETE => 'Siap Diselesaikan',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_CANCELLED => 'Dibatalkan',
            self::STATUS_DEACTIVATION_CANCELLED => 'Batal Keluar',
            default => str_replace('_', ' ', (string) $this->status),
        };
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'anggota_id');
    }

    public function siklus()
    {
        return $this->belongsTo(SiklusKeanggotaan::class, 'siklus_keanggotaan_id');
    }

    public function siklusDaftarUlang()
    {
        return $this->belongsTo(SiklusKeanggotaan::class, 're_registered_cycle_id');
    }

    public function details()
    {
        return $this->hasMany(PenyelesaianKeanggotaanDetail::class, 'penyelesaian_keanggotaan_id')
            ->orderBy('urutan_alokasi');
    }

    public function dompetRefund()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_refund_id');
    }

    public function jurnal()
    {
        return $this->morphMany(JurnalUmum::class, 'referensi', 'referensi_tipe', 'referensi_id');
    }

    public function mutasiKas()
    {
        return $this->morphMany(MutasiKas::class, 'referensi', 'referensi_tipe', 'referensi_id');
    }
}
