<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class SewaPrinter extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_DIKONFIRMASI = 'dikonfirmasi';

    public const STATUS_BERJALAN = 'berjalan';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    public const PEMBAYARAN_BELUM_BAYAR = 'belum_bayar';

    public const PEMBAYARAN_PAID = 'paid';

    public const PEMBAYARAN_REFUNDED = 'refunded';

    protected $table = 'sewa_printer';

    protected $fillable = [
        'kode_sewa',
        'nama_perusahaan_snapshot',
        'perusahaan_id',
        'kode_perusahaan_snapshot',
        'model_sumber',
        'karyawan_id',
        'mulai_tanggal',
        'selesai_tanggal',
        'kebutuhan',
        'vendor_nama',
        'vendor_kontak',
        'vendor_alamat',
        'total_harga_vendor',
        'total_margin',
        'total_tagihan_perusahaan',
        'status',
        'status_pembayaran',
        'confirmed_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'alasan_pembatalan',
        'keterangan',
        'recorded_by',
        'created_by',
        'updated_by',
        'confirmed_by',
        'idempotency_key',
    ];

    protected $casts = [
        'mulai_tanggal' => 'date',
        'selesai_tanggal' => 'date',
        'total_harga_vendor' => 'integer',
        'total_margin' => 'integer',
        'total_tagihan_perusahaan' => 'integer',
        'confirmed_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuntimeException('Transaksi Sewa Printer tidak boleh dihapus permanen. Gunakan pembatalan/refund.');
        });
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_DIKONFIRMASI,
            self::STATUS_BERJALAN,
            self::STATUS_SELESAI,
            self::STATUS_DIBATALKAN,
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_DIKONFIRMASI => 'Dikonfirmasi',
            self::STATUS_BERJALAN => 'Berjalan',
            self::STATUS_SELESAI => 'Selesai',
            self::STATUS_DIBATALKAN => 'Dibatalkan',
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

    public function details()
    {
        return $this->hasMany(SewaPrinterDetail::class, 'sewa_printer_id');
    }

    public function karyawanPic()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id');
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id');
    }

    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class);
    }

    public function pembayaranVendor()
    {
        return $this->morphOne(PembayaranVendorSewa::class, 'sewa');
    }

    public function invoiceDetail()
    {
        return $this->morphOne(InvoicePenagihanDetail::class, 'referensi');
    }

    public function pembayaran()
    {
        return $this->hasOne(PembayaranSewaPrinter::class, 'sewa_printer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function jurnal()
    {
        return $this->morphMany(JurnalUmum::class, 'referensi', 'referensi_tipe', 'referensi_id');
    }

    public function scopeBlockingSchedule($query)
    {
        return $query->whereIn('status', [self::STATUS_DIKONFIRMASI, self::STATUS_BERJALAN]);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }



    public function getTotalHargaDasarAttribute(): int
    {
        return (int) ($this->total_harga_vendor ?? 0);
    }

    public function getGrandTotalAttribute(): int
    {
        return (int) ($this->total_tagihan_perusahaan ?? 0);
    }
}
