<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ShuConfig extends Model
{
    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'persen_pembina',
        'persen_pengawas',
        'persen_pengurus',
        'persen_anggota',
        'persen_dana_sosial',
        'persen_dana_cadangan',
        'persen_dana_pendidikan',
        'persen_jasa_modal',
        'persen_jasa_usaha',
        'status_persetujuan',
        'berlaku_mulai',
        'dasar_persetujuan',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'persen_pembina' => 'decimal:2',
        'persen_pengawas' => 'decimal:2',
        'persen_pengurus' => 'decimal:2',
        'persen_anggota' => 'decimal:2',
        'persen_dana_sosial' => 'decimal:2',
        'persen_dana_cadangan' => 'decimal:2',
        'persen_dana_pendidikan' => 'decimal:2',
        'persen_jasa_modal' => 'decimal:2',
        'persen_jasa_usaha' => 'decimal:2',
        'berlaku_mulai' => 'date',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Konfigurasi SHU yang tersimpan bersifat immutable. Buat versi baru untuk perubahan.'));
        static::deleting(fn () => throw new RuntimeException('Konfigurasi SHU merupakan histori dan tidak boleh dihapus.'));
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status_persetujuan', self::STATUS_APPROVED);
    }

    public function scopeEffectiveOn(Builder $query, CarbonInterface|string $date): Builder
    {
        return $query->approved()->whereDate('berlaku_mulai', '<=', $date);
    }

    public static function effectiveFor(CarbonInterface|string $date): ?self
    {
        return self::query()
            ->effectiveOn($date)
            ->orderByDesc('berlaku_mulai')
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->first();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
