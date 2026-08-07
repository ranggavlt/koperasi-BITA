<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ShuPenerima extends Model
{
    public const BELUM_DIBAYAR = 'belum_dibayar';
    public const DIBAYAR = 'dibayar';
    public const DIREVERSAL = 'direversal';

    public const ALASAN_HAK_FINAL = [
        'keputusan_rat', 'pertimbangan_pengurus', 'aktivitas_data_di_luar_sistem',
        'koreksi_data_anggota', 'lainnya',
    ];

    protected $table = 'shu_penerima';
    protected $guarded = [];
    protected $casts = [
        'bobot' => 'decimal:3', 'simpanan_wajib_dihitung' => 'decimal:2',
        'simpanan_manasuka_dihitung' => 'decimal:2', 'basis_jasa_modal' => 'decimal:2',
        'basis_jasa_usaha' => 'decimal:2', 'nominal_jasa_modal' => 'decimal:2',
        'nominal_jasa_usaha' => 'decimal:2', 'hitungan_sistem' => 'decimal:2',
        'hak_final' => 'decimal:2', 'nominal_hak' => 'decimal:2',
        'eligible' => 'boolean', 'diikutkan' => 'boolean', 'formula_snapshot' => 'array',
        'hak_final_ditetapkan_at' => 'datetime', 'eligibility_set_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $recipient): void {
            if ($recipient->shu?->status === ShuKoperasi::STATUS_APPROVED) {
                $allowed = ['status_pembayaran', 'updated_at'];
                if (array_diff(array_keys($recipient->getDirty()), $allowed) !== []) {
                    throw new RuntimeException('Hak penerima SHU yang sudah disetujui bersifat permanen.');
                }
            }
        });
        static::deleting(function (self $recipient): void {
            if ($recipient->shu?->status !== ShuKoperasi::STATUS_DRAFT) {
                throw new RuntimeException('Penerima SHU yang sudah siap atau disetujui tidak boleh dihapus.');
            }
        });
    }

    public function shu() { return $this->belongsTo(ShuKoperasi::class, 'shu_koperasi_id'); }
    public function anggota() { return $this->belongsTo(Anggota::class); }
    public function pengurus() { return $this->belongsTo(PengurusKoperasi::class, 'pengurus_koperasi_id'); }
    public function struktur() { return $this->belongsTo(StrukturKoperasi::class, 'struktur_koperasi_id'); }
    public function pembayaran() { return $this->hasOne(PembayaranShu::class, 'shu_penerima_id'); }
    public function finalRight(): int { return (int) ($this->hak_final ?? $this->hitungan_sistem ?? $this->nominal_hak); }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status_pembayaran) {
            self::DIBAYAR => 'Sudah Dibayar',
            self::DIREVERSAL => 'Direversal',
            default => 'Belum Dibayar',
        };
    }
}
