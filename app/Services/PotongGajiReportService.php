<?php

namespace App\Services;

use App\Models\AlokasiKreditPotongGaji;
use App\Models\CicilanPinjaman;
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
        $creditByLimit = $limitIds === []
            ? collect()
            : AlokasiKreditPotongGaji::query()
                ->whereIn('limit_potong_gaji_anggota_id', $limitIds)
                ->where('status', AlokasiKreditPotongGaji::STATUS_APPLIED)
                ->get()
                ->groupBy('limit_potong_gaji_anggota_id');

        $rows = $limits->map(function (LimitPotongGajiAnggota $limit) use ($creditByLimit) {
            $ledgers = $limit->pemakaian;
            $activeLedgers = $ledgers->whereIn('status', [
                PemakaianPotongGaji::STATUS_RESERVED,
                PemakaianPotongGaji::STATUS_CONSUMED,
                PemakaianPotongGaji::STATUS_SETTLED,
            ]);

            $cicilan = (float) $activeLedgers->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN)->sum('nominal');
            $simpanan = (float) $activeLedgers->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK)->sum('nominal');
            $pos = (float) $activeLedgers->where('kategori', PemakaianPotongGaji::KATEGORI_POS)->sum('nominal');
            $reserved = (float) $ledgers->where('status', PemakaianPotongGaji::STATUS_RESERVED)->sum('nominal');
            $consumed = (float) $ledgers->where('status', PemakaianPotongGaji::STATUS_CONSUMED)->sum('nominal');
            $settled = (float) $ledgers->where('status', PemakaianPotongGaji::STATUS_SETTLED)->sum('nominal');
            $releasedReversed = (float) $ledgers
                ->whereIn('status', [PemakaianPotongGaji::STATUS_RELEASED, PemakaianPotongGaji::STATUS_REVERSED])
                ->sum('nominal');
            $credit = (float) $creditByLimit->get($limit->id, collect())->sum('nominal_diterapkan');
            $gross = $cicilan + $simpanan + $pos;
            $net = max(0, $gross - $credit);

            return (object) [
                'periode' => $limit->periodePotongGaji?->periode,
                'anggota' => $limit->anggota,
                'karyawan' => $limit->anggota?->karyawan,
                'nomor_anggota' => $limit->anggota?->nomor_anggota,
                'nama' => $limit->anggota?->karyawan?->nama,
                'limit_nominal' => (float) $limit->limit_nominal,
                'cicilan' => $cicilan,
                'simpanan_pokok' => $simpanan,
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
            ];
        })->values();

        if ($filters['kategori'] ?? null) {
            $kategori = $filters['kategori'];
            $rows = $rows->filter(fn ($row) => (float) match ($kategori) {
                PemakaianPotongGaji::KATEGORI_CICILAN => $row->cicilan,
                PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK => $row->simpanan_pokok,
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
                    'kode_sumber' => $this->sourceCode($ledger),
                    'tanggal' => $ledger->occurred_at,
                    'nominal' => (float) $ledger->nominal,
                    'status' => $ledger->status,
                    'metode_penyelesaian' => $ledger->jenis,
                    'ledger' => $ledger,
                    'reversal' => $ledger->reversalTransaksi,
                ]);

        $summary = [
            'total_anggota' => $rows->count(),
            'gross_payroll' => $rows->sum('gross_payroll'),
            'kredit_refund' => $rows->sum('kredit_refund'),
            'net_payroll' => $rows->sum('net_payroll'),
            'total_diterima_bank' => $this->payrollMutasiMasuk($mulai, $akhir),
            'total_outstanding' => $this->outstandingTotal(),
            'total_released_reversed' => $rows->sum('released_reversed'),
        ];

        return compact('mulai', 'akhir', 'periodeRow', 'rows', 'details', 'summary');
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
                'metode_penyelesaian' => 'tunai',
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
                'metode_penyelesaian' => 'tunai',
            ]);

        $rows = $pos->concat($simpanan)
            ->when($filters['anggota_id'] ?? null, fn (Collection $items, $anggotaId) => $items->filter(fn ($row) => (int) ($row->anggota?->id) === (int) $anggotaId))
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
        $payroll = $this->payroll($periode);
        $gross = (float) $payroll['summary']['gross_payroll'];
        $credit = (float) $payroll['summary']['kredit_refund'];
        $net = max(0, $gross - $credit);
        $mutasi = $this->payrollMutasiMasuk($mulai, $akhir);
        $bankDebit = $this->payrollJurnalDebitBank($mulai, $akhir);
        $creditPiutang = $this->payrollJurnalCreditPiutang($mulai, $akhir);
        $outstanding = $this->outstandingTotal();
        $reversal = ReversalTransaksi::query()
            ->whereBetween('processed_at', [$mulai->copy()->startOfDay(), $akhir->copy()->endOfDay()])
            ->sum('nominal');

        $differences = [
            'net_vs_mutasi' => round($net - $mutasi, 2),
            'net_vs_debit_bank' => round($net - $bankDebit, 2),
            'gross_vs_kredit_piutang' => round($gross - $creditPiutang, 2),
        ];

        $status = collect($differences)->every(fn ($diff) => abs((float) $diff) < 0.01)
            ? 'balanced'
            : 'mismatch';

        return [
            'periode' => $periode,
            'gross_kewajiban' => $gross,
            'kredit_refund_diterapkan' => $credit,
            'net_payroll' => $net,
            'mutasi_kas_masuk' => $mutasi,
            'debit_bank_jurnal' => $bankDebit,
            'kredit_piutang_jurnal' => $creditPiutang,
            'saldo_outstanding' => $outstanding,
            'total_reversal' => (float) $reversal,
            'differences' => $differences,
            'status' => $status,
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

        if ($ledger->source_type === \App\Models\JadwalCicilanPinjaman::class) {
            return 'JAD-' . $ledger->source_id;
        }

        return class_basename($ledger->source_type) . '-' . $ledger->source_id;
    }

    private function payrollMutasiMasuk(Carbon $mulai, Carbon $akhir): float
    {
        return (float) MutasiKas::query()
            ->where('tipe', 'masuk')
            ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->where(function ($query): void {
                $query->where('idempotency_key', 'like', 'pos:payroll:mutasi:%')
                    ->orWhere('idempotency_key', 'like', 'simpanan-pokok:payroll:mutasi:%')
                    ->orWhere('idempotency_key', 'like', 'cicilan:pembayaran:mutasi:%');
            })
            ->sum('jumlah');
    }

    private function payrollJurnalDebitBank(Carbon $mulai, Carbon $akhir): float
    {
        return (float) JurnalUmum::query()
            ->join('jurnal_umum_detail', 'jurnal_umum_detail.jurnal_umum_id', '=', 'jurnal_umum.id')
            ->whereBetween('jurnal_umum.tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->where('jurnal_umum_detail.debit', '>', 0)
            ->where(function ($query): void {
                $query->where('jurnal_umum.idempotency_key', 'like', 'pos:payroll:jurnal:%')
                    ->orWhere('jurnal_umum.idempotency_key', 'like', 'simpanan-pokok:payroll:jurnal:%')
                    ->orWhere('jurnal_umum.idempotency_key', 'like', 'cicilan:pembayaran:jurnal:%');
            })
            ->whereIn('jurnal_umum_detail.akun_kode', ['101', '102'])
            ->sum('jurnal_umum_detail.debit');
    }

    private function payrollJurnalCreditPiutang(Carbon $mulai, Carbon $akhir): float
    {
        return (float) JurnalUmum::query()
            ->join('jurnal_umum_detail', 'jurnal_umum_detail.jurnal_umum_id', '=', 'jurnal_umum.id')
            ->whereBetween('jurnal_umum.tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->where('jurnal_umum_detail.kredit', '>', 0)
            ->where(function ($query): void {
                $query->where('jurnal_umum.idempotency_key', 'like', 'pos:payroll:jurnal:%')
                    ->orWhere('jurnal_umum.idempotency_key', 'like', 'simpanan-pokok:payroll:jurnal:%')
                    ->orWhere('jurnal_umum.idempotency_key', 'like', 'cicilan:pembayaran:jurnal:%');
            })
            ->whereIn('jurnal_umum_detail.akun_kode', ['103', '105'])
            ->sum('jurnal_umum_detail.kredit');
    }

    private function outstandingTotal(): float
    {
        $pos = (float) Pembayaran::query()
            ->where('status', Pembayaran::STATUS_OUTSTANDING_CASH)
            ->sum('jumlah_bayar');
        $simpanan = (float) Simpanan::query()
            ->where('status', Simpanan::STATUS_OUTSTANDING_CASH)
            ->sum('jumlah');

        return $pos + $simpanan;
    }
}
