<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsetKoperasi extends Model
{
    use HasFactory;

    public const JENIS_MOBIL = 'mobil';

    public const JENIS_PRINTER = 'printer';

    public const STATUS_TERSEDIA = 'tersedia';

    public const STATUS_DIGUNAKAN_DISEWA = 'digunakan_disewa';

    public const STATUS_PERAWATAN = 'perawatan';

    public const STATUS_NONAKTIF = 'nonaktif';

    protected $table = 'aset_koperasi';

    protected $fillable = [
        'kode_aset',
        'jenis_aset',
        'merek',
        'model',
        'status',
        'keterangan',
        'created_by',
        'updated_by',
        'nonaktif_at',
        'nonaktif_by',
    ];

    protected $casts = [
        'nonaktif_at' => 'datetime',
    ];

    public static function jenis(): array
    {
        return [
            self::JENIS_MOBIL,
            self::JENIS_PRINTER,
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_TERSEDIA,
            self::STATUS_DIGUNAKAN_DISEWA,
            self::STATUS_PERAWATAN,
            self::STATUS_NONAKTIF,
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_TERSEDIA => 'Tersedia',
            self::STATUS_DIGUNAKAN_DISEWA => 'Digunakan/Disewa',
            self::STATUS_PERAWATAN => 'Perawatan',
            self::STATUS_NONAKTIF => 'Nonaktif',
        ];
    }

    public function mobil()
    {
        return $this->hasOne(AsetMobil::class, 'aset_koperasi_id');
    }

    public function printer()
    {
        return $this->hasOne(AsetPrinter::class, 'aset_koperasi_id');
    }

    public function bebanOperasionalDetails()
    {
        return $this->hasMany(BebanOperasionalDetail::class, 'aset_koperasi_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function nonaktifBy()
    {
        return $this->belongsTo(User::class, 'nonaktif_by');
    }

    public function scopeMobil($query)
    {
        return $query->where('jenis_aset', self::JENIS_MOBIL);
    }

    public function scopePrinter($query)
    {
        return $query->where('jenis_aset', self::JENIS_PRINTER);
    }

    public function scopeStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function isMobil(): bool
    {
        return $this->jenis_aset === self::JENIS_MOBIL;
    }

    public function isPrinter(): bool
    {
        return $this->jenis_aset === self::JENIS_PRINTER;
    }

    public function isNonaktif(): bool
    {
        return $this->status === self::STATUS_NONAKTIF;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function getNamaAsetAttribute(): string
    {
        return trim("{$this->merek} {$this->model}");
    }
}
