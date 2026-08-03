<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class BatasKlaimDanaSosial extends Model
{
    protected $table = 'batas_klaim_dana_sosial';
    protected $fillable = ['kategori', 'nominal_maksimal', 'berlaku_mulai', 'alasan', 'created_by'];
    protected $casts = ['nominal_maksimal' => 'decimal:2', 'berlaku_mulai' => 'date'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Batas klaim yang tersimpan bersifat immutable. Buat versi baru.'));
        static::deleting(fn () => throw new RuntimeException('Riwayat batas klaim tidak boleh dihapus.'));
    }

    public function scopeEffectiveOn(Builder $query, CarbonInterface|string $date): Builder
    {
        return $query->whereDate('berlaku_mulai', '<=', $date);
    }

    public static function effectiveFor(string $category, CarbonInterface|string $date): ?self
    {
        return self::query()->where('kategori', $category)->effectiveOn($date)->orderByDesc('berlaku_mulai')->orderByDesc('id')->first();
    }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
