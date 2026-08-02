<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class SewaMobil extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_DIAJUKAN = 'diajukan';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    public const STATUS_BERJALAN = 'berjalan';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    public const STATUS_REFUNDED = 'refunded';

    public const PEMBAYARAN_BELUM_BAYAR = 'belum_bayar';

    public const PEMBAYARAN_PAID = 'paid';

    public const PEMBAYARAN_REFUNDED = 'refunded';

    protected $table = 'sewa_mobil';

    protected $fillable = [
        'kode_sewa',
        'aset_koperasi_id',
        'karyawan_id',
        'pemohon_user_id',
        'recorded_by',
        'nama_perusahaan_snapshot',
        'nama_kegiatan',
        'lokasi_kegiatan',
        'vendor_nama',
        'vendor_kontak',
        'vendor_alamat',
        'jenis_kendaraan',
        'merek_kendaraan',
        'model_kendaraan',
        'plat_nomor_snapshot',
        'plat_nomor_normalized',
        'tahun_kendaraan',
        'warna_kendaraan',
        'keterangan_kendaraan',
        'tanggal_mulai',
        'tanggal_selesai',
        'jumlah_hari',
        'tarif_harian_snapshot',
        'total_sewa',
        'total_harga_vendor',
        'total_markup',
        'total_tagihan_perusahaan',
        'status',
        'status_pembayaran',
        'pengurus_penyetuju_id',
        'nama_pengurus_snapshot',
        'jabatan_pengurus_snapshot',
        'approval_recorded_by',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'started_at',
        'started_by',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'refunded_at',
        'refunded_by',
        'refund_reason',
        'reversal_transaksi_id',
        'alasan_penolakan',
        'alasan_pembatalan',
        'keterangan',
        'needs_finance_review',
        'created_by',
        'updated_by',
        'idempotency_key',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'jumlah_hari' => 'integer',
        'tarif_harian_snapshot' => 'integer',
        'total_sewa' => 'integer',
        'total_harga_vendor' => 'integer',
        'total_markup' => 'integer',
        'total_tagihan_perusahaan' => 'integer',
        'tahun_kendaraan' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'needs_finance_review' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuntimeException('Transaksi Sewa Mobil tidak boleh dihapus permanen. Gunakan pembatalan/refund.');
        });
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_DIAJUKAN,
            self::STATUS_DISETUJUI,
            self::STATUS_DITOLAK,
            self::STATUS_BERJALAN,
            self::STATUS_SELESAI,
            self::STATUS_DIBATALKAN,
            self::STATUS_REFUNDED,
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_DIAJUKAN => 'Diajukan',
            self::STATUS_DISETUJUI => 'Disetujui',
            self::STATUS_DITOLAK => 'Ditolak',
            self::STATUS_BERJALAN => 'Berjalan',
            self::STATUS_SELESAI => 'Selesai',
            self::STATUS_DIBATALKAN => 'Dibatalkan',
            self::STATUS_REFUNDED => 'Refunded',
        ];
    }

    public static function paymentStatuses(): array
    {
        return [
            self::PEMBAYARAN_BELUM_BAYAR,
            self::PEMBAYARAN_PAID,
            self::PEMBAYARAN_REFUNDED,
        ];
    }

    public function aset()
    {
        return $this->belongsTo(AsetKoperasi::class, 'aset_koperasi_id');
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function pemohon()
    {
        return $this->belongsTo(User::class, 'pemohon_user_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function pengurusPenyetuju()
    {
        return $this->belongsTo(PengurusKoperasi::class, 'pengurus_penyetuju_id');
    }

    public function approvalRecorder()
    {
        return $this->belongsTo(User::class, 'approval_recorded_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function reversal()
    {
        return $this->belongsTo(ReversalTransaksi::class, 'reversal_transaksi_id');
    }

    public function pembayaran()
    {
        return $this->hasOne(PembayaranSewaMobil::class, 'sewa_mobil_id');
    }

    public function mutasiKas()
    {
        return $this->morphMany(MutasiKas::class, 'referensi', 'referensi_tipe', 'referensi_id');
    }

    public function jurnal()
    {
        return $this->morphMany(JurnalUmum::class, 'referensi', 'referensi_tipe', 'referensi_id');
    }

    public function scopeOwnedByUser($query, int $userId)
    {
        return $query->where('pemohon_user_id', $userId);
    }

    public function scopeBlockingSchedule($query)
    {
        return $query->whereIn('status', [
            self::STATUS_DIAJUKAN,
            self::STATUS_DISETUJUI,
            self::STATUS_BERJALAN,
            self::STATUS_SELESAI,
        ]);
    }

    public function getTarifTotalAttribute(): int
    {
        return (int) ($this->total_tagihan_perusahaan ?? $this->total_sewa ?? 0);
    }

    public function getVehicleLabelAttribute(): string
    {
        $parts = array_filter([
            $this->jenis_kendaraan,
            $this->merek_kendaraan,
            $this->model_kendaraan,
        ]);

        return $parts === [] ? '-' : implode(' ', $parts);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }
}
