<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Pinjaman extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_DIAJUKAN = 'diajukan';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_LUNAS = 'lunas';

    public const STATUS_DITOLAK = 'ditolak';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $table = 'pinjaman';

    protected $fillable = [
        'kode_pinjaman',
        'karyawan_id',
        'anggota_id',
        'siklus_keanggotaan_id',
        'anggota_pinjaman_terbuka_id',
        'dompet_id',
        'jumlah_pinjaman',
        'plafon_pinjaman_snapshot',
        'bunga_persen',
        'tenor_bulan',
        'sisa_pinjaman',
        'status',
        'tanggal_pinjaman',
        'tanggal_pengajuan',
        'keterangan',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'disbursed_by',
        'disbursed_at',
    ];

    protected $casts = [
        'jumlah_pinjaman' => 'decimal:2',
        'plafon_pinjaman_snapshot' => 'decimal:2',
        'bunga_persen' => 'decimal:2',
        'sisa_pinjaman' => 'decimal:2',
        'tanggal_pinjaman' => 'date',
        'tanggal_pengajuan' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'disbursed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Pinjaman $pinjaman): void {
            if (DB::connection()->getDriverName() !== 'mysql') {
                $pinjaman->anggota_aktif_id = $pinjaman->status === self::STATUS_AKTIF
                    ? $pinjaman->anggota_id
                    : null;

                $pinjaman->anggota_pinjaman_terbuka_id = self::isOpenStatus((string) $pinjaman->status)
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

    public function siklusKeanggotaan()
    {
        return $this->belongsTo(SiklusKeanggotaan::class, 'siklus_keanggotaan_id');
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

    /**
     * @return array<int, string>
     */
    public static function openStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_DIAJUKAN,
            self::STATUS_DISETUJUI,
            self::STATUS_AKTIF,
        ];
    }

    public static function isOpenStatus(string $status): bool
    {
        return in_array($status, self::openStatuses(), true);
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_DIAJUKAN => 'Diajukan',
            self::STATUS_DISETUJUI => 'Disetujui',
            self::STATUS_AKTIF => 'Aktif',
            self::STATUS_LUNAS => 'Lunas',
            self::STATUS_DITOLAK => 'Ditolak',
            self::STATUS_DIBATALKAN => 'Dibatalkan',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }
}
