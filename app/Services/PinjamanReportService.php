<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\CicilanPinjaman;
use App\Models\JadwalCicilanPinjaman;
use App\Models\Karyawan;
use App\Models\PemakaianPotongGaji;
use App\Models\Pinjaman;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PinjamanReportService
{
    /**
     * @return array{
     *     rows:LengthAwarePaginator,
     *     summary:array<string,float|int>,
     *     filters:array<string,mixed>,
     *     statusOptions:array<string,string>
     * }
     */
    public function cicilanIndex(array $filters = []): array
    {
        $currentPeriod = now(config('app.timezone'))->startOfMonth()->toDateString();
        $normalized = $this->normalizeCicilanFilters($filters);
        $base = $this->cicilanBaseQuery($normalized, $currentPeriod);

        $summaryRows = (clone $base)
            ->with(['payrollLedgers', 'cicilanPembayaran'])
            ->get();

        $rows = (clone $base)
            ->with([
                'pinjaman.anggota.karyawan',
                'pinjaman.siklusKeanggotaan',
                'payrollLedgers.limit.periodePotongGaji',
                'cicilanPembayaran.dompet',
            ])
            ->orderBy('periode')
            ->orderBy('pinjaman_id')
            ->orderBy('angsuran_ke')
            ->paginate(10)
            ->withQueryString();

        $rows->setCollection(
            $rows->getCollection()->map(fn (JadwalCicilanPinjaman $jadwal) => $this->decorateJadwal($jadwal, $currentPeriod))
        );

        return [
            'rows' => $rows,
            'summary' => $this->cicilanSummary($summaryRows, $currentPeriod),
            'filters' => $normalized,
            'statusOptions' => $this->cicilanStatusOptions(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function pinjamanDetail(Pinjaman $pinjaman): array
    {
        $pinjaman->loadMissing([
            'anggota.karyawan',
            'anggota.siklusAktif',
            'siklusKeanggotaan',
            'dompet.akun',
            'jadwalCicilan.payrollLedgers.limit.periodePotongGaji',
            'jadwalCicilan.cicilanPembayaran.dompet',
            'cicilan.dompet',
            'cicilan.mutasiKas.dompet',
            'cicilan.jurnal.details',
            'mutasiKas.dompet',
            'jurnal.details',
        ]);

        $currentPeriod = now(config('app.timezone'))->startOfMonth()->toDateString();
        $jadwalRows = $pinjaman->jadwalCicilan
            ->map(fn (JadwalCicilanPinjaman $jadwal) => $this->decorateJadwal($jadwal, $currentPeriod));

        $payments = $pinjaman->cicilan
            ->sortBy(fn (CicilanPinjaman $payment) => optional($payment->tanggal_bayar)->format('Y-m-d') . '-' . str_pad((string) $payment->id, 10, '0', STR_PAD_LEFT))
            ->values();

        $activeCycleId = $pinjaman->anggota?->siklusAktif?->id;
        $oldCycle = $pinjaman->siklus_keanggotaan_id
            && $activeCycleId
            && (int) $pinjaman->siklus_keanggotaan_id !== (int) $activeCycleId
            && (float) $pinjaman->sisa_pinjaman > 0;

        return [
            'pinjaman' => $pinjaman,
            'jadwalRows' => $jadwalRows,
            'payments' => $payments,
            'summary' => [
                'total_offset' => (float) $pinjaman->jadwalCicilan->sum(fn ($row) => (float) ($row->nominal_offset ?? 0)),
                'total_pembayaran' => (float) $payments
                    ->where('status', CicilanPinjaman::STATUS_SUDAH_BAYAR)
                    ->sum(fn ($row) => (float) $row->jumlah_cicilan),
                'total_sisa_jadwal' => (float) $pinjaman->jadwalCicilan
                    ->where('status', '!=', JadwalCicilanPinjaman::STATUS_CANCELLED)
                    ->sum(fn ($row) => (float) ($row->nominal_sisa ?? $row->nominal_pokok)),
                'reserved_payroll' => (float) $pinjaman->jadwalCicilan->sum(fn (JadwalCicilanPinjaman $row) => $row->payrollLedgers
                    ->where('status', PemakaianPotongGaji::STATUS_RESERVED)
                    ->sum(fn ($ledger) => (float) $ledger->nominal)),
                'settled_payroll' => (float) $pinjaman->jadwalCicilan->sum(fn (JadwalCicilanPinjaman $row) => $row->payrollLedgers
                    ->where('status', PemakaianPotongGaji::STATUS_SETTLED)
                    ->sum(fn ($ledger) => (float) $ledger->nominal)),
                'old_cycle' => $oldCycle,
            ],
        ];
    }

    /**
     * @return Collection<int,object>
     */
    public function outstandingPinjaman(array $filters = []): Collection
    {
        $rows = Pinjaman::query()
            ->with(['anggota.karyawan', 'anggota.siklusAktif', 'siklusKeanggotaan', 'jadwalCicilan'])
            ->where('status', Pinjaman::STATUS_AKTIF)
            ->whereRaw('CAST(sisa_pinjaman AS DECIMAL(15,2)) > 0')
            ->get()
            ->filter(fn (Pinjaman $pinjaman) => ! $this->isEligiblePayrollLoan($pinjaman))
            ->map(function (Pinjaman $pinjaman): object {
                $openSchedules = $pinjaman->jadwalCicilan
                    ->filter(fn (JadwalCicilanPinjaman $jadwal) => $jadwal->status !== JadwalCicilanPinjaman::STATUS_CANCELLED
                        && (float) ($jadwal->nominal_sisa ?? $jadwal->nominal_pokok) > 0)
                    ->sortBy('periode')
                    ->values();
                $oldest = $openSchedules->first();

                return (object) [
                    'kelompok' => 'Cicilan Pinjaman',
                    'source_type' => Pinjaman::class,
                    'source_id' => $pinjaman->id,
                    'anggota' => $pinjaman->anggota,
                    'karyawan' => $pinjaman->anggota?->karyawan,
                    'kode_transaksi' => $pinjaman->kode_pinjaman,
                    'tanggal' => $pinjaman->tanggal_pinjaman,
                    'nominal_awal' => (float) $pinjaman->jumlah_pinjaman,
                    'nominal_dibayar' => max(0, (float) $pinjaman->jumlah_pinjaman - (float) $pinjaman->sisa_pinjaman),
                    'sisa' => (float) $pinjaman->sisa_pinjaman,
                    'status' => 'belum_diselesaikan',
                    'status_label' => 'Belum Diselesaikan',
                    'metode_penyelesaian' => 'Tunai melalui detail Pinjaman',
                    'pinjaman' => $pinjaman,
                    'siklus_lama' => $this->isOldCycleLoan($pinjaman),
                    'jadwal_tertua' => $oldest?->periode,
                    'jumlah_jadwal_terbuka' => $openSchedules->count(),
                    'detail_route' => route('pinjaman.show', $pinjaman),
                    'payable_on_outstanding_page' => false,
                ];
            })
            ->when($filters['anggota_id'] ?? null, fn (Collection $items, $anggotaId) => $items
                ->filter(fn ($row) => (int) ($row->anggota?->id) === (int) $anggotaId))
            ->values();

        return $rows;
    }

    /**
     * @return Collection<int,object>
     */
    public function dueCicilanWithoutLedgerForPayroll(string $periode, ?int $anggotaId = null): Collection
    {
        $periodStart = $this->normalizePeriod($periode);

        return JadwalCicilanPinjaman::query()
            ->with(['pinjaman.anggota.karyawan', 'payrollLedgers'])
            ->whereDate('periode', '<=', $periodStart)
            ->where('status', JadwalCicilanPinjaman::STATUS_SCHEDULED)
            ->whereRaw('CAST(COALESCE(nominal_sisa, nominal_pokok) AS DECIMAL(15,2)) > 0')
            ->whereHas('pinjaman', function (Builder $query) use ($anggotaId): void {
                $query->where('status', Pinjaman::STATUS_AKTIF)
                    ->whereNotNull('siklus_keanggotaan_id')
                    ->when($anggotaId, fn ($q) => $q->where('anggota_id', $anggotaId));
            })
            ->whereHas('pinjaman.anggota.siklusAktif', function (Builder $query): void {
                $query->whereColumn('siklus_keanggotaan.id', 'pinjaman.siklus_keanggotaan_id');
            })
            ->whereDoesntHave('payrollLedgers', function (Builder $query): void {
                $query->whereIn('status', [
                    PemakaianPotongGaji::STATUS_RESERVED,
                    PemakaianPotongGaji::STATUS_SETTLED,
                ]);
            })
            ->orderBy('periode')
            ->get()
            ->map(fn (JadwalCicilanPinjaman $jadwal) => (object) [
                'jadwal' => $jadwal,
                'pinjaman' => $jadwal->pinjaman,
                'anggota' => $jadwal->pinjaman?->anggota,
                'periode' => $jadwal->periode,
                'nominal_sisa' => (float) ($jadwal->nominal_sisa ?? $jadwal->nominal_pokok),
            ]);
    }

    /**
     * @return array<string,string>
     */
    public function cicilanStatusOptions(): array
    {
        return [
            'scheduled' => 'Terjadwal',
            'tertunggak' => 'Tertunggak',
            'reserved' => 'Dicadangkan Payroll',
            'paid' => 'Sudah Dibayar',
            'cancelled' => 'Dibatalkan',
            'reversed' => 'Dikoreksi',
        ];
    }

    /**
     * @param array<string,mixed> $filters
     */
    private function cicilanBaseQuery(array $filters, string $currentPeriod): Builder
    {
        return JadwalCicilanPinjaman::query()
            ->whereHas('pinjaman')
            ->when($filters['anggota_id'] ?? null, fn (Builder $query, $anggotaId) => $query
                ->whereHas('pinjaman', fn (Builder $loan) => $loan->where('anggota_id', $anggotaId)))
            ->when($filters['pinjaman_id'] ?? null, fn (Builder $query, $pinjamanId) => $query->where('pinjaman_id', $pinjamanId))
            ->when($filters['periode_mulai'] ?? null, fn (Builder $query, $periode) => $query->whereDate('periode', '>=', $periode))
            ->when($filters['periode_selesai'] ?? null, fn (Builder $query, $periode) => $query->whereDate('periode', '<=', $periode))
            ->when($filters['status'] ?? null, function (Builder $query, string $status) use ($currentPeriod): void {
                match ($status) {
                    'tertunggak' => $query->where('status', JadwalCicilanPinjaman::STATUS_SCHEDULED)
                        ->whereDate('periode', '<=', $currentPeriod),
                    'reversed' => $query->whereHas('cicilanPembayaran', fn (Builder $payment) => $payment
                        ->where('status', CicilanPinjaman::STATUS_REVERSED)),
                    default => $query->where('status', $status),
                };
            });
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private function normalizeCicilanFilters(array $filters): array
    {
        foreach (['anggota_id', 'pinjaman_id'] as $key) {
            if (($filters[$key] ?? null) === '' || ($filters[$key] ?? null) === null) {
                unset($filters[$key]);
            }
        }

        if (($filters['status'] ?? null) === '' || ! array_key_exists((string) ($filters['status'] ?? ''), $this->cicilanStatusOptions())) {
            unset($filters['status']);
        }

        foreach (['periode_mulai', 'periode_selesai'] as $key) {
            if (empty($filters[$key])) {
                unset($filters[$key]);
                continue;
            }

            $filters[$key] = $this->normalizePeriod((string) $filters[$key]);
        }

        return $filters;
    }

    private function decorateJadwal(JadwalCicilanPinjaman $jadwal, string $currentPeriod): object
    {
        $jadwal->loadMissing(['pinjaman.anggota.karyawan', 'payrollLedgers.limit.periodePotongGaji', 'cicilanPembayaran.dompet']);
        $activeLedger = $jadwal->payrollLedgers
            ->sortByDesc('id')
            ->first(fn (PemakaianPotongGaji $ledger) => in_array($ledger->status, [
                PemakaianPotongGaji::STATUS_RESERVED,
                PemakaianPotongGaji::STATUS_CONSUMED,
                PemakaianPotongGaji::STATUS_SETTLED,
                PemakaianPotongGaji::STATUS_RELEASED,
                PemakaianPotongGaji::STATUS_REVERSED,
            ], true));
        $payment = $jadwal->cicilanPembayaran;

        return (object) [
            'jadwal' => $jadwal,
            'pinjaman' => $jadwal->pinjaman,
            'anggota' => $jadwal->pinjaman?->anggota,
            'karyawan' => $jadwal->pinjaman?->anggota?->karyawan,
            'kode_pinjaman' => $jadwal->pinjaman?->kode_pinjaman,
            'periode' => $jadwal->periode,
            'nominal_pokok' => (float) $jadwal->nominal_pokok,
            'nominal_offset' => (float) ($jadwal->nominal_offset ?? 0),
            'nominal_sisa' => (float) ($jadwal->nominal_sisa ?? $jadwal->nominal_pokok),
            'status_label' => $this->jadwalStatusLabel($jadwal, $currentPeriod, $payment),
            'status_class' => $this->jadwalStatusClass($jadwal, $currentPeriod, $payment),
            'payroll_status' => $activeLedger?->status,
            'payroll_status_label' => $this->ledgerStatusLabel($activeLedger?->status),
            'payroll_status_class' => $this->ledgerStatusClass($activeLedger?->status),
            'payroll_nominal' => $activeLedger ? (float) $activeLedger->nominal : 0,
            'payment' => $payment,
            'metode_pembayaran_label' => $this->paymentMethodLabel($payment?->metode_pembayaran ?? $jadwal->metode_penyelesaian),
            'tanggal_pembayaran' => $payment?->tanggal_bayar ?? $jadwal->paid_at,
        ];
    }

    /**
     * @param Collection<int,JadwalCicilanPinjaman> $rows
     * @return array<string,float|int>
     */
    private function cicilanSummary(Collection $rows, string $currentPeriod): array
    {
        $due = $rows->filter(fn (JadwalCicilanPinjaman $row) => $row->status !== JadwalCicilanPinjaman::STATUS_CANCELLED
            && (float) ($row->nominal_sisa ?? $row->nominal_pokok) > 0
            && $row->periode->toDateString() <= $currentPeriod);
        $overdue = $rows->filter(fn (JadwalCicilanPinjaman $row) => $row->status === JadwalCicilanPinjaman::STATUS_SCHEDULED
            && (float) ($row->nominal_sisa ?? $row->nominal_pokok) > 0
            && $row->periode->toDateString() <= $currentPeriod);
        $reserved = $rows->sum(fn (JadwalCicilanPinjaman $row) => $row->payrollLedgers
            ->where('status', PemakaianPotongGaji::STATUS_RESERVED)
            ->sum(fn ($ledger) => (float) $ledger->nominal));
        $paid = $rows->where('status', JadwalCicilanPinjaman::STATUS_PAID)
            ->sum(fn ($row) => (float) $row->nominal_pokok);
        $remaining = $rows->filter(fn (JadwalCicilanPinjaman $row) => $row->status !== JadwalCicilanPinjaman::STATUS_CANCELLED)
            ->sum(fn ($row) => (float) ($row->nominal_sisa ?? $row->nominal_pokok));

        return [
            'jatuh_tempo' => (float) $due->sum(fn ($row) => (float) ($row->nominal_sisa ?? $row->nominal_pokok)),
            'tertunggak' => (float) $overdue->sum(fn ($row) => (float) ($row->nominal_sisa ?? $row->nominal_pokok)),
            'dicadangkan_payroll' => (float) $reserved,
            'sudah_dibayar' => (float) $paid,
            'total_sisa' => (float) $remaining,
            'jumlah_jadwal' => $rows->count(),
        ];
    }

    private function jadwalStatusLabel(JadwalCicilanPinjaman $jadwal, string $currentPeriod, ?CicilanPinjaman $payment): string
    {
        if ($payment?->status === CicilanPinjaman::STATUS_REVERSED) {
            return 'Dikoreksi';
        }

        return match ($jadwal->status) {
            JadwalCicilanPinjaman::STATUS_SCHEDULED => $jadwal->periode->toDateString() <= $currentPeriod ? 'Tertunggak' : 'Terjadwal',
            JadwalCicilanPinjaman::STATUS_RESERVED => 'Dicadangkan Payroll',
            JadwalCicilanPinjaman::STATUS_PAID => 'Sudah Dibayar',
            JadwalCicilanPinjaman::STATUS_CANCELLED => 'Dibatalkan',
            default => ucfirst((string) $jadwal->status),
        };
    }

    private function jadwalStatusClass(JadwalCicilanPinjaman $jadwal, string $currentPeriod, ?CicilanPinjaman $payment): string
    {
        if ($payment?->status === CicilanPinjaman::STATUS_REVERSED) {
            return 'kbsm-status kbsm-status--amber';
        }

        return match ($jadwal->status) {
            JadwalCicilanPinjaman::STATUS_SCHEDULED => $jadwal->periode->toDateString() <= $currentPeriod
                ? 'kbsm-status kbsm-status--red'
                : 'kbsm-status kbsm-status--slate',
            JadwalCicilanPinjaman::STATUS_RESERVED => 'kbsm-status kbsm-status--navy',
            JadwalCicilanPinjaman::STATUS_PAID => 'kbsm-status kbsm-status--green',
            JadwalCicilanPinjaman::STATUS_CANCELLED => 'kbsm-status kbsm-status--slate',
            default => 'kbsm-status kbsm-status--slate',
        };
    }

    private function ledgerStatusLabel(?string $status): string
    {
        return match ($status) {
            PemakaianPotongGaji::STATUS_RESERVED => 'Dicadangkan Payroll',
            PemakaianPotongGaji::STATUS_CONSUMED => 'Menunggu Potong Gaji',
            PemakaianPotongGaji::STATUS_SETTLED => 'Sudah Dipotong',
            PemakaianPotongGaji::STATUS_RELEASED => 'Dilepas',
            PemakaianPotongGaji::STATUS_REVERSED => 'Dikoreksi',
            default => 'Belum Dialokasikan',
        };
    }

    private function ledgerStatusClass(?string $status): string
    {
        return match ($status) {
            PemakaianPotongGaji::STATUS_RESERVED => 'kbsm-status kbsm-status--navy',
            PemakaianPotongGaji::STATUS_CONSUMED => 'kbsm-status kbsm-status--amber',
            PemakaianPotongGaji::STATUS_SETTLED => 'kbsm-status kbsm-status--green',
            PemakaianPotongGaji::STATUS_RELEASED => 'kbsm-status kbsm-status--slate',
            PemakaianPotongGaji::STATUS_REVERSED => 'kbsm-status kbsm-status--amber',
            default => 'kbsm-status kbsm-status--slate',
        };
    }

    private function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            CicilanPinjaman::METODE_POTONG_GAJI,
            JadwalCicilanPinjaman::METODE_POTONG_GAJI => 'Potong Gaji',
            CicilanPinjaman::METODE_TUNAI,
            JadwalCicilanPinjaman::METODE_TUNAI => 'Tunai',
            JadwalCicilanPinjaman::METODE_OFFSET_SIMPANAN_POKOK => 'Offset Simpanan',
            default => '-',
        };
    }

    private function normalizePeriod(string $value): string
    {
        $value = trim($value);
        $format = preg_match('/^\d{4}-\d{2}$/', $value) ? 'Y-m' : null;

        $date = $format
            ? CarbonImmutable::createFromFormat($format, $value, config('app.timezone'))
            : CarbonImmutable::parse($value, config('app.timezone'));

        return $date->startOfMonth()->toDateString();
    }

    private function isEligiblePayrollLoan(Pinjaman $pinjaman): bool
    {
        return $pinjaman->anggota?->status === Anggota::STATUS_AKTIF
            && $pinjaman->anggota?->karyawan?->status_kerja === Karyawan::STATUS_AKTIF
            && ! $this->isOldCycleLoan($pinjaman);
    }

    private function isOldCycleLoan(Pinjaman $pinjaman): bool
    {
        $activeCycleId = $pinjaman->anggota?->siklusAktif?->id;

        return $pinjaman->siklus_keanggotaan_id
            && $activeCycleId
            && (int) $pinjaman->siklus_keanggotaan_id !== (int) $activeCycleId;
    }
}
