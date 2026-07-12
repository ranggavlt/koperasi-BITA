<?php

namespace App\Services;

use App\Models\AlokasiKreditPotongGaji;
use App\Models\Anggota;
use App\Models\CicilanPinjaman;
use App\Models\DetailPenjualan;
use App\Models\DompetKoperasi;
use App\Models\HutangReseller;
use App\Models\JadwalCicilanPinjaman;
use App\Models\Karyawan;
use App\Models\KreditPotongGajiAnggota;
use App\Models\MutasiKas;
use App\Models\Pembayaran;
use App\Models\PembayaranOutstandingCash;
use App\Models\PemakaianPotongGaji;
use App\Models\Penjualan;
use App\Models\Pinjaman;
use App\Models\Produk;
use App\Models\ReversalTransaksi;
use App\Models\Simpanan;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TransaksiReversalService
{
    public function __construct(private readonly AkuntansiService $akuntansiService)
    {
    }

    public function cancelPendingPayrollPos(Penjualan $penjualan, string $alasan, int $userId): ReversalTransaksi
    {
        return DB::transaction(function () use ($penjualan, $alasan, $userId): ReversalTransaksi {
            $locked = Penjualan::query()
                ->with(['pembayaran.ledger.limit', 'details.produk', 'details.hutangReseller', 'jurnal'])
                ->lockForUpdate()
                ->findOrFail($penjualan->id);

            $this->assertReason($alasan);
            $this->assertNoReversal($locked);

            $payment = Pembayaran::query()
                ->where('penjualan_id', $locked->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->metode_pembayaran !== Pembayaran::METODE_POTONG_GAJI || $payment->status !== Pembayaran::STATUS_PENDING_PAYROLL) {
                throw ValidationException::withMessages([
                    'penjualan' => 'Hanya POS payroll pending yang dapat dibatalkan sebelum payroll confirmed.',
                ]);
            }

            $ledger = PemakaianPotongGaji::query()->lockForUpdate()->findOrFail($payment->pemakaian_potong_gaji_id);
            $limit = $ledger->limit()->lockForUpdate()->firstOrFail();

            if ($ledger->status !== PemakaianPotongGaji::STATUS_CONSUMED || $limit->status === \App\Models\LimitPotongGajiAnggota::STATUS_CONFIRMED) {
                throw ValidationException::withMessages([
                    'penjualan' => 'POS payroll ini sudah tidak eligible untuk pembatalan pending.',
                ]);
            }

            $this->assertResellerSettlementReversible($locked);
            $this->restoreStockOnce($locked);
            $this->cancelResellerDebt($locked);

            $reversal = $this->createReversal(
                $locked,
                ReversalTransaksi::JENIS_POS_PAYROLL_CANCEL,
                $this->decimalToCents($locked->grand_total),
                $alasan,
                $userId,
                [
                    'original_ledger_id' => $ledger->id,
                    'original_jurnal_id' => $locked->jurnal?->id,
                    'idempotency_key' => 'reversal:pos-pending:' . $locked->id,
                ]
            );

            $this->akuntansiService->recordPosReversal($reversal, $locked, 'piutang_potong_gaji', null, $userId);

            $ledger->update([
                'status' => PemakaianPotongGaji::STATUS_REVERSED,
                'reversed_by' => $userId,
                'reversed_at' => $this->now(),
                'reversal_transaksi_id' => $reversal->id,
                'updated_by' => $userId,
            ]);

            $payment->update([
                'status' => Pembayaran::STATUS_CANCELLED,
                'reversal_transaksi_id' => $reversal->id,
            ]);

            $locked->update([
                'status' => Penjualan::STATUS_CANCELLED,
                'reversal_transaksi_id' => $reversal->id,
                'reversed_by' => $userId,
                'reversed_at' => $this->now(),
            ]);

            return $reversal->fresh(['originalLedger', 'originalJurnal']);
        });
    }

    public function refundPos(Penjualan $penjualan, string $alasan, int $userId, ?DompetKoperasi $dompetRefund = null): ReversalTransaksi
    {
        return DB::transaction(function () use ($penjualan, $alasan, $userId, $dompetRefund): ReversalTransaksi {
            $locked = Penjualan::query()
                ->with(['anggota.karyawan', 'pembayaran.ledger.limit', 'details.produk', 'details.hutangReseller', 'jurnal', 'mutasiKas'])
                ->lockForUpdate()
                ->findOrFail($penjualan->id);

            $this->assertReason($alasan);
            $this->assertNoReversal($locked);

            $payment = Pembayaran::query()
                ->where('penjualan_id', $locked->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->metode_pembayaran === Pembayaran::METODE_POTONG_GAJI && $payment->status === Pembayaran::STATUS_PENDING_PAYROLL) {
                return $this->cancelPendingPayrollPos($locked, $alasan, $userId);
            }

            $this->assertResellerSettlementReversible($locked);
            $this->restoreStockOnce($locked);
            $this->cancelResellerDebt($locked);

            if ($payment->metode_pembayaran === Pembayaran::METODE_POTONG_GAJI) {
                return $this->refundConfirmedPayrollPos($locked, $payment, $alasan, $userId, $dompetRefund);
            }

            return $this->refundNonPayrollPos($locked, $payment, $alasan, $userId);
        });
    }

    public function correctPendingSimpananPokok(Simpanan $simpanan, string $alasan, int $userId, ?int $nominalPengganti = null): ReversalTransaksi
    {
        return DB::transaction(function () use ($simpanan, $alasan, $userId, $nominalPengganti): ReversalTransaksi {
            $locked = Simpanan::query()
                ->with(['anggota.karyawan', 'jenisSimpanan', 'ledger', 'jurnal'])
                ->lockForUpdate()
                ->findOrFail($simpanan->id);

            $this->assertReason($alasan);

            if (! $locked->isSimpananPokok()) {
                throw ValidationException::withMessages(['simpanan' => 'Koreksi ini hanya untuk Simpanan Pokok.']);
            }

            if ($locked->reversal_transaksi_id || $locked->status === Simpanan::STATUS_REVERSED) {
                throw ValidationException::withMessages(['simpanan' => 'Simpanan Pokok ini sudah mempunyai reversal.']);
            }

            $originalStatus = $locked->status;

            if ($locked->anggota?->status === Anggota::STATUS_AKTIF && $nominalPengganti === null && $locked->status === Simpanan::STATUS_SETTLED) {
                throw ValidationException::withMessages([
                    'simpanan' => 'Simpanan Pokok Anggota aktif tidak boleh direfund. Gunakan koreksi dengan transaksi pengganti.',
                ]);
            }

            $nominalLamaCents = $this->decimalToCents($locked->nominal_snapshot ?? $locked->jumlah);
            $reversal = $this->createReversal(
                $locked,
                ReversalTransaksi::JENIS_SIMPANAN_POKOK_CORRECTION,
                $nominalLamaCents,
                $alasan,
                $userId,
                [
                    'original_ledger_id' => $locked->pemakaian_potong_gaji_id,
                    'original_jurnal_id' => $locked->jurnal?->id,
                    'idempotency_key' => 'reversal:simpanan-pokok:' . $locked->id,
                ]
            );

            if ($locked->ledger && in_array($locked->ledger->status, [PemakaianPotongGaji::STATUS_CONSUMED, PemakaianPotongGaji::STATUS_RESERVED], true)) {
                $locked->ledger->update([
                    'status' => PemakaianPotongGaji::STATUS_REVERSED,
                    'reversed_by' => $userId,
                    'reversed_at' => $this->now(),
                    'reversal_transaksi_id' => $reversal->id,
                    'updated_by' => $userId,
                ]);
            }

            $this->akuntansiService->recordSimpananPokokReversal($locked, $reversal, $userId);

            $locked->update([
                'status' => Simpanan::STATUS_REVERSED,
                'reversal_transaksi_id' => $reversal->id,
            ]);

            if ($nominalPengganti !== null) {
                $replacement = Simpanan::query()->create([
                    'idempotency_key' => 'simpanan-pokok:replacement:' . $locked->id,
                    'karyawan_id' => $locked->karyawan_id,
                    'anggota_id' => $locked->anggota_id,
                    'siklus_keanggotaan_id' => $locked->siklus_keanggotaan_id,
                    'jenis_simpanan_id' => $locked->jenis_simpanan_id,
                    'kode_jenis_snapshot' => $locked->kode_jenis_snapshot,
                    'nama_jenis_snapshot' => $locked->nama_jenis_snapshot,
                    'nominal_snapshot' => $this->decimalFromCents($nominalPengganti * 100),
                    'jumlah' => $this->decimalFromCents($nominalPengganti * 100),
                    'metode_pembayaran' => Simpanan::METODE_POTONG_GAJI,
                    'status' => Simpanan::STATUS_PENDING_PAYROLL,
                    'tanggal' => $this->today(),
                    'created_by' => $userId,
                    'keterangan' => 'Pengganti koreksi Simpanan Pokok #' . $locked->id,
                ]);

                $this->akuntansiService->recordSimpananPokokPayroll($replacement, $userId);

                $locked->update(['replacement_simpanan_id' => $replacement->id]);

                $selisihCents = ($nominalPengganti * 100) - $nominalLamaCents;
                if ($originalStatus === Simpanan::STATUS_SETTLED && $selisihCents < 0) {
                    $this->createCredit($locked->anggota, $reversal, abs($selisihCents), $userId);
                }
            }

            return $reversal->fresh(['kreditPayroll']);
        });
    }

    public function reverseCicilan(CicilanPinjaman $cicilan, string $alasan, int $userId, ?DompetKoperasi $dompetRefund = null): ReversalTransaksi
    {
        return DB::transaction(function () use ($cicilan, $alasan, $userId, $dompetRefund): ReversalTransaksi {
            $payment = CicilanPinjaman::query()
                ->with(['pinjaman.anggota.karyawan', 'jadwal', 'dompet', 'jurnal', 'mutasiKas'])
                ->lockForUpdate()
                ->findOrFail($cicilan->id);

            $this->assertReason($alasan);

            if ($payment->status !== CicilanPinjaman::STATUS_SUDAH_BAYAR || ! $payment->jadwal) {
                throw ValidationException::withMessages(['cicilan' => 'Hanya cicilan paid yang dapat direversal.']);
            }

            if ($payment->reversal_transaksi_id || ReversalTransaksi::query()->where('source_type', CicilanPinjaman::class)->where('source_id', $payment->id)->exists()) {
                throw ValidationException::withMessages(['cicilan' => 'Cicilan ini sudah pernah direversal.']);
            }

            $laterPaidExists = JadwalCicilanPinjaman::query()
                ->where('pinjaman_id', $payment->pinjaman_id)
                ->where('angsuran_ke', '>', $payment->jadwal->angsuran_ke)
                ->where('status', JadwalCicilanPinjaman::STATUS_PAID)
                ->exists();

            if ($laterPaidExists) {
                throw ValidationException::withMessages([
                    'cicilan' => 'Reversal otomatis ditolak karena sudah ada pembayaran cicilan setelah periode ini.',
                ]);
            }

            $pinjaman = Pinjaman::query()->lockForUpdate()->findOrFail($payment->pinjaman_id);
            $jadwal = JadwalCicilanPinjaman::query()->lockForUpdate()->findOrFail($payment->jadwal_cicilan_pinjaman_id);
            $nominalCents = $this->decimalToCents($payment->jumlah_cicilan);
            $anggotaAktif = $pinjaman->anggota?->status === Anggota::STATUS_AKTIF
                && $pinjaman->anggota?->karyawan?->status_kerja === Karyawan::STATUS_AKTIF;

            $jenis = $payment->metode_pembayaran === CicilanPinjaman::METODE_POTONG_GAJI && $anggotaAktif
                ? ReversalTransaksi::JENIS_CICILAN_PAYROLL_REVERSAL
                : ReversalTransaksi::JENIS_CICILAN_CASH_REVERSAL;

            $dompet = null;
            if ($jenis === ReversalTransaksi::JENIS_CICILAN_CASH_REVERSAL) {
                $dompet = $this->cashOrBankDompetForRefund($dompetRefund?->id ?? $payment->dompet_id);
                $this->assertDompetSaldo($dompet, $nominalCents, 'Saldo Dompet tidak mencukupi untuk reversal cicilan.');
            }

            $reversal = $this->createReversal(
                $payment,
                $jenis,
                $nominalCents,
                $alasan,
                $userId,
                [
                    'original_jurnal_id' => $payment->jurnal?->id,
                    'original_mutasi_id' => $payment->mutasiKas?->id,
                    'dompet_refund_id' => $dompet?->id,
                    'idempotency_key' => 'reversal:cicilan:' . $payment->id,
                ]
            );

            $jadwal->update([
                'status' => JadwalCicilanPinjaman::STATUS_SCHEDULED,
                'metode_penyelesaian' => null,
                'paid_at' => null,
            ]);

            $pinjaman->update([
                'sisa_pinjaman' => $this->decimalFromCents($this->decimalToCents($pinjaman->sisa_pinjaman) + $nominalCents),
                'status' => Pinjaman::STATUS_AKTIF,
            ]);

            $payment->update([
                'status' => CicilanPinjaman::STATUS_REVERSED,
                'reversal_transaksi_id' => $reversal->id,
            ]);

            if ($jenis === ReversalTransaksi::JENIS_CICILAN_PAYROLL_REVERSAL) {
                $this->createCredit($pinjaman->anggota, $reversal, $nominalCents, $userId);
                $this->akuntansiService->recordCicilanReversalToCredit($payment, $reversal, $userId);
            } else {
                $this->recordRefundMutasi($reversal, $dompet, $nominalCents, 'Refund reversal cicilan #' . $payment->id);
                $this->decreaseSaldoDompet($dompet, $nominalCents);
                $this->akuntansiService->recordCicilanReversalCash($payment, $reversal, $dompet->akun, $userId);
            }

            return $reversal->fresh(['kreditPayroll', 'dompetRefund']);
        });
    }

    public function payOutstandingSource(string $sourceType, int $sourceId, DompetKoperasi $dompet, int $userId): PembayaranOutstandingCash
    {
        return DB::transaction(function () use ($sourceType, $sourceId, $dompet, $userId): PembayaranOutstandingCash {
            $dompetKas = $this->cashDompetForPayment($dompet->id);
            $source = $this->outstandingSourceForUpdate($sourceType, $sourceId);
            $nominalCents = $this->outstandingNominalCents($source);

            if ($nominalCents <= 0) {
                throw ValidationException::withMessages(['outstanding' => 'Nominal outstanding tidak valid.']);
            }

            $kode = $this->nextCode('outstanding_cash', 'OCS');
            $payment = PembayaranOutstandingCash::query()->create([
                'kode_pembayaran' => $kode,
                'source_type' => $source::class,
                'source_id' => $source->id,
                'anggota_id' => $source->anggota_id ?? $source->penjualan?->anggota_id ?? null,
                'karyawan_id' => $source->karyawan_id ?? $source->penjualan?->karyawan_id ?? null,
                'dompet_id' => $dompetKas->id,
                'nominal' => $this->decimalFromCents($nominalCents),
                'status' => PembayaranOutstandingCash::STATUS_PAID,
                'paid_at' => $this->now(),
                'created_by' => $userId,
                'idempotency_key' => 'outstanding-cash:' . $source::class . ':' . $source->id,
            ]);

            $this->recordOutstandingMutasi($payment, $dompetKas, $nominalCents);
            $this->increaseSaldoDompet($dompetKas, $nominalCents);
            $this->akuntansiService->recordOutstandingCashReceipt($payment, $dompetKas->akun, $userId);
            $this->markOutstandingSettled($source);

            return $payment->fresh(['source', 'dompet', 'mutasiKas', 'jurnal.details']);
        });
    }

    public function payAllOutstandingForAnggota(Anggota $anggota, DompetKoperasi $dompet, int $userId): Collection
    {
        return DB::transaction(function () use ($anggota, $dompet, $userId): Collection {
            $lockedAnggota = Anggota::query()->lockForUpdate()->findOrFail($anggota->id);
            $payments = new Collection();

            $posIds = Pembayaran::query()
                ->join('penjualan', 'penjualan.id', '=', 'pembayaran.penjualan_id')
                ->where('penjualan.anggota_id', $lockedAnggota->id)
                ->where('pembayaran.status', Pembayaran::STATUS_OUTSTANDING_CASH)
                ->pluck('pembayaran.id');

            foreach ($posIds as $paymentId) {
                $payments->push($this->payOutstandingSource(Pembayaran::class, (int) $paymentId, $dompet, $userId));
            }

            $simpananIds = Simpanan::query()
                ->where('anggota_id', $lockedAnggota->id)
                ->where('status', Simpanan::STATUS_OUTSTANDING_CASH)
                ->pluck('id');

            foreach ($simpananIds as $simpananId) {
                $payments->push($this->payOutstandingSource(Simpanan::class, (int) $simpananId, $dompet, $userId));
            }

            if ($payments->isEmpty()) {
                throw ValidationException::withMessages(['outstanding' => 'Tidak ada outstanding cash eligible untuk Anggota ini.']);
            }

            return $payments;
        });
    }

    private function refundConfirmedPayrollPos(Penjualan $penjualan, Pembayaran $payment, string $alasan, int $userId, ?DompetKoperasi $dompetRefund): ReversalTransaksi
    {
        if ($payment->status !== Pembayaran::STATUS_PAID || ! $payment->ledger || $payment->ledger->status !== PemakaianPotongGaji::STATUS_SETTLED) {
            throw ValidationException::withMessages([
                'penjualan' => 'POS payroll confirmed hanya dapat diretur setelah pembayaran paid dan ledger settled.',
            ]);
        }

        $anggotaAktif = $penjualan->anggota?->status === Anggota::STATUS_AKTIF
            && $penjualan->anggota?->karyawan?->status_kerja === Karyawan::STATUS_AKTIF;
        $nominalCents = $this->decimalToCents($penjualan->grand_total);
        $dompet = null;

        if (! $anggotaAktif) {
            $dompet = $this->cashOrBankDompetForRefund($dompetRefund?->id);
            $this->assertDompetSaldo($dompet, $nominalCents, 'Saldo Dompet refund tidak mencukupi.');
        }

        $reversal = $this->createReversal(
            $penjualan,
            $anggotaAktif ? ReversalTransaksi::JENIS_POS_PAYROLL_REFUND_CREDIT : ReversalTransaksi::JENIS_POS_PAYROLL_REFUND_CASH,
            $nominalCents,
            $alasan,
            $userId,
            [
                'original_ledger_id' => $payment->pemakaian_potong_gaji_id,
                'original_jurnal_id' => $penjualan->jurnal?->id,
                'original_mutasi_id' => $penjualan->mutasiKas?->id,
                'dompet_refund_id' => $dompet?->id,
                'idempotency_key' => 'reversal:pos-confirmed:' . $penjualan->id,
            ]
        );

        if ($anggotaAktif) {
            $this->createCredit($penjualan->anggota, $reversal, $nominalCents, $userId);
            $this->akuntansiService->recordPosReversal($reversal, $penjualan, 'utang_refund_anggota', null, $userId);
        } else {
            $this->recordRefundMutasi($reversal, $dompet, $nominalCents, 'Refund POS payroll confirmed ' . $penjualan->kode_transaksi);
            $this->decreaseSaldoDompet($dompet, $nominalCents);
            $this->akuntansiService->recordPosReversal($reversal, $penjualan, 'dompet', $dompet->akun, $userId);
        }

        $payment->update([
            'status' => Pembayaran::STATUS_REFUNDED,
            'reversal_transaksi_id' => $reversal->id,
        ]);

        $penjualan->update([
            'status' => Penjualan::STATUS_REFUNDED,
            'reversal_transaksi_id' => $reversal->id,
            'reversed_by' => $userId,
            'reversed_at' => $this->now(),
        ]);

        return $reversal->fresh(['kreditPayroll', 'dompetRefund']);
    }

    private function refundNonPayrollPos(Penjualan $penjualan, Pembayaran $payment, string $alasan, int $userId): ReversalTransaksi
    {
        if ($payment->status !== Pembayaran::STATUS_PAID || ! in_array($payment->metode_pembayaran, [
            Pembayaran::METODE_TUNAI,
            Pembayaran::METODE_TRANSFER_BANK,
            Pembayaran::METODE_QRIS,
        ], true)) {
            throw ValidationException::withMessages(['penjualan' => 'Hanya POS non-payroll paid yang dapat direfund.']);
        }

        $dompet = $this->cashOrBankDompetForRefund($payment->dompet_id);
        $nominalCents = $this->decimalToCents($penjualan->grand_total);
        $this->assertDompetSaldo($dompet, $nominalCents, 'Saldo Dompet refund tidak mencukupi.');

        $reversal = $this->createReversal(
            $penjualan,
            ReversalTransaksi::JENIS_POS_NON_PAYROLL_REFUND,
            $nominalCents,
            $alasan,
            $userId,
            [
                'original_jurnal_id' => $penjualan->jurnal?->id,
                'original_mutasi_id' => $penjualan->mutasiKas?->id,
                'dompet_refund_id' => $dompet->id,
                'idempotency_key' => 'reversal:pos-nonpayroll:' . $penjualan->id,
            ]
        );

        $this->recordRefundMutasi($reversal, $dompet, $nominalCents, 'Refund POS non-payroll ' . $penjualan->kode_transaksi);
        $this->decreaseSaldoDompet($dompet, $nominalCents);
        $this->akuntansiService->recordPosReversal($reversal, $penjualan, 'dompet', $dompet->akun, $userId);

        $payment->update([
            'status' => Pembayaran::STATUS_REFUNDED,
            'reversal_transaksi_id' => $reversal->id,
        ]);

        $penjualan->update([
            'status' => Penjualan::STATUS_REFUNDED,
            'reversal_transaksi_id' => $reversal->id,
            'reversed_by' => $userId,
            'reversed_at' => $this->now(),
        ]);

        return $reversal->fresh(['dompetRefund']);
    }

    private function createReversal(object $source, string $jenis, int $nominalCents, string $alasan, int $userId, array $extra = []): ReversalTransaksi
    {
        if ($nominalCents <= 0) {
            throw ValidationException::withMessages(['nominal' => 'Nominal reversal harus lebih besar dari nol.']);
        }

        try {
            return ReversalTransaksi::query()->create(array_merge([
                'kode_reversal' => $this->nextCode('reversal', 'REV'),
                'source_type' => $source::class,
                'source_id' => $source->id,
                'jenis_reversal' => $jenis,
                'nominal' => $this->decimalFromCents($nominalCents),
                'alasan' => trim($alasan),
                'status' => ReversalTransaksi::STATUS_PROCESSED,
                'created_by' => $userId,
                'processed_by' => $userId,
                'processed_at' => $this->now(),
            ], $extra));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'reversal' => 'Transaksi ini sudah mempunyai reversal/refund. Muat ulang halaman untuk melihat status terbaru.',
            ]);
        }
    }

    private function createCredit(?Anggota $anggota, ReversalTransaksi $reversal, int $nominalCents, int $userId): KreditPotongGajiAnggota
    {
        if (! $anggota) {
            throw ValidationException::withMessages(['anggota' => 'Kredit payroll membutuhkan mapping Anggota yang valid.']);
        }

        return KreditPotongGajiAnggota::query()->create([
            'anggota_id' => $anggota->id,
            'reversal_transaksi_id' => $reversal->id,
            'nominal_awal' => $this->decimalFromCents($nominalCents),
            'nominal_terpakai' => '0.00',
            'nominal_sisa' => $this->decimalFromCents($nominalCents),
            'status' => KreditPotongGajiAnggota::STATUS_OPEN,
            'created_by' => $userId,
        ]);
    }

    private function assertNoReversal(Penjualan $penjualan): void
    {
        if ($penjualan->reversal_transaksi_id || ReversalTransaksi::query()->where('source_type', Penjualan::class)->where('source_id', $penjualan->id)->exists()) {
            throw ValidationException::withMessages([
                'penjualan' => 'Penjualan ini sudah mempunyai reversal/refund.',
            ]);
        }
    }

    private function assertReason(string $alasan): void
    {
        if (mb_strlen(trim($alasan)) < 5) {
            throw ValidationException::withMessages(['alasan' => 'Alasan koreksi/reversal wajib diisi minimal 5 karakter.']);
        }
    }

    private function assertResellerSettlementReversible(Penjualan $penjualan): void
    {
        $blocked = HutangReseller::query()
            ->whereIn('detail_penjualan_id', $penjualan->details->pluck('id'))
            ->where(function ($query): void {
                $query->whereNotNull('pembayaran_konsinyasi_id')
                    ->orWhere('status', '!=', 'belum_dibayar');
            })
            ->exists();

        if ($blocked) {
            throw ValidationException::withMessages([
                'reseller' => 'Reversal otomatis diblokir karena kewajiban reseller sudah diselesaikan atau mempunyai transaksi lanjutan.',
            ]);
        }
    }

    private function restoreStockOnce(Penjualan $penjualan): void
    {
        foreach ($penjualan->details as $detail) {
            /** @var DetailPenjualan $detail */
            $produk = Produk::query()->lockForUpdate()->findOrFail($detail->produk_id);
            $produk->update(['stok' => (int) $produk->stok + (int) $detail->qty]);
        }
    }

    private function cancelResellerDebt(Penjualan $penjualan): void
    {
        HutangReseller::query()
            ->whereIn('detail_penjualan_id', $penjualan->details->pluck('id'))
            ->where('status', 'belum_dibayar')
            ->whereNull('pembayaran_konsinyasi_id')
            ->update(['status' => 'dibatalkan']);
    }

    private function cashOrBankDompetForRefund(?int $dompetId): DompetKoperasi
    {
        if (! $dompetId) {
            throw ValidationException::withMessages(['dompet_id' => 'Dompet refund wajib dipilih.']);
        }

        $dompet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail($dompetId);

        if (! in_array($dompet->jenis_dompet, [DompetKoperasi::JENIS_KAS, DompetKoperasi::JENIS_BANK], true)) {
            throw ValidationException::withMessages(['dompet_id' => 'Dompet refund harus Kas atau Bank.']);
        }

        if (! $dompet->akun || ! $dompet->akun->is_aktif || $dompet->akun->kategori !== 'aset' || $dompet->akun->posisi_saldo !== 'debit') {
            throw ValidationException::withMessages(['dompet_id' => 'Dompet refund wajib memiliki COA Aset aktif dengan saldo normal Debit.']);
        }

        return $dompet;
    }

    private function cashDompetForPayment(int $dompetId): DompetKoperasi
    {
        $dompet = $this->cashOrBankDompetForRefund($dompetId);

        if ($dompet->jenis_dompet !== DompetKoperasi::JENIS_KAS) {
            throw ValidationException::withMessages(['dompet_id' => 'Pembayaran outstanding cash wajib masuk Dompet Kas.']);
        }

        return $dompet;
    }

    private function assertDompetSaldo(DompetKoperasi $dompet, int $nominalCents, string $message): void
    {
        if ($this->decimalToCents($dompet->saldo) < $nominalCents) {
            throw ValidationException::withMessages(['dompet_id' => $message]);
        }
    }

    private function recordRefundMutasi(ReversalTransaksi $reversal, DompetKoperasi $dompet, int $nominalCents, string $keterangan): MutasiKas
    {
        return MutasiKas::query()->create([
            'idempotency_key' => 'reversal:mutasi:' . $reversal->id,
            'dompet_id' => $dompet->id,
            'tipe' => 'keluar',
            'jumlah' => $this->decimalFromCents($nominalCents),
            'keterangan' => $keterangan,
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversal->id,
            'tanggal' => $this->today(),
        ]);
    }

    private function recordOutstandingMutasi(PembayaranOutstandingCash $payment, DompetKoperasi $dompet, int $nominalCents): MutasiKas
    {
        return MutasiKas::query()->create([
            'idempotency_key' => 'outstanding-cash:mutasi:' . $payment->id,
            'dompet_id' => $dompet->id,
            'tipe' => 'masuk',
            'jumlah' => $this->decimalFromCents($nominalCents),
            'keterangan' => 'Penerimaan outstanding cash ' . $payment->kode_pembayaran,
            'referensi_tipe' => PembayaranOutstandingCash::class,
            'referensi_id' => $payment->id,
            'tanggal' => $this->today(),
        ]);
    }

    private function outstandingSourceForUpdate(string $sourceType, int $sourceId): Pembayaran|Simpanan
    {
        if ($sourceType === Pembayaran::class) {
            $payment = Pembayaran::query()
                ->with('penjualan')
                ->lockForUpdate()
                ->findOrFail($sourceId);

            if ($payment->status !== Pembayaran::STATUS_OUTSTANDING_CASH) {
                throw ValidationException::withMessages(['outstanding' => 'Pembayaran POS ini bukan outstanding cash.']);
            }

            return $payment;
        }

        if ($sourceType === Simpanan::class) {
            $simpanan = Simpanan::query()->lockForUpdate()->findOrFail($sourceId);

            if ($simpanan->status !== Simpanan::STATUS_OUTSTANDING_CASH) {
                throw ValidationException::withMessages(['outstanding' => 'Simpanan ini bukan outstanding cash.']);
            }

            return $simpanan;
        }

        throw ValidationException::withMessages(['source_type' => 'Sumber outstanding belum didukung.']);
    }

    private function outstandingNominalCents(Pembayaran|Simpanan $source): int
    {
        if ($source instanceof Pembayaran) {
            return $this->decimalToCents($source->jumlah_bayar);
        }

        return $this->decimalToCents($source->nominal_snapshot ?? $source->jumlah);
    }

    private function markOutstandingSettled(Pembayaran|Simpanan $source): void
    {
        if ($source instanceof Pembayaran) {
            $source->update(['status' => Pembayaran::STATUS_SETTLED_CASH]);
            return;
        }

        $source->update([
            'status' => Simpanan::STATUS_SETTLED_CASH,
            'settled_at' => $this->now(),
        ]);
    }

    private function increaseSaldoDompet(DompetKoperasi $dompet, int $nominalCents): void
    {
        $dompet->update(['saldo' => $this->decimalFromCents($this->decimalToCents($dompet->saldo) + $nominalCents)]);
    }

    private function decreaseSaldoDompet(DompetKoperasi $dompet, int $nominalCents): void
    {
        $dompet->update(['saldo' => $this->decimalFromCents($this->decimalToCents($dompet->saldo) - $nominalCents)]);
    }

    private function nextCode(string $jenis, string $prefix): string
    {
        $periode = CarbonImmutable::now($this->timezone())->format('Ym');

        try {
            DB::table('nomor_urut_transaksi')->insert([
                'jenis' => $jenis,
                'periode' => $periode,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
        }

        $counter = DB::table('nomor_urut_transaksi')
            ->where('jenis', $jenis)
            ->where('periode', $periode)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            throw new RuntimeException('Counter nomor transaksi tidak dapat dibuat.');
        }

        $next = ((int) $counter->last_number) + 1;

        DB::table('nomor_urut_transaksi')
            ->where('id', $counter->id)
            ->update(['last_number' => $next, 'updated_at' => now()]);

        return sprintf('%s-%s-%06d', $prefix, $periode, $next);
    }

    private function now()
    {
        return now($this->timezone());
    }

    private function today(): string
    {
        return CarbonImmutable::now($this->timezone())->toDateString();
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'Asia/Jakarta');
    }

    private function decimalToCents(int|string $value): int
    {
        $normalized = trim((string) $value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = substr(str_pad(preg_replace('/\D/', '', $fraction) ?? '', 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -1 * $cents : $cents;
    }

    private function decimalFromCents(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $value = intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-' . $value : $value;
    }
}
