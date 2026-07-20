<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Simpanan extends Model
{
    public const METODE_POTONG_GAJI = 'potong_gaji';

    public const METODE_TUNAI = 'tunai';

    public const METODE_TRANSFER_BANK = 'transfer_bank';

    public const JENIS_SETORAN = 'setoran';

    public const JENIS_PENARIKAN = 'penarikan';

    public const STATUS_PENDING_PAYROLL = 'pending_payroll';

    public const STATUS_ALLOCATED = 'allocated';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_OUTSTANDING_CASH = 'outstanding_cash';

    public const STATUS_SETTLED_CASH = 'settled_cash';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_REVERSED_DUE_TO_EXIT = 'reversed_due_to_exit';

    public const STATUS_SETTLED_OFFSET = 'settled_offset';

    protected $table = 'simpanan';

    protected $fillable = [
        'idempotency_key',
        'kode_transaksi',
        'karyawan_id',
        'anggota_id',
        'pemakaian_potong_gaji_id',
        'jadwal_simpanan_wajib_id',
        'reversal_transaksi_id',
        'replacement_simpanan_id',
        'simpanan_pokok_anggota_id',
        'simpanan_pokok_siklus_id',
        'siklus_keanggotaan_id',
        'penyelesaian_keanggotaan_id',
        'jenis_simpanan_id',
        'kode_jenis_snapshot',
        'nama_jenis_snapshot',
        'nominal_snapshot',
        'jumlah',
        'jenis_transaksi',
        'dompet_id',
        'saldo_sebelum_snapshot',
        'saldo_sesudah_snapshot',
        'nomor_referensi',
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
        'saldo_sebelum_snapshot' => 'decimal:2',
        'saldo_sesudah_snapshot' => 'decimal:2',
        'tanggal' => 'date',
        'settled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Simpanan $simpanan): void {
            if (DB::connection()->getDriverName() !== 'mysql') {
                $activeSimpananPokok = $simpanan->kode_jenis_snapshot === JenisSimpanan::KODE_SIMPANAN_POKOK
                    && ! in_array($simpanan->status, [self::STATUS_REVERSED, self::STATUS_REVERSED_DUE_TO_EXIT], true);

                $simpanan->simpanan_pokok_anggota_id = $simpanan->kode_jenis_snapshot === JenisSimpanan::KODE_SIMPANAN_POKOK
                    && ! in_array($simpanan->status, [self::STATUS_REVERSED, self::STATUS_REVERSED_DUE_TO_EXIT], true)
                    ? $simpanan->anggota_id
                    : null;

                $simpanan->simpanan_pokok_siklus_id = $activeSimpananPokok
                    ? $simpanan->siklus_keanggotaan_id
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

    public function dompet()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_id');
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

    public function jadwalSimpananWajib()
    {
        return $this->belongsTo(JadwalSimpananWajib::class, 'jadwal_simpanan_wajib_id');
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

    public function siklusKeanggotaan()
    {
        return $this->belongsTo(SiklusKeanggotaan::class, 'siklus_keanggotaan_id');
    }

    public function penyelesaianKeanggotaan()
    {
        return $this->belongsTo(PenyelesaianKeanggotaan::class, 'penyelesaian_keanggotaan_id');
    }

    public function isSimpananPokok(): bool
    {
        return $this->kode_jenis_snapshot === JenisSimpanan::KODE_SIMPANAN_POKOK
            || $this->jenisSimpanan?->kode === JenisSimpanan::KODE_SIMPANAN_POKOK;
    }

    public function isSimpananWajib(): bool
    {
        return $this->kode_jenis_snapshot === JenisSimpanan::KODE_SIMPANAN_WAJIB
            || $this->jenisSimpanan?->kode === JenisSimpanan::KODE_SIMPANAN_WAJIB
            || $this->jenisSimpanan?->kategori === JenisSimpanan::KATEGORI_WAJIB;
    }

    public function isSimpananSukarela(): bool
    {
        return $this->kode_jenis_snapshot === JenisSimpanan::KODE_SIMPANAN_SUKARELA
            || $this->jenisSimpanan?->kode === JenisSimpanan::KODE_SIMPANAN_SUKARELA
            || $this->jenisSimpanan?->kategori === JenisSimpanan::KATEGORI_SUKARELA;
    }

    public function getJenisTransaksiLabelAttribute(): string
    {
        return match ($this->jenis_transaksi) {
            self::JENIS_SETORAN => 'Setoran',
            self::JENIS_PENARIKAN => 'Penarikan',
            default => '-',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_PAYROLL => 'Pending Payroll',
            self::STATUS_ALLOCATED => 'Dialokasikan',
            self::STATUS_SETTLED => 'Posted',
            self::STATUS_OUTSTANDING_CASH => 'Outstanding Tunai',
            self::STATUS_SETTLED_CASH => 'Lunas Tunai',
            self::STATUS_REVERSED => 'Dikoreksi',
            self::STATUS_REVERSED_DUE_TO_EXIT => 'Dikoreksi Keluar Anggota',
            self::STATUS_SETTLED_OFFSET => 'Diselesaikan Offset',
            default => str_replace('_', ' ', (string) $this->status),
        };
    }
}
