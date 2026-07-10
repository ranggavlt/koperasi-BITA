<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Akun extends Model
{
    public const KATEGORI = [
        'aset' => 'Aset',
        'kewajiban' => 'Kewajiban',
        'ekuitas' => 'Ekuitas',
        'pendapatan' => 'Pendapatan',
        'beban' => 'Beban',
    ];

    protected $table = 'akun';

    protected $fillable = [
        'kode_akun',
        'nama_akun',
        'kategori',
        'posisi_saldo',
        'is_aktif',
        'is_sistem',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
            'is_sistem' => 'boolean',
        ];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }

    public function jurnalDetails()
    {
        return $this->hasMany(JurnalUmumDetail::class, 'akun_id');
    }

    public static function posisiSaldoUntuk(string $kategori): string
    {
        return in_array($kategori, ['kewajiban', 'ekuitas', 'pendapatan'], true)
            ? 'kredit'
            : 'debit';
    }

    public function getKategoriLabelAttribute(): string
    {
        return self::KATEGORI[$this->kategori] ?? ucfirst($this->kategori);
    }
}
