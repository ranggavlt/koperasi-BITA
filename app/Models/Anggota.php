<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anggota extends Model
{
    use HasFactory;

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_NONAKTIF = 'nonaktif';

    protected $table = 'anggota';

    protected $fillable = [
        'karyawan_id',
        'nomor_anggota',
        'tanggal_bergabung',
        'alamat',
        'status',
        'tanggal_nonaktif',
        'plafon_pinjaman',
    ];

    protected $casts = [
        'tanggal_bergabung' => 'date',
        'tanggal_nonaktif' => 'date',
        'plafon_pinjaman' => 'decimal:2',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function pengurus()
    {
        return $this->hasMany(PengurusKoperasi::class);
    }

    public function pengurusAktif()
    {
        return $this->hasOne(PengurusKoperasi::class)
            ->where('status', PengurusKoperasi::STATUS_AKTIF);
    }

    public function limitsPotongGaji()
    {
        return $this->hasMany(LimitPotongGajiAnggota::class);
    }

    public function overrideLimitPotongGaji()
    {
        return $this->hasOne(OverrideLimitPotongGajiAnggota::class);
    }

    public function penjualan()
    {
        return $this->hasMany(Penjualan::class);
    }

    public function simpanan()
    {
        return $this->hasMany(Simpanan::class);
    }

    public function saldoSimpananManasuka()
    {
        return $this->hasMany(SaldoSimpananManasuka::class, 'anggota_id');
    }

    public function jadwalSimpananWajib()
    {
        return $this->hasMany(JadwalSimpananWajib::class, 'anggota_id');
    }

    public function siklusKeanggotaan()
    {
        return $this->hasMany(SiklusKeanggotaan::class, 'anggota_id')
            ->orderBy('siklus_ke');
    }

    public function siklusAktif()
    {
        return $this->hasOne(SiklusKeanggotaan::class, 'anggota_id')
            ->where('status', SiklusKeanggotaan::STATUS_ACTIVE);
    }

    public function penyelesaianKeanggotaan()
    {
        return $this->hasMany(PenyelesaianKeanggotaan::class, 'anggota_id');
    }

    public function pinjaman()
    {
        return $this->hasMany(Pinjaman::class);
    }

    public function pembagianShu()
    {
        return $this->hasMany(ShuAnggota::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('status', self::STATUS_AKTIF);
    }

    public function mempunyaiTransaksi(): bool
    {
        return $this->penjualan()->exists()
            || $this->simpanan()->exists()
            || $this->pinjaman()->exists()
            || $this->pembagianShu()->exists()
            || ($this->karyawan?->mempunyaiTransaksi() ?? false);
    }
}
