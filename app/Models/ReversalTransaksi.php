<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ReversalTransaksi extends Model
{
    public const STATUS_PROCESSED = 'processed';

    public const STATUS_CANCELLED = 'cancelled';

    public const JENIS_POS_PAYROLL_CANCEL = 'pos_payroll_cancel';

    public const JENIS_POS_PAYROLL_REFUND_CREDIT = 'pos_payroll_refund_credit';

    public const JENIS_POS_PAYROLL_REFUND_CASH = 'pos_payroll_refund_cash';

    public const JENIS_POS_NON_PAYROLL_REFUND = 'pos_non_payroll_refund';

    public const JENIS_SIMPANAN_POKOK_CORRECTION = 'simpanan_pokok_correction';

    public const JENIS_SIMPANAN_MANASUKA_CORRECTION = 'simpanan_manasuka_correction';

    public const JENIS_SIMPANAN_WAJIB_EXIT_CANCEL = 'simpanan_wajib_exit_cancel';

    public const JENIS_CICILAN_PAYROLL_REVERSAL = 'cicilan_payroll_reversal';

    public const JENIS_CICILAN_CASH_REVERSAL = 'cicilan_cash_reversal';

    public const JENIS_BEBAN_OPERASIONAL_REVERSAL = 'beban_operasional_reversal';

    public const JENIS_SIMPANAN_POKOK_EXIT_CANCEL = 'simpanan_pokok_exit_cancel';

    public const JENIS_SEWA_HARDWARE_REFUND = 'sewa_hardware_refund';

    protected $table = 'reversal_transaksi';

    protected $fillable = [
        'kode_reversal',
        'source_type',
        'source_id',
        'jenis_reversal',
        'nominal',
        'alasan',
        'status',
        'original_ledger_id',
        'original_jurnal_id',
        'original_mutasi_id',
        'target_periode_potong_gaji_id',
        'dompet_refund_id',
        'reversal_of_id',
        'created_by',
        'processed_by',
        'processed_at',
        'idempotency_key',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ReversalTransaksi $_reversal): void {
            throw new RuntimeException('Reversal transaksi tidak boleh dihapus permanen.');
        });
    }

    public function source()
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function originalLedger()
    {
        return $this->belongsTo(PemakaianPotongGaji::class, 'original_ledger_id');
    }

    public function originalJurnal()
    {
        return $this->belongsTo(JurnalUmum::class, 'original_jurnal_id');
    }

    public function originalMutasi()
    {
        return $this->belongsTo(MutasiKas::class, 'original_mutasi_id');
    }

    public function targetPeriode()
    {
        return $this->belongsTo(PeriodePotongGaji::class, 'target_periode_potong_gaji_id');
    }

    public function dompetRefund()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_refund_id');
    }

    public function kreditPayroll()
    {
        return $this->hasOne(KreditPotongGajiAnggota::class, 'reversal_transaksi_id');
    }
}
