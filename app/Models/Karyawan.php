<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Karyawan extends Model
{
    /** @use HasFactory<\Database\Factories\KaryawanFactory> */
    use HasFactory;

    protected $table = 'karyawan';

    protected $fillable = [
        'nama',
        'email',
        'telepon',
        'jabatan',
        'status_kerja',
        'tanggal_berhenti',
    ];

    protected $casts = [
        'is_anggota' => 'boolean',
        'tanggal_berhenti' => 'date',
    ];

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_BERHENTI = 'berhenti';

    public function anggota()
    {
        return $this->hasOne(Anggota::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function penjualan()
    {
        return $this->hasMany(Penjualan::class);
    }

    public function simpanan()
    {
        return $this->hasMany(Simpanan::class);
    }

    public function pinjaman()
    {
        return $this->hasMany(Pinjaman::class);
    }

    public function pembagianShu()
    {
        return $this->hasMany(ShuAnggota::class);
    }

    public function sewaMobil()
    {
        return $this->hasMany(SewaMobil::class);
    }

    public function sewaPrinter()
    {
        return $this->hasMany(SewaPrinter::class, 'karyawan_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('status_kerja', self::STATUS_AKTIF);
    }

    public function isAnggotaAktif(): bool
    {
        $anggota = $this->relationLoaded('anggota')
            ? $this->anggota
            : $this->anggota()->first();

        return $this->status_kerja === self::STATUS_AKTIF
            && $anggota?->status === Anggota::STATUS_AKTIF;
    }

    public function mempunyaiTransaksi(): bool
    {
        return $this->penjualan()->exists()
            || $this->simpanan()->exists()
            || $this->pinjaman()->exists()
            || $this->pembagianShu()->exists()
            || $this->sewaMobil()->exists()
            || $this->sewaPrinter()->exists();
    }
}
