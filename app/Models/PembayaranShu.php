<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PembayaranShu extends Model
{
    public const STATUS_PAID = 'paid';
    public const STATUS_REVERSED = 'reversed';

    protected $table = 'pembayaran_shu';
    protected $guarded = [];
    protected $casts = ['jumlah' => 'decimal:2', 'tanggal_bayar' => 'date', 'reversed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $payment): void {
            $allowed = [
                'status', 'reversal_mutasi_kas_id', 'reversal_jurnal_id', 'reversed_by',
                'reversed_at', 'reversal_reason', 'updated_at',
            ];
            if (array_diff(array_keys($payment->getDirty()), $allowed) !== []) {
                throw new RuntimeException('Pembayaran SHU final tidak boleh diubah. Gunakan reversal resmi.');
            }
        });
        static::deleting(fn () => throw new RuntimeException('Pembayaran SHU final tidak boleh dihapus.'));
    }

    public function penerima() { return $this->belongsTo(ShuPenerima::class, 'shu_penerima_id'); }
    public function dompet() { return $this->belongsTo(DompetKoperasi::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function reverser() { return $this->belongsTo(User::class, 'reversed_by'); }
    public function mutasi() { return $this->belongsTo(MutasiKas::class, 'mutasi_kas_id'); }
    public function jurnalPembayaran() { return $this->belongsTo(JurnalUmum::class, 'jurnal_id'); }
    public function reversalMutasi() { return $this->belongsTo(MutasiKas::class, 'reversal_mutasi_kas_id'); }
    public function reversalJurnal() { return $this->belongsTo(JurnalUmum::class, 'reversal_jurnal_id'); }
    public function mutasiKas() { return $this->morphMany(MutasiKas::class, 'referensi', 'referensi_tipe', 'referensi_id'); }
    public function jurnal() { return $this->morphMany(JurnalUmum::class, 'referensi', 'referensi_tipe', 'referensi_id'); }
}
