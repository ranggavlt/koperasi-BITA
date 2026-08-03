<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class KlaimDanaSosial extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DITOLAK = 'ditolak';
    public const STATUS_PAID = 'paid';
    public const STATUS_REVERSED = 'reversed';
    public const KATEGORI = ['meninggal', 'melahirkan', 'khitan', 'proposal_sosial'];

    protected $table = 'klaim_dana_sosial';
    protected $fillable = ['kode_klaim', 'anggota_id', 'karyawan_id', 'nama_penerima_snapshot', 'kategori', 'batas_klaim_id', 'batas_nominal_snapshot', 'batas_berlaku_snapshot', 'nominal', 'tanggal_pengajuan', 'keterangan', 'status', 'sumber_dana_sosial_id', 'dompet_id', 'metode_pembayaran', 'created_by', 'approved_by', 'paid_by', 'reversed_by', 'submitted_at', 'approved_at', 'approval_reason', 'rejected_at', 'paid_at', 'reversed_at', 'alasan_penolakan', 'reversal_reason', 'idempotency_key'];
    protected $casts = ['nominal' => 'decimal:2', 'batas_nominal_snapshot' => 'decimal:2', 'batas_berlaku_snapshot' => 'date', 'tanggal_pengajuan' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'paid_at' => 'datetime', 'reversed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Klaim Dana Sosial tidak boleh dihapus. Gunakan status penolakan atau reversal.'));
        static::updating(function (self $claim): void {
            if ($claim->getOriginal('status') === self::STATUS_PAID) {
                $allowed = ['status', 'reversed_by', 'reversed_at', 'reversal_reason', 'updated_at'];
                if (array_diff(array_keys($claim->getDirty()), $allowed) !== [] || $claim->status !== self::STATUS_REVERSED) {
                    throw new RuntimeException('Klaim yang sudah dibayar bersifat immutable. Gunakan reversal.');
                }
            }
        });
    }

    public function anggota() { return $this->belongsTo(Anggota::class); }
    public function karyawan() { return $this->belongsTo(Karyawan::class); }
    public function sumber() { return $this->belongsTo(DanaSosialSumber::class, 'sumber_dana_sosial_id'); }
    public function dompet() { return $this->belongsTo(DompetKoperasi::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function payer() { return $this->belongsTo(User::class, 'paid_by'); }
    public function reverser() { return $this->belongsTo(User::class, 'reversed_by'); }
    public function mutasiDana() { return $this->hasMany(MutasiDanaSosial::class, 'klaim_dana_sosial_id'); }
    public function mutasiKas() { return $this->morphMany(MutasiKas::class, 'referensi', 'referensi_tipe', 'referensi_id'); }
    public function jurnal() { return $this->morphMany(JurnalUmum::class, 'referensi', 'referensi_tipe', 'referensi_id'); }
    public function batasKlaim() { return $this->belongsTo(BatasKlaimDanaSosial::class, 'batas_klaim_id'); }
}
