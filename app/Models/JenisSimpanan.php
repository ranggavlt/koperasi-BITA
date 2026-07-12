<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JenisSimpanan extends Model
{
    use HasFactory;

    public const KODE_SIMPANAN_POKOK = 'SIMPANAN_POKOK';

    protected $table = 'jenis_simpanan';

    protected $fillable = [
        'akun_id',
        'kode',
        'nama_jenis',
        'wajib',
        'aktif',
        'nominal_default',
        'keterangan'
    ];

    protected $casts = [
        'wajib' => 'boolean',
        'aktif' => 'boolean',
        'nominal_default' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (JenisSimpanan $jenis): void {
            if ($jenis->akun_id) {
                return;
            }

            $slug = Str::slug((string) $jenis->nama_jenis);
            $accountKey = config("account_map.postings.simpanan.jenis.{$slug}");

            if (! is_string($accountKey) || $accountKey === '') {
                return;
            }

            $accountCode = config("account_map.accounts.{$accountKey}.kode_akun");

            $jenis->akun_id = Akun::query()
                ->where('kode_akun', $accountCode)
                ->value('id');
        });
    }

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }

    public function scopeSimpananPokok($query)
    {
        return $query->where('kode', self::KODE_SIMPANAN_POKOK);
    }
}
