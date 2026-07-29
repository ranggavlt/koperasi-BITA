<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Produk extends Model
{
    /** @use HasFactory<\Database\Factories\ProdukFactory> */
    use HasFactory;

    public const DEMO_PHOTO_PREFIX = 'assets/img/demo-products/';

    public const FALLBACK_PHOTO_PATH = 'assets/img/demo-products/fallback-produk.svg';

    protected $table = 'produk';

    protected $fillable = [
        'nama_produk',
        'foto',
        'kategori_id',
        'harga_beli',
        'harga_jual',
        'stok',
        'konsinyasi',
        'reseller_id',
        'harga_setor',
    ];

    public function getFotoUrlAttribute(): string
    {
        return $this->fotoUrl();
    }

    public function fotoUrl(): string
    {
        $path = trim(str_replace('\\', '/', (string) $this->foto));

        if ($path === '') {
            return $this->fallbackFotoUrl();
        }

        $path = ltrim($path, '/');

        if (Str::startsWith($path, self::DEMO_PHOTO_PREFIX)) {
            return is_file(public_path($path))
                ? asset($path)
                : $this->fallbackFotoUrl();
        }

        if (Str::startsWith($path, 'storage/')) {
            return is_file(public_path($path))
                ? asset($path)
                : $this->fallbackFotoUrl();
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : $this->fallbackFotoUrl();
    }

    private function fallbackFotoUrl(): string
    {
        return asset(self::FALLBACK_PHOTO_PATH);
    }

    public function kategori()
    {
        return $this->belongsTo(KategoriProduk::class, 'kategori_id');
    }

    public function reseller()
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }
}
