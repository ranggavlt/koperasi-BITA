<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Pinjaman extends Model
{
    use HasFactory;

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_LUNAS = 'lunas';

    protected $table = 'pinjaman';

    protected $fillable = [
        'kode_pinjaman',
        'karyawan_id',
        'anggota_id',
        'dompet_id',
        'jumlah_pinjaman',
        'plafon_pinjaman_snapshot',
        'bunga_persen',
        'tenor_bulan',
        'sisa_pinjaman',
        'status',
        'tanggal_pinjaman',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'jumlah_pinjaman' => 'decimal:2',
        'plafon_pinjaman_snapshot' => 'decimal:2',
        'bunga_persen' => 'decimal:2',
        'sisa_pinjaman' => 'decimal:2',
        'tanggal_pinjaman' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Pinjaman $pinjaman): void {
            if (DB::connection()->getDriverName() !== 'mysql') {
                $pinjaman->anggota_aktif_id = $pinjaman->status === self::STATUS_AKTIF
                    ? $pinjaman->anggota_id
                    : null;
            }
        });

        static::deleting(function (Pinjaman $_pinjaman): void {
            throw new RuntimeException('Pinjaman tidak boleh dihapus permanen. Gunakan mekanisme pelunasan/koreksi.');
        });
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class);
    }

    public function dompet()
    {
        return $this->belongsTo(DompetKoperasi::class);
    }

    public function cicilan()
    {
        return $this->hasMany(CicilanPinjaman::class);
    }

    public function jadwalCicilan()
    {
        return $this->hasMany(JadwalCicilanPinjaman::class)->orderBy('angsuran_ke');
    }

    public function mutasiKas()
    {
        return $this->hasOne(MutasiKas::class, 'referensi_id')
            ->where('referensi_tipe', self::class);
    }

    public function jurnal()
    {
        return $this->hasOne(JurnalUmum::class, 'referensi_id')
            ->where('referensi_tipe', self::class);
    }
}
