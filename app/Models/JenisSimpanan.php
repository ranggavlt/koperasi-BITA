<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class JenisSimpanan extends Model
{
    use HasFactory;

    public const KODE_SIMPANAN_POKOK = 'SIMPANAN_POKOK';

    public const KODE_SIMPANAN_WAJIB = 'SIMPANAN_WAJIB';

    public const KODE_SIMPANAN_SUKARELA = 'SIMPANAN_SUKARELA';

    public const KATEGORI_POKOK = 'pokok';

    public const KATEGORI_WAJIB = 'wajib';

    public const KATEGORI_SUKARELA = 'sukarela';

    public const KATEGORI = [
        self::KATEGORI_POKOK => 'Pokok',
        self::KATEGORI_WAJIB => 'Wajib',
        self::KATEGORI_SUKARELA => 'Sukarela',
    ];

    public const KODE_BY_KATEGORI = [
        self::KATEGORI_POKOK => self::KODE_SIMPANAN_POKOK,
        self::KATEGORI_WAJIB => self::KODE_SIMPANAN_WAJIB,
        self::KATEGORI_SUKARELA => self::KODE_SIMPANAN_SUKARELA,
    ];

    protected $table = 'jenis_simpanan';

    protected $fillable = [
        'akun_id',
        'kode',
        'kategori',
        'interval_bulan',
        'berlaku_mulai',
        'nama_jenis',
        'wajib',
        'aktif',
        'active_kategori_marker',
        'nominal_default',
        'keterangan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'wajib' => 'boolean',
        'aktif' => 'boolean',
        'nominal_default' => 'decimal:2',
        'interval_bulan' => 'integer',
        'berlaku_mulai' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (JenisSimpanan $jenis): void {
            if (DB::connection()->getDriverName() === 'mysql') {
                return;
            }

            $jenis->active_kategori_marker = $jenis->aktif && $jenis->kategori
                ? $jenis->kategori
                : null;
        });
    }

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function simpanan()
    {
        return $this->hasMany(Simpanan::class, 'jenis_simpanan_id');
    }

    public function riwayat()
    {
        return $this->hasMany(RiwayatJenisSimpanan::class, 'jenis_simpanan_id');
    }

    public function latestRiwayat()
    {
        return $this->hasOne(RiwayatJenisSimpanan::class, 'jenis_simpanan_id')->latestOfMany('changed_at');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }

    public function scopeSimpananPokok($query)
    {
        return $query->where('kode', self::KODE_SIMPANAN_POKOK);
    }

    public function scopeKategori($query, string $kategori)
    {
        return $query->where('kategori', $kategori);
    }

    public function getKategoriLabelAttribute(): string
    {
        return self::KATEGORI[$this->kategori] ?? 'Belum diklasifikasi';
    }

    public function getFrekuensiLabelAttribute(): string
    {
        return match ($this->kategori) {
            self::KATEGORI_POKOK => 'Sekali saat menjadi Anggota',
            self::KATEGORI_WAJIB => 'Setiap ' . (int) $this->interval_bulan . ' bulan',
            self::KATEGORI_SUKARELA => 'Sesuai transaksi',
            default => '-',
        };
    }

    public function getIsTerpakaiAttribute(): bool
    {
        if (! $this->exists) {
            return false;
        }

        return Simpanan::query()->where('jenis_simpanan_id', $this->id)->exists();
    }

    public static function kodeUntukKategori(string $kategori): ?string
    {
        return self::KODE_BY_KATEGORI[$kategori] ?? null;
    }
}
