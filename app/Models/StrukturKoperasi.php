<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class StrukturKoperasi extends Model
{
    public const KELOMPOK = ['pengurus', 'pengawas', 'pembina'];
    public const STATUS_AKTIF = 'aktif';
    public const STATUS_NONAKTIF = 'nonaktif';

    protected $table = 'struktur_koperasi';

    protected $fillable = [
        'anggota_id', 'nama_penerima', 'kelompok', 'jabatan', 'tanggal_mulai',
        'tanggal_selesai', 'status', 'dasar_keputusan', 'created_by',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Histori Struktur Koperasi tidak boleh dihapus.'));
    }

    public function anggota() { return $this->belongsTo(Anggota::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function penerimaShu() { return $this->hasMany(ShuPenerima::class, 'struktur_koperasi_id'); }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('tanggal_mulai', '<=', $date)
            ->where(function (Builder $inner) use ($date): void {
                $inner->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $date);
            });
    }

    public function getNamaAttribute(): string
    {
        return $this->anggota?->karyawan?->nama ?? (string) $this->nama_penerima;
    }

    public function getKelompokLabelAttribute(): string
    {
        return ucfirst($this->kelompok);
    }
}
