<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JenisSimpanan extends Model
{
    use HasFactory;

    protected $table = 'jenis_simpanan';

    protected $fillable = [
        'akun_id',
        'nama_jenis',
        'wajib',
        'nominal_default',
        'keterangan'
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
}
