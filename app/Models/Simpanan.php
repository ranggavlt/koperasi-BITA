<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Simpanan extends Model
{
    public const METODE_POTONG_GAJI = 'potong_gaji';

    public const METODE_TUNAI = 'tunai';

    public const STATUS_PENDING_PAYROLL = 'pending_payroll';

    public const STATUS_ALLOCATED = 'allocated';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_OUTSTANDING_CASH = 'outstanding_cash';

    public const STATUS_SETTLED_CASH = 'settled_cash';

    public const STATUS_REVERSED = 'reversed';

    protected $table = 'simpanan';

    protected $fillable = [
        'idempotency_key',
        'karyawan_id',
        'anggota_id',
        'pemakaian_potong_gaji_id',
        'reversal_transaksi_id',
        'replacement_simpanan_id',
        'simpanan_pokok_anggota_id',
        'jenis_simpanan_id',
        'kode_jenis_snapshot',
        'nama_jenis_snapshot',
        'nominal_snapshot',
        'jumlah',
        'metode_pembayaran',
        'status',
        'tanggal',
        'settled_at',
        'created_by',
        'keterangan'
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
        'nominal_snapshot' => 'decimal:2',
        'tanggal' => 'date',
        'settled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Simpanan $simpanan): void {
            if (DB::connection()->getDriverName() !== 'mysql') {
                $simpanan->simpanan_pokok_anggota_id = $simpanan->kode_jenis_snapshot === JenisSimpanan::KODE_SIMPANAN_POKOK
                    && $simpanan->status !== self::STATUS_REVERSED
                    ? $simpanan->anggota_id
                    : null;
            }
        });

        static::deleting(function (Simpanan $_simpanan): void {
            throw new RuntimeException('Simpanan tidak boleh dihapus permanen.');
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

    public function jenisSimpanan()
    {
        return $this->belongsTo(JenisSimpanan::class);
    }

    public function mutasiKas()
    {
        return $this->hasOne(MutasiKas::class, 'referensi_id')
            ->where('referensi_tipe', self::class);
    }

    public function ledger()
    {
        return $this->belongsTo(PemakaianPotongGaji::class, 'pemakaian_potong_gaji_id');
    }

    public function jurnal()
    {
        return $this->hasOne(JurnalUmum::class, 'referensi_id')
            ->where('referensi_tipe', self::class);
    }

    public function reversal()
    {
        return $this->belongsTo(ReversalTransaksi::class, 'reversal_transaksi_id');
    }

    public function replacement()
    {
        return $this->belongsTo(self::class, 'replacement_simpanan_id');
    }

    public function isSimpananPokok(): bool
    {
        return $this->kode_jenis_snapshot === JenisSimpanan::KODE_SIMPANAN_POKOK
            || $this->jenisSimpanan?->kode === JenisSimpanan::KODE_SIMPANAN_POKOK;
    }
}
