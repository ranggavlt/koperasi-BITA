<?php

namespace App\Services;

use App\Models\AlokasiKreditPotongGaji;
use App\Models\CicilanPinjaman;
use App\Models\JadwalCicilanPinjaman;
use App\Models\JadwalSimpananWajib;
use App\Models\JurnalUmum;
use App\Models\LimitPotongGajiAnggota;
use App\Models\MutasiKas;
use App\Models\Pembayaran;
use App\Models\PembayaranOutstandingCash;
use App\Models\PemakaianPotongGaji;
use App\Models\Penjualan;
use App\Models\PeriodePotongGaji;
use App\Models\ReversalTransaksi;
use App\Models\Simpanan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use LogicException;

class PotongGajiReportService
{
    public function payroll(string $periode, array $filters = []): array
    {
        [$mulai, $akhir] = $this->periodRange($periode);
        $periodeRow = PeriodePotongGaji::query()->whereDate('periode', $mulai->toDateString())->first();

        $limits = LimitPotongGajiAnggota::query()
            ->with(['anggota.karyawan', 'pemakaian', 'periodePotongGaji', 'dompetPenerimaan'])
            ->when($periodeRow, fn ($query) => $query->where('periode_potong_gaji_id', $periodeRow->id))
            ->when(! $periodeRow, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($filters['anggota_id'] ?? null, fn ($query, $anggotaId) => $query->where('anggota_id', $anggotaId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('anggota_id')
            ->get();

        $limitIds = $limits->pluck('id')->all();
        $dueCicilanWithoutLedger = $periodeRow
            ? app(PinjamanReportService::class)
                ->dueCicilanWithoutLedgerForPayroll($mulai->format('Y-m'), isset($filters['anggota_id']) ? (int) $filters['anggota_id'] : null)
                ->groupBy(fn ($row) => (int) ($row->anggota?->id ?? 0))
            : collect();
        $creditByLimit = $limitIds === []
            ? collect()
            : AlokasiKreditPotongGaji::query()
                ->whereIn('limit_potong_gaji_anggota_id', $limitIds)
                ->where('status', AlokasiKreditPotongGaji::STATUS_APPLIED)
                ->get()
                ->groupBy('limit_potong_gaji_anggota_id');

        $rows = $limits->map(function (LimitPotongGajiAnggota $limit) use ($creditByLimit, $dueCicilanWithoutLedger) {
            $ledgers = $limit->pemakaian;
            $activeLedgers = $ledgers->whereIn('status', [
                PemakaianPotongGaji::STATUS_RESERVED,
                PemakaianPotongGaji::STATUS_CONSUMED,
                PemakaianPotongGaji::STATUS_SETTLED,
            ]);

            $cicilanLedgers = $ledgers->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN);
            $activeCicilanLedgers = $activeLedgers->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN);
            $cicilan = (float) $activeCicilanLedgers->sum('nominal');
            $cicilanReserved = (float) $cicilanLedgers->where('status', PemakaianPotongGaji::STATUS_RESERVED)->sum('nominal');
            $cicilanSettled = (float) $cicilanLedgers->where('status', PemakaianPotongGaji::STATUS_SETTLED)->sum('nominal');
            $cicilanReleased = (float) $cicilanLedgers
                ->whereIn('status', [PemakaianPotongGaji::STATUS_RELEASED, PemakaianPotongGaji::STATUS_REVERSED])
                ->sum('nominal');
            $cicilanUnallocated = (float) $dueCicilanWithoutLedger
                ->get((int) $limit->anggota_id, collect())
                ->sum(fn ($row) => (float) $row->nominal_sisa);
            $simpananPokok = (float) $activeLedgers->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK)->sum('nominal');
            $simpananWajib = (float) $activeLedgers->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)->sum('nominal');
            $pos = (float) $activeLedgers->where('kategori', PemakaianPotongGaji::KATEGORI_POS)->sum('nominal');
            $reserved = (float) $ledgers->where('status', PemakaianPotongGaji::STATUS_RESERVED)->sum('nominal');
            $consumed = (float) $ledgers->where('status', PemakaianPotongGaji::STATUS_CONSUMED)->sum('nominal');
            $settled = (float) $ledgers->where('status', PemakaianPotongGaji::STATUS_SETTLED)->sum('nominal');
            $releasedReversed = (float) $ledgers
                ->whereIn('status', [PemakaianPotongGaji::STATUS_RELEASED, PemakaianPotongGaji::STATUS_REVERSED])
                ->sum('nominal');
            $credit = (float) $creditByLimit->get($limit->id, collect())->sum('nominal_diterapkan');
            $gross = $cicilan + $simpananPokok + $simpananWajib + $pos;
            $net = max(0, $gross - $credit);

            return (object) [
                'periode' => $limit->periodePotongGaji?->periode,
                'anggota' => $limit->anggota,
                'karyawan' => $limit->anggota?->karyawan,
                'nomor_anggota' => $limit->anggota?->nomor_anggota,
                'nama' => $limit->anggota?->karyawan?->nama,
                'limit_nominal' => (float) $limit->limit_nominal,
                'cicilan' => $cicilan,
                'cicilan_reserved' => $cicilanReserved,
                'cicilan_settled' => $cicilanSettled,
                'cicilan_released' => $cicilanReleased,
                'cicilan_belum_dialokasikan' => $cicilanUnallocated,
                'simpanan_pokok' => $simpananPokok,
                'simpanan_wajib' => $simpananWajib,
                'pos' => $pos,
                'jasa_print' => 0,
                'reserved' => $reserved,
                'consumed' => $consumed,
                'settled' => $settled,
                'released_reversed' => $releasedReversed,
                'kredit_refund' => $credit,
                'gross_payroll' => $gross,
                'net_payroll' => $net,
                'sisa_kapasitas' => max(0, (float) $limit->limit_nominal + $credit - $gross),
                'status_limit' => $limit->status,
                'bank_penerimaan' => $limit->dompetPenerimaan?->nama_dompet,
                'confirmed_by' => $limit->confirmed_by,
                'confirmed_at' => $limit->confirmed_at,
                'warnings' => $cicilanUnallocated > 0 ? ['Ada Cicilan jatuh tempo belum dialokasikan ke ledger payroll.'] : [],
            ];
        })->values();

        if ($filters['kategori'] ?? null) {
            $kategori = $filters['kategori'];
            $rows = $rows->filter(fn ($row) => (float) match ($kategori) {
                PemakaianPotongGaji::KATEGORI_CICILAN => $row->cicilan,
                PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK => $row->simpanan_pokok,
                PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB => $row->simpanan_wajib,
                PemakaianPotongGaji::KATEGORI_POS => $row->pos,
                PemakaianPotongGaji::KATEGORI_JASA_PRINT => $row->jasa_print,
                default => $row->gross_payroll,
            } > 0)->values();
        }

        $details = $limitIds === []
            ? collect()
            : PemakaianPotongGaji::query()
                ->with(['limit.anggota.karyawan', 'reversalTransaksi'])
                ->whereIn('limit_potong_gaji_anggota_id', $limitIds)
                ->when($filters['kategori'] ?? null, fn ($query, $kategori) => $query->where('kategori', $kategori))
                ->orderBy('limit_potong_gaji_anggota_id')
                ->orderBy('id')
                ->get()
                ->map(fn (PemakaianPotongGaji $ledger) => (object) [
                    'anggota' => $ledger->limit?->anggota,
                    'kategori' => $ledger->kategori,
                    'kategori_label' => $this->kategoriLabel($ledger->kategori),
                    'kode_sumber' => $this->sourceCode($ledger),
                    'tanggal' => $ledger->occurred_at,
                    'nominal' => (float) $ledger->nominal,
                    'status' => $ledger->status,
                    'status_label' => $this->ledgerStatusLabel($ledger->status),
                    'metode_penyelesaian' => $ledger->jenis,
                    'ledger' => $ledger,
                    'reversal' => $ledger->reversalTransaksi,
                ]);

        $summary = [
            'total_anggota' => $rows->count(),
            'gross_payroll' => $rows->sum('gross_payroll'),
            'kredit_refund' => $rows->sum('kredit_refund'),
            'net_payroll' => $rows->sum('net_payroll'),
            'total_diterima_bank' => $this->payrollMutasiMasukForLedgerIds($this->settledPayrollLedgerIds($periodeRow?->id)),
            'total_outstanding' => $this->outstandingTotal(),
            'total_released_reversed' => $rows->sum('released_reversed'),
            'cicilan_due_belum_dialokasikan' => $rows->sum('cicilan_belum_dialokasikan'),
        ];

        $warnings = $dueCicilanWithoutLedger
            ->flatten(1)
            ->values();

        return compact('mulai', 'akhir', 'periodeRow', 'rows', 'details', 'summary', 'warnings');
    }

    public function outstanding(array $filters = []): array
    {
        $pos = Pembayaran::query()
            ->with(['penjualan.anggota.karyawan', 'penjualan.karyawan'])
            ->whereIn('status', [Pembayaran::STATUS_OUTSTANDING_CASH, Pembayaran::STATUS_SETTLED_CASH])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->get()
            ->map(fn (Pembayaran $payment) => (object) [
                'kelompok' => 'POS',
                'source_type' => Pembayaran::class,
                'source_id' => $payment->id,
                'anggota' => $payment->penjualan?->anggota,
                'karyawan' => $payment->penjualan?->anggota?->karyawan ?? $payment->penjualan?->karyawan,
                'kode_transaksi' => $payment->penjualan?->kode_transaksi,
                'tanggal' => $payment->penjualan?->tanggal_transaksi,
                'nominal_awal' => (float) $payment->jumlah_bayar,
                'nominal_dibayar' => $payment->status === Pembayaran::STATUS_SETTLED_CASH ? (float) $payment->jumlah_bayar : 0,
                'sisa' => $payment->status === Pembayaran::STATUS_SETTLED_CASH ? 0 : (float) $payment->jumlah_bayar,
                'status' => $payment->status,
                'status_label' => $this->outstandingStatusLabel($payment->status),
                'metode_penyelesaian' => 'tunai',
                'payable_on_outstanding_page' => $payment->status === Pembayaran::STATUS_OUTSTANDING_CASH,
                'detail_route' => null,
            ]);

        $simpanan = Simpanan::query()
            ->with(['anggota.karyawan'])
            ->whereIn('status', [Simpanan::STATUS_OUTSTANDING_CASH, Simpanan::STATUS_SETTLED_CASH])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->get()
            ->map(fn (Simpanan $row) => (object) [
                'kelompok' => 'Simpanan Pokok',
                'source_type' => Simpanan::class,
                'source_id' => $row->id,
                'anggota' => $row->anggota,
                'karyawan' => $row->anggota?->karyawan,
                'kode_transaksi' => 'SMP-' . $row->id,
                'tanggal' => $row->tanggal,
                'nominal_awal' => (float) ($row->nominal_snapshot ?? $row->jumlah),
                'nominal_dibayar' => $row->status === Simpanan::STATUS_SETTLED_CASH ? (float) ($row->nominal_snapshot ?? $row->jumlah) : 0,
                'sisa' => $row->status === Simpanan::STATUS_SETTLED_CASH ? 0 : (float) ($row->nominal_snapshot ?? $row->jumlah),
                'status' => $row->status,
                'status_label' => $this->outstandingStatusLabel($row->status),
                'metode_penyelesaian' => 'tunai',
                'payable_on_outstanding_page' => $row->status === Simpanan::STATUS_OUTSTANDING_CASH,
                'detail_route' => null,
            ]);

        $pinjaman = app(PinjamanReportService::class)->outstandingPinjaman($filters);
        if (($filters['status'] ?? null) && ! in_array($filters['status'], ['outstanding_cash', 'belum_diselesaikan'], true)) {
            $pinjaman = collect();
        }

        $rows = $pos->concat($simpanan)
            ->concat($pinjaman)
            ->when($filters['anggota_id'] ?? null, fn (Collection $items, $anggotaId) => $items->filter(fn ($row) => (int) ($row->anggota?->id) === (int) $anggotaId))
            ->sortBy([['kelompok', 'asc'], ['tanggal', 'asc']])
            ->values();

        return [
            'rows' => $rows,
            'summary' => [
                'total_outstanding' => $rows->sum('sisa'),
                'total_dibayar' => $rows->sum('nominal_dibayar'),
                'jumlah_sumber' => $rows->count(),
            ],
        ];
    }

    public function reconciliation(string $periode): array
    {
        [$mulai, $akhir] = $this->periodRange($periode);
        $periodeRow = PeriodePotongGaji::query()->whereDate('periode', $mulai->toDateString())->first();
        $limitIds = $periodeRow
            ? LimitPotongGajiAnggota::query()->where('periode_potong_gaji_id', $periodeRow->id)->pluck('id')->all()
            : [];
        $ledgerIds = $this->settledPayrollLedgerIds($periodeRow?->id);

        $gross = $ledgerIds === []
            ? 0.0
            : (float) PemakaianPotongGaji::query()->whereIn('id', $ledgerIds)->sum('nominal');
        $credit = $limitIds === []
            ? 0.0
            : (float) AlokasiKreditPotongGaji::query()
                ->whereIn('limit_potong_gaji_anggota_id', $limitIds)
                ->where('status', AlokasiKreditPotongGaji::STATUS_APPLIED)
                ->sum('nominal_diterapkan');
        $net = max(0, $gross - $credit);
        $proof = $this->payrollPaymentProofTotalForLedgerIds($ledgerIds);
        $mutasi = $this->payrollMutasiMasukForLedgerIds($ledgerIds);
        $bankDebit = $this->payrollJurnalDebitBankForLedgerIds($ledgerIds);
        $creditPiutang = $this->payrollJurnalCreditPiutangForLedgerIds($ledgerIds);
        $outstanding = $this->outstandingTotal();
        $reversal = ReversalTransaksi::query()
            ->whereBetween('processed_at', [$mulai->copy()->startOfDay(), $akhir->copy()->endOfDay()])
            ->sum('nominal');

        $differences = [
            'Potongan tercatat vs bukti pembayaran' => round($gross - $proof, 2),
            'Penerimaan bersih vs Mutasi Bank' => round($net - $mutasi, 2),
            'Penerimaan bersih vs Debit Bank Jurnal' => round($net - $bankDebit, 2),
            'Potongan tercatat vs Kredit Piutang Jurnal' => round($gross - $creditPiutang, 2),
        ];

        $status = collect($differences)->every(fn ($diff) => abs((float) $diff) < 0.01)
            ? 'balanced'
            : 'mismatch';

        return [
            'periode' => $periode,
            'gross_kewajiban' => $gross,
            'total_potongan_tercatat' => $gross,
            'kredit_refund_diterapkan' => $credit,
            'net_payroll' => $net,
            'penerimaan_bersih_seharusnya' => $net,
            'bukti_pembayaran_payroll' => $proof,
            'mutasi_kas_masuk' => $mutasi,
            'debit_bank_jurnal' => $bankDebit,
            'kredit_piutang_jurnal' => $creditPiutang,
            'saldo_outstanding' => $outstanding,
            'total_reversal' => (float) $reversal,
            'differences' => $differences,
            'status' => $status,
            'status_label' => $status === 'balanced' ? 'Sesuai' : 'Perlu Diperiksa',
        ];
    }

    private function periodRange(string $periode): array
    {
        try {
            $mulai = Carbon::createFromFormat('Y-m', $periode, config('app.timezone'))->startOfMonth();
        } catch (\Throwable) {
            $mulai = now(config('app.timezone'))->startOfMonth();
        }

        return [$mulai, $mulai->copy()->endOfMonth()];
    }

    private function sourceCode(PemakaianPotongGaji $ledger): string
    {
        if ($ledger->source_type === Penjualan::class) {
            return Penjualan::query()->whereKey($ledger->source_id)->value('kode_transaksi') ?? ('POS-' . $ledger->source_id);
        }

        if ($ledger->source_type === Simpanan::class) {
            return 'SMP-' . $ledger->source_id;
        }

        if ($ledger->source_type === JadwalSimpananWajib::class) {
            return JadwalSimpananWajib::query()->whereKey($ledger->source_id)->value('kode_tagihan') ?? ('SWJ-' . $ledger->source_id);
        }

        if ($ledger->source_type === \App\Models\JadwalCicilanPinjaman::class) {
            return 'JAD-' . $ledger->source_id;
        }

        return class_basename($ledger->source_type) . '-' . $ledger->source_id;
    }

    private function kategoriLabel(string $kategori): string
    {
        return match ($kategori) {
            PemakaianPotongGaji::KATEGORI_CICILAN => 'Cicilan Pinjaman',
            PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK => 'Simpanan Pokok',
            PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB => 'Simpanan Wajib',
            PemakaianPotongGaji::KATEGORI_POS => 'POS Potong Gaji',
            PemakaianPotongGaji::KATEGORI_JASA_PRINT => 'Jasa Print',
            default => ucfirst(str_replace('_', ' ', $kategori)),
        };
    }

    private function ledgerStatusLabel(string $status): string
    {
        return match ($status) {
            PemakaianPotongGaji::STATUS_RESERVED => 'Dicadangkan Payroll',
            PemakaianPotongGaji::STATUS_CONSUMED => 'Menunggu Potong Gaji',
            PemakaianPotongGaji::STATUS_SETTLED => 'Sudah Dipotong',
            PemakaianPotongGaji::STATUS_RELEASED => 'Dilepas',
            PemakaianPotongGaji::STATUS_REVERSED => 'Dikoreksi',
            default => ucfirst($status),
        };
    }

    private function outstandingStatusLabel(string $status): string
    {
        return match ($status) {
            Pembayaran::STATUS_OUTSTANDING_CASH,
            Simpanan::STATUS_OUTSTANDING_CASH => 'Belum Diselesaikan',
            Pembayaran::STATUS_SETTLED_CASH,
            Simpanan::STATUS_SETTLED_CASH => 'Selesai Tunai',
            'belum_diselesaikan' => 'Belum Diselesaikan',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function payrollMutasiMasuk(Carbon $mulai, Carbon $akhir): float
    {
        return (float) MutasiKas::query()
            ->where('tipe', 'masuk')
            ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->where(function ($query): void {
                $query->where('idempotency_key', 'like', 'pos:payroll:mutasi:%')
                    ->orWhere('idempotency_key', 'like', 'simpanan-pokok:payroll:mutasi:%')
                    ->orWhere('idempotency_key', 'like', 'simpanan-wajib:payroll:mutasi:%')
                    ->orWhere('idempotency_key', 'like', 'cicilan:pembayaran:mutasi:%');
            })
            ->sum('jumlah');
    }

    /**
     * @return array<int,int>
     */
    private function settledPayrollLedgerIds(?int $periodeId): array
    {
        if (! $periodeId) {
            return [];
        }

        return PemakaianPotongGaji::query()
            ->join('limit_potong_gaji_anggota as l', 'l.id', '=', 'pemakaian_potong_gaji.limit_potong_gaji_anggota_id')
            ->where('l.periode_potong_gaji_id', $periodeId)
            ->where('pemakaian_potong_gaji.status', PemakaianPotongGaji::STATUS_SETTLED)
            ->pluck('pemakaian_potong_gaji.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param array<int,int> $ledgerIds
     * @return array{cicilan:array<int,int>, pembayaran:array<int,int>, ledger:array<int,int>}
     */
    private function payrollReferenceIdsForLedgerIds(array $ledgerIds): array
    {
        if ($ledgerIds === []) {
            return ['cicilan' => [], 'pembayaran' => [], 'ledger' => []];
        }

        $ledgers = PemakaianPotongGaji::query()
            ->whereIn('id', $ledgerIds)
            ->get(['id', 'kategori', 'source_type', 'source_id']);

        $jadwalIds = $ledgers
            ->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN)
            ->where('source_type', JadwalCicilanPinjaman::class)
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $cicilanIds = $jadwalIds === []
            ? []
            : CicilanPinjaman::query()
                ->whereIn('jadwal_cicilan_pinjaman_id', $jadwalIds)
                ->where('metode_pembayaran', CicilanPinjaman::METODE_POTONG_GAJI)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        $pembayaranIds = Pembayaran::query()
            ->whereIn('pemakaian_potong_gaji_id', $ledgerIds)
            ->where('metode_pembayaran', Pembayaran::METODE_POTONG_GAJI)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return [
            'cicilan' => $cicilanIds,
            'pembayaran' => $pembayaranIds,
            'ledger' => $ledgerIds,
        ];
    }

    /**
     * @param array<int,int> $ledgerIds
     */
    private function payrollMutasiMasukForLedgerIds(array $ledgerIds): float
    {
        $refs = $this->payrollReferenceIdsForLedgerIds($ledgerIds);
        $total = 0.0;

        if ($refs['cicilan'] !== []) {
            $total += (float) MutasiKas::query()
                ->where('tipe', 'masuk')
                ->where('referensi_tipe', CicilanPinjaman::class)
                ->whereIn('referensi_id', $refs['cicilan'])
                ->sum('jumlah');
        }

        if ($refs['pembayaran'] !== []) {
            $total += (float) MutasiKas::query()
                ->where('tipe', 'masuk')
                ->where('referensi_tipe', Pembayaran::class)
                ->whereIn('referensi_id', $refs['pembayaran'])
                ->sum('jumlah');
        }

        if ($refs['ledger'] !== []) {
            $total += (float) MutasiKas::query()
                ->where('tipe', 'masuk')
                ->where('referensi_tipe', PemakaianPotongGaji::class)
                ->whereIn('referensi_id', $refs['ledger'])
                ->sum('jumlah');
        }

        return $total;
    }

    private function payrollJurnalDebitBank(Carbon $mulai, Carbon $akhir): float
    {
        $bankCodes = $this->accountCodes(['bank']);

        return (float) JurnalUmum::query()
            ->join('jurnal_umum_detail', 'jurnal_umum_detail.jurnal_umum_id', '=', 'jurnal_umum.id')
            ->whereBetween('jurnal_umum.tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->where('jurnal_umum_detail.debit', '>', 0)
            ->where(function ($query): void {
                $query->where('jurnal_umum.idempotency_key', 'like', 'pos:payroll:jurnal:%')
                    ->orWhere('jurnal_umum.idempotency_key', 'like', 'simpanan-pokok:payroll:jurnal:%')
                    ->orWhere('jurnal_umum.idempotency_key', 'like', 'simpanan-wajib:payroll:jurnal:%')
                    ->orWhere('jurnal_umum.idempotency_key', 'like', 'cicilan:pembayaran:jurnal:%');
            })
            ->whereIn('jurnal_umum_detail.akun_kode', $bankCodes)
            ->sum('jurnal_umum_detail.debit');
    }

    /**
     * @param array<int,int> $ledgerIds
     */
    private function payrollJurnalDebitBankForLedgerIds(array $ledgerIds): float
    {
        return $this->payrollJurnalTotalForLedgerIds($ledgerIds, 'debit', $this->accountCodes(['bank']));
    }

    private function payrollJurnalCreditPiutang(Carbon $mulai, Carbon $akhir): float
    {
        $piutangCodes = $this->postingAccountCodes([
            'refund.piutang_potong_gaji',
            'refund.piutang_pinjaman',
        ]);

        return (float) JurnalUmum::query()
            ->join('jurnal_umum_detail', 'jurnal_umum_detail.jurnal_umum_id', '=', 'jurnal_umum.id')
            ->whereBetween('jurnal_umum.tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->where('jurnal_umum_detail.kredit', '>', 0)
            ->where(function ($query): void {
                $query->where('jurnal_umum.idempotency_key', 'like', 'pos:payroll:jurnal:%')
                    ->orWhere('jurnal_umum.idempotency_key', 'like', 'simpanan-pokok:payroll:jurnal:%')
                    ->orWhere('jurnal_umum.idempotency_key', 'like', 'simpanan-wajib:payroll:jurnal:%')
                    ->orWhere('jurnal_umum.idempotency_key', 'like', 'cicilan:pembayaran:jurnal:%');
            })
            ->whereIn('jurnal_umum_detail.akun_kode', $piutangCodes)
            ->sum('jurnal_umum_detail.kredit');
    }

    /**
     * @param array<int,int> $ledgerIds
     */
    private function payrollJurnalCreditPiutangForLedgerIds(array $ledgerIds): float
    {
        return $this->payrollJurnalTotalForLedgerIds($ledgerIds, 'kredit', $this->postingAccountCodes([
            'refund.piutang_potong_gaji',
            'refund.piutang_pinjaman',
        ]));
    }

    /**
     * @param array<int,int> $ledgerIds
     * @param array<int,string> $accountCodes
     */
    private function payrollJurnalTotalForLedgerIds(array $ledgerIds, string $side, array $accountCodes): float
    {
        $refs = $this->payrollReferenceIdsForLedgerIds($ledgerIds);
        $query = JurnalUmum::query()
            ->join('jurnal_umum_detail', 'jurnal_umum_detail.jurnal_umum_id', '=', 'jurnal_umum.id')
            ->whereIn('jurnal_umum_detail.akun_kode', $accountCodes)
            ->where("jurnal_umum_detail.{$side}", '>', 0)
            ->where(function ($query) use ($refs): void {
                $hasCondition = false;

                if ($refs['cicilan'] !== []) {
                    $query->where(function ($q) use ($refs): void {
                        $q->where('jurnal_umum.referensi_tipe', CicilanPinjaman::class)
                            ->whereIn('jurnal_umum.referensi_id', $refs['cicilan']);
                    });
                    $hasCondition = true;
                }

                if ($refs['pembayaran'] !== []) {
                    $method = $hasCondition ? 'orWhere' : 'where';
                    $query->{$method}(function ($q) use ($refs): void {
                        $q->where('jurnal_umum.referensi_tipe', Pembayaran::class)
                            ->whereIn('jurnal_umum.referensi_id', $refs['pembayaran']);
                    });
                    $hasCondition = true;
                }

                if ($refs['ledger'] !== []) {
                    $method = $hasCondition ? 'orWhere' : 'where';
                    $query->{$method}(function ($q) use ($refs): void {
                        $q->where('jurnal_umum.referensi_tipe', PemakaianPotongGaji::class)
                            ->whereIn('jurnal_umum.referensi_id', $refs['ledger']);
                    });
                    $hasCondition = true;
                }

                if (! $hasCondition) {
                    $query->whereRaw('1 = 0');
                }
            });

        return (float) $query->sum("jurnal_umum_detail.{$side}");
    }

    private function payrollPaymentProofTotal(Carbon $mulai, Carbon $akhir): float
    {
        $cicilan = (float) CicilanPinjaman::query()
            ->where('metode_pembayaran', CicilanPinjaman::METODE_POTONG_GAJI)
            ->where('status', CicilanPinjaman::STATUS_SUDAH_BAYAR)
            ->whereBetween('tanggal_bayar', [$mulai->toDateString(), $akhir->toDateString()])
            ->sum('jumlah_cicilan');

        $pos = (float) Pembayaran::query()
            ->where('metode_pembayaran', Pembayaran::METODE_POTONG_GAJI)
            ->where('status', Pembayaran::STATUS_PAID)
            ->whereBetween('created_at', [$mulai->copy()->startOfDay(), $akhir->copy()->endOfDay()])
            ->sum('jumlah_bayar');

        $simpanan = Simpanan::query()
            ->where('metode_pembayaran', Simpanan::METODE_POTONG_GAJI)
            ->where('status', Simpanan::STATUS_SETTLED)
            ->whereBetween('settled_at', [$mulai->copy()->startOfDay(), $akhir->copy()->endOfDay()])
            ->get()
            ->sum(fn (Simpanan $row) => (float) ($row->nominal_snapshot ?? $row->jumlah));

        return $cicilan + $pos + $simpanan;
    }

    /**
     * @param array<int,int> $ledgerIds
     */
    private function payrollPaymentProofTotalForLedgerIds(array $ledgerIds): float
    {
        if ($ledgerIds === []) {
            return 0.0;
        }

        $refs = $this->payrollReferenceIdsForLedgerIds($ledgerIds);
        $cicilan = $refs['cicilan'] === []
            ? 0.0
            : (float) CicilanPinjaman::query()
                ->whereIn('id', $refs['cicilan'])
                ->whereIn('status', [CicilanPinjaman::STATUS_SUDAH_BAYAR, CicilanPinjaman::STATUS_REVERSED])
                ->sum('jumlah_cicilan');
        $pos = $refs['pembayaran'] === []
            ? 0.0
            : (float) Pembayaran::query()
                ->whereIn('id', $refs['pembayaran'])
                ->whereIn('status', [Pembayaran::STATUS_PAID, Pembayaran::STATUS_REFUNDED])
                ->sum('jumlah_bayar');
        $simpanan = Simpanan::query()
            ->whereIn('pemakaian_potong_gaji_id', $ledgerIds)
            ->where('status', Simpanan::STATUS_SETTLED)
            ->get()
            ->sum(fn (Simpanan $row) => (float) ($row->nominal_snapshot ?? $row->jumlah));

        return $cicilan + $pos + (float) $simpanan;
    }

    private function outstandingTotal(): float
    {
        $pos = (float) Pembayaran::query()
            ->where('status', Pembayaran::STATUS_OUTSTANDING_CASH)
            ->sum('jumlah_bayar');
        $simpanan = (float) Simpanan::query()
            ->where('status', Simpanan::STATUS_OUTSTANDING_CASH)
            ->sum('jumlah');
        $pinjaman = (float) app(PinjamanReportService::class)
            ->outstandingPinjaman()
            ->sum('sisa');

        return $pos + $simpanan + $pinjaman;
    }

    /**
     * @param array<int,string> $keys
     * @return array<int,string>
     */
    private function accountCodes(array $keys): array
    {
        return array_map(function (string $key): string {
            $definition = config("account_map.accounts.{$key}");

            if (! is_array($definition) || empty($definition['kode_akun'])) {
                throw new LogicException("Pemetaan akun report [{$key}] belum didefinisikan di config/account_map.php.");
            }

            return (string) $definition['kode_akun'];
        }, $keys);
    }

    /**
     * @param array<int,string> $paths
     * @return array<int,string>
     */
    private function postingAccountCodes(array $paths): array
    {
        return array_map(function (string $path): string {
            $accountKey = config("account_map.postings.{$path}");

            if (! is_string($accountKey) || $accountKey === '') {
                throw new LogicException("Pemetaan posting report [{$path}] belum didefinisikan di config/account_map.php.");
            }

            return $this->accountCodes([$accountKey])[0];
        }, $paths);
    }
}
