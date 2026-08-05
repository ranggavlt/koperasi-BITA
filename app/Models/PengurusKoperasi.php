<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengurusKoperasi extends Model
{
    use HasFactory;

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_NONAKTIF = 'nonaktif';

    public const KELOMPOK_PENGURUS = 'pengurus';

    public const KELOMPOK_PENGAWAS = 'pengawas';

    public const KELOMPOK_PEMBINA = 'pembina';

    public const JABATAN_PER_KELOMPOK = [
        self::KELOMPOK_PENGURUS => ['Ketua Pengurus', 'Sekretaris', 'Bendahara'],
        self::KELOMPOK_PENGAWAS => ['Ketua Pengawas', 'Anggota Pengawas'],
        self::KELOMPOK_PEMBINA => ['Pembina'],
    ];

    public const JABATAN = [
        'Ketua Pengurus', 'Sekretaris', 'Bendahara',
        'Ketua Pengawas', 'Anggota Pengawas',
        'Pembina',
    ];

    protected $table = 'pengurus_koperasi';

    protected $fillable = [
        'anggota_id',
        'jabatan',
        'kelompok',
        'status',
    ];

    public function anggota()
    {
        return $this->belongsTo(Anggota::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('status', self::STATUS_AKTIF);
    }

    public static function kelompokUntukJabatan(string $jabatan): string
    {
        foreach (self::JABATAN_PER_KELOMPOK as $kelompok => $daftarJabatan) {
            if (in_array($jabatan, $daftarJabatan, true)) {
                return $kelompok;
            }
        }

        return self::KELOMPOK_PENGURUS;
    }

    public function getKelompokLabelAttribute(): string
    {
        return ucfirst($this->kelompok ?: self::kelompokUntukJabatan($this->jabatan));
    }
}
