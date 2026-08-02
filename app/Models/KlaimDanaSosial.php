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
    public const KATEGORI = ['meninggal', 'melahirkan', 'khitan', 'proposal_sosial'];

    protected $table = 'klaim_dana_sosial';
    protected $fillable = ['kode_klaim', 'anggota_id', 'karyawan_id', 'nama_penerima_snapshot', 'kategori', 'nominal', 'tanggal_pengajuan', 'keterangan', 'status', 'sumber_dana_sosial_id', 'dompet_id', 'metode_pembayaran', 'created_by', 'approved_by', 'paid_by', 'submitted_at', 'approved_at', 'rejected_at', 'paid_at', 'alasan_penolakan', 'idempotency_key'];
    protected $casts = ['nominal' => 'decimal:2', 'tanggal_pengajuan' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'paid_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Klaim Dana Sosial tidak boleh dihapus. Gunakan status penolakan atau reversal.'));
    }

    public function anggota() { return $this->belongsTo(Anggota::class); }
    public function karyawan() { return $this->belongsTo(Karyawan::class); }
    public function sumber() { return $this->belongsTo(DanaSosialSumber::class, 'sumber_dana_sosial_id'); }
    public function dompet() { return $this->belongsTo(DompetKoperasi::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function payer() { return $this->belongsTo(User::class, 'paid_by'); }
    public function mutasiDana() { return $this->hasOne(MutasiDanaSosial::class, 'klaim_dana_sosial_id'); }
    public function mutasiKas() { return $this->morphMany(MutasiKas::class, 'referensi', 'referensi_tipe', 'referensi_id'); }
    public function jurnal() { return $this->morphMany(JurnalUmum::class, 'referensi', 'referensi_tipe', 'referensi_id'); }
}
