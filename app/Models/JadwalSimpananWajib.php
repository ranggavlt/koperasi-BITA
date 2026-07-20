<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use RuntimeException;

class JadwalSimpananWajib extends Model
{
    use HasFactory;

    public const STATUS_OUTSTANDING = 'outstanding';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_CANCELLED_EXIT = 'cancelled_exit';

    protected $table = 'jadwal_simpanan_wajib';

    protected $fillable = [
        'kode_tagihan',
        'anggota_id',
        'siklus_keanggotaan_id',
        'jenis_simpanan_id',
        'periode',
        'nominal_snapshot',
        'interval_bulan_snapshot',
        'kode_jenis_snapshot',
        'nama_jenis_snapshot',
        'status',
        'reserved_at',
        'settled_at',
        'created_by',
        'settled_by',
        'penyelesaian_keanggotaan_id',
        'cancellation_reversal_id',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'recovery_jurnal_id',
        'recovered_at',
        'recovered_by',
        'recovery_reason',
    ];

    protected $casts = [
        'periode' => 'date',
        'nominal_snapshot' => 'decimal:2',
        'interval_bulan_snapshot' => 'integer',
        'reserved_at' => 'datetime',
        'settled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'recovered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuntimeException('Jadwal Simpanan Wajib tidak boleh dihapus permanen.');
        });
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'anggota_id');
    }

    public function siklusKeanggotaan()
    {
        return $this->belongsTo(SiklusKeanggotaan::class, 'siklus_keanggotaan_id');
    }

    public function jenisSimpanan()
    {
        return $this->belongsTo(JenisSimpanan::class, 'jenis_simpanan_id');
    }

    public function simpanan()
    {
        return $this->hasOne(Simpanan::class, 'jadwal_simpanan_wajib_id');
    }

    public function pemakaian()
    {
        return $this->hasMany(PemakaianPotongGaji::class, 'source_id')
            ->where('source_type', self::class)
            ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB);
    }

    public function activeLedger()
    {
        return $this->hasOne(PemakaianPotongGaji::class, 'source_id')
            ->where('source_type', self::class)
            ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
            ->whereIn('status', [
                PemakaianPotongGaji::STATUS_RESERVED,
                PemakaianPotongGaji::STATUS_CONSUMED,
                PemakaianPotongGaji::STATUS_SETTLED,
            ]);
    }

    public function penyelesaianKeanggotaan()
    {
        return $this->belongsTo(PenyelesaianKeanggotaan::class, 'penyelesaian_keanggotaan_id');
    }

    public function cancellationReversal()
    {
        return $this->belongsTo(ReversalTransaksi::class, 'cancellation_reversal_id');
    }

    public function recoveryJurnal()
    {
        return $this->belongsTo(JurnalUmum::class, 'recovery_jurnal_id');
    }

    public function scopeOutstanding($query)
    {
        return $query->where('status', self::STATUS_OUTSTANDING);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OUTSTANDING => 'Tunggakan/Belum Dialokasikan',
            self::STATUS_RESERVED => 'Sudah Dialokasikan',
            self::STATUS_SETTLED => 'Sudah Dibayar Payroll',
            self::STATUS_CANCELLED_EXIT => 'Dibatalkan karena Keanggotaan Berakhir',
            default => str_replace('_', ' ', (string) $this->status),
        };
    }
}
