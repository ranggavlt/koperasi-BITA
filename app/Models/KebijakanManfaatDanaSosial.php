<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class KebijakanManfaatDanaSosial extends Model
{
    protected $table = 'kebijakan_manfaat_dana_sosial';
    protected $guarded = [];
    protected $casts = [
        'batas_maksimal' => 'decimal:2',
        'berlaku_mulai' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Kebijakan manfaat yang tersimpan tidak boleh diedit. Buat versi baru.'));
        static::deleting(fn () => throw new RuntimeException('Riwayat kebijakan manfaat tidak boleh dihapus.'));
    }

    public function jenisManfaat() { return $this->belongsTo(JenisManfaatDanaSosial::class, 'jenis_manfaat_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeEffectiveOn(Builder $query, CarbonInterface|string $date): Builder
    {
        return $query->whereDate('berlaku_mulai', '<=', $date);
    }

    public static function effectiveFor(int $benefitId, CarbonInterface|string $date): ?self
    {
        return self::query()->where('jenis_manfaat_id', $benefitId)->where('is_active', true)
            ->effectiveOn($date)->latest('berlaku_mulai')->latest('id')->first();
    }
}
