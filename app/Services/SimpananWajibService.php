<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\JadwalSimpananWajib;
use App\Models\JenisSimpanan;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\LimitPotongGajiAnggota;
use App\Models\MutasiKas;
use App\Models\PemakaianPotongGaji;
use App\Models\PeriodePotongGaji;
use App\Models\SiklusKeanggotaan;
use App\Models\Simpanan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SimpananWajibService
{
    public function __construct(private readonly AkuntansiService $akuntansiService)
    {
    }

    public function businessTimezone(): string
    {
        return (string) config('app.timezone', 'Asia/Jakarta');
    }

    public function normalizePeriod(CarbonInterface|string|null $periode = null): CarbonImmutable
    {
        $timezone = $this->businessTimezone();

        if ($periode instanceof CarbonInterface) {
            return CarbonImmutable::instance($periode)
                ->setTimezone($timezone)
                ->startOfMonth()
                ->startOfDay();
        }

        if ($periode === null || trim((string) $periode) === '') {
            return CarbonImmutable::now($timezone)->startOfMonth()->startOfDay();
        }

        return CarbonImmutable::parse((string) $periode, $timezone)
            ->setTimezone($timezone)
            ->startOfMonth()
            ->startOfDay();
    }

    /**
     * Membuat tagihan Simpanan Wajib sampai periode target.
     * Operasi ini idempotent dan hanya menulis pada aksi eksplisit/service payroll,
     * bukan pada GET halaman.
     */
    public function generateUntil(CarbonInterface|string|null $targetPeriod = null, ?Anggota $onlyAnggota = null, ?int $userId = null): int
    {
        $target = $this->normalizePeriod($targetPeriod);

        return DB::transaction(function () use ($target, $onlyAnggota, $userId): int {
            $jenis = $this->activeWajibMasterForUpdate();
            $created = 0;

            $anggotaRows = Anggota::query()
                ->with(['karyawan', 'siklusAktif'])
                ->when($onlyAnggota, fn ($query) => $query->whereKey($onlyAnggota->id))
                ->where('status', Anggota::STATUS_AKTIF)
                ->whereHas('karyawan', fn ($query) => $query->where('status_kerja', Karyawan::STATUS_AKTIF))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($anggotaRows as $anggota) {
                $siklus = $anggota->siklusAktif;

                if (! $siklus) {
                    continue;
                }

                foreach ($this->duePeriodsFor($anggota, $siklus, $jenis, $target) as $periode) {
                    if ($this->isPeriodAlreadyConfirmedForAnggota($anggota->id, $periode)) {
                        continue;
                    }

                    $jadwal = $this->createScheduleWithSimpanan($anggota, $siklus, $jenis, $periode, $userId);
                    if ($jadwal->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }

            return $created;
        });
    }

    public function reserveOutstandingForLimit(LimitPotongGajiAnggota $limit, int $userId): int
    {
        return DB::transaction(function () use ($limit, $userId): int {
            $locked = LimitPotongGajiAnggota::query()
                ->with(['periodePotongGaji', 'anggota.karyawan'])
                ->lockForUpdate()
                ->findOrFail($limit->id);

            if ($locked->status !== LimitPotongGajiAnggota::STATUS_ACTIVE) {
                return 0;
            }

            $this->generateUntil($locked->periodePotongGaji->periode, $locked->anggota, $userId);

            $periode = $locked->periodePotongGaji->periode->toDateString();
            $reserved = 0;

            $jadwalRows = JadwalSimpananWajib::query()
                ->with('simpanan')
                ->where('anggota_id', $locked->anggota_id)
                ->whereDate('periode', '<=', $periode)
                ->where('status', JadwalSimpananWajib::STATUS_OUTSTANDING)
                ->orderBy('periode')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($jadwalRows as $jadwal) {
                $simpanan = $this->simpananForSchedule($jadwal, $userId);
                $nominalCents = $this->decimalToCents($jadwal->nominal_snapshot);
                $availableCents = $this->limitNominalCents($locked) - $this->reservedAndConsumedCents($locked);

                if ($nominalCents > $availableCents) {
                    break;
                }

                $ledger = $this->createReservedLedgerForJadwal($locked, $jadwal, $userId);

                $jadwal->update([
                    'status' => JadwalSimpananWajib::STATUS_RESERVED,
                    'reserved_at' => now($this->businessTimezone()),
                ]);

                $simpanan->update([
                    'pemakaian_potong_gaji_id' => $ledger->id,
                    'metode_pembayaran' => Simpanan::METODE_POTONG_GAJI,
                    'status' => Simpanan::STATUS_PENDING_PAYROLL,
                ]);

                $reserved++;
            }

            return $reserved;
        });
    }

    public function releaseReservationsForLimit(LimitPotongGajiAnggota $limit, ?int $userId, string $reason): int
    {
        return DB::transaction(function () use ($limit, $userId, $reason): int {
            $locked = LimitPotongGajiAnggota::query()->lockForUpdate()->findOrFail($limit->id);
            $released = 0;

            $ledgers = PemakaianPotongGaji::query()
                ->where('limit_potong_gaji_anggota_id', $locked->id)
                ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
                ->where('source_type', JadwalSimpananWajib::class)
                ->where('status', PemakaianPotongGaji::STATUS_RESERVED)
                ->lockForUpdate()
                ->get();

            foreach ($ledgers as $ledger) {
                $jadwal = JadwalSimpananWajib::query()->lockForUpdate()->find($ledger->source_id);

                $ledger->update([
                    'status' => PemakaianPotongGaji::STATUS_RELEASED,
                    'released_at' => now($this->businessTimezone()),
                    'released_by' => $userId,
                    'release_reason' => $reason,
                    'updated_by' => $userId,
                ]);

                if ($jadwal && $jadwal->status === JadwalSimpananWajib::STATUS_RESERVED) {
                    $jadwal->update([
                        'status' => JadwalSimpananWajib::STATUS_OUTSTANDING,
                        'reserved_at' => null,
                    ]);

                    $jadwal->simpanan()
                        ->whereIn('status', [Simpanan::STATUS_PENDING_PAYROLL, Simpanan::STATUS_ALLOCATED])
                        ->update([
                            'pemakaian_potong_gaji_id' => null,
                            'status' => Simpanan::STATUS_PENDING_PAYROLL,
                        ]);
                }

                $released++;
            }

            return $released;
        });
    }

    public function settleUsage(
        LimitPotongGajiAnggota $limit,
        PemakaianPotongGaji $usage,
        DompetKoperasi $dompetPayroll,
        int $userId,
        int $creditCents = 0
    ): Simpanan {
        return DB::transaction(function () use ($limit, $usage, $dompetPayroll, $userId, $creditCents): Simpanan {
            $lockedUsage = PemakaianPotongGaji::query()
                ->lockForUpdate()
                ->findOrFail($usage->id);

            if ($lockedUsage->kategori !== PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB
                || $lockedUsage->source_type !== JadwalSimpananWajib::class
                || $lockedUsage->status !== PemakaianPotongGaji::STATUS_RESERVED) {
                throw ValidationException::withMessages([
                    'simpanan_wajib' => 'Ledger Simpanan Wajib tidak valid untuk settlement payroll.',
                ]);
            }

            $lockedLimit = LimitPotongGajiAnggota::query()
                ->lockForUpdate()
                ->findOrFail($limit->id);

            if ((int) $lockedUsage->limit_potong_gaji_anggota_id !== (int) $lockedLimit->id) {
                throw ValidationException::withMessages([
                    'simpanan_wajib' => 'Ledger Simpanan Wajib tidak sesuai dengan limit payroll.',
                ]);
            }

            $jadwal = JadwalSimpananWajib::query()
                ->with('simpanan.jenisSimpanan.akun')
                ->lockForUpdate()
                ->findOrFail($lockedUsage->source_id);

            if ((int) $jadwal->anggota_id !== (int) $lockedLimit->anggota_id) {
                throw ValidationException::withMessages([
                    'simpanan_wajib' => 'Jadwal Simpanan Wajib tidak sesuai dengan Anggota pada limit.',
                ]);
            }

            $simpanan = $jadwal->simpanan;
            if (! $simpanan) {
                throw ValidationException::withMessages([
                    'simpanan_wajib' => 'Jadwal Simpanan Wajib belum mempunyai transaksi Simpanan.',
                ]);
            }

            $nominalCents = $this->decimalToCents($jadwal->nominal_snapshot);
            if ($this->decimalToCents($lockedUsage->nominal) !== $nominalCents) {
                throw ValidationException::withMessages([
                    'simpanan_wajib' => 'Nominal ledger Simpanan Wajib tidak sama dengan snapshot jadwal.',
                ]);
            }

            if ($jadwal->status === JadwalSimpananWajib::STATUS_SETTLED && $simpanan->status === Simpanan::STATUS_SETTLED) {
                $lockedUsage->update([
                    'status' => PemakaianPotongGaji::STATUS_SETTLED,
                    'settled_at' => now($this->businessTimezone()),
                    'updated_by' => $userId,
                ]);

                return $simpanan;
            }

            if ($jadwal->status !== JadwalSimpananWajib::STATUS_RESERVED) {
                throw ValidationException::withMessages([
                    'simpanan_wajib' => 'Jadwal Simpanan Wajib belum dalam status reserved.',
                ]);
            }

            if (! in_array($simpanan->status, [Simpanan::STATUS_PENDING_PAYROLL, Simpanan::STATUS_ALLOCATED], true)) {
                throw ValidationException::withMessages([
                    'simpanan_wajib' => 'Status Simpanan Wajib tidak dapat disettle lewat payroll.',
                ]);
            }

            $creditCents = min($creditCents, $nominalCents);
            $netCents = $nominalCents - $creditCents;

            if ($netCents > 0 && $this->recordPayrollReceiptMutasi(
                'simpanan-wajib:payroll:mutasi:' . $lockedUsage->id,
                $dompetPayroll,
                $netCents,
                'Penerimaan payroll Simpanan Wajib ' . $jadwal->kode_tagihan,
                PemakaianPotongGaji::class,
                $lockedUsage->id,
                now($this->businessTimezone())->toDateString()
            )) {
                $this->increaseSaldoDompet($dompetPayroll, $netCents);
            }

            $this->akuntansiService->recordPenerimaanPayrollPotongGajiNet(
                'simpanan-wajib:payroll:jurnal:' . $lockedUsage->id,
                'PG-SWJ-' . $lockedUsage->id,
                now($this->businessTimezone())->toDateString(),
                (float) $this->decimalFromCents($nominalCents),
                (float) $this->decimalFromCents($creditCents),
                $dompetPayroll->akun,
                PemakaianPotongGaji::class,
                $lockedUsage->id,
                $userId
            );

            $simpanan->update([
                'pemakaian_potong_gaji_id' => $lockedUsage->id,
                'metode_pembayaran' => Simpanan::METODE_POTONG_GAJI,
                'status' => Simpanan::STATUS_SETTLED,
                'settled_at' => now($this->businessTimezone()),
            ]);

            $jadwal->update([
                'status' => JadwalSimpananWajib::STATUS_SETTLED,
                'settled_at' => now($this->businessTimezone()),
                'settled_by' => $userId,
            ]);

            $lockedUsage->update([
                'status' => PemakaianPotongGaji::STATUS_SETTLED,
                'settled_at' => now($this->businessTimezone()),
                'updated_by' => $userId,
            ]);

            return $simpanan->fresh(['jadwalSimpananWajib', 'ledger', 'jurnal.details']);
        });
    }

    public function hasBlockingOutstandingBeforePos(Anggota $anggota, CarbonInterface|string $tanggal): bool
    {
        $periode = $this->normalizePeriod($tanggal)->toDateString();

        return JadwalSimpananWajib::query()
            ->where('anggota_id', $anggota->id)
            ->whereDate('periode', '<=', $periode)
            ->where('status', JadwalSimpananWajib::STATUS_OUTSTANDING)
            ->exists();
    }

    public function outstandingSummary(array $filters = []): array
    {
        $query = JadwalSimpananWajib::query()
            ->with(['anggota.karyawan', 'jenisSimpanan', 'simpanan.ledger.limit.periodePotongGaji', 'activeLedger.limit.periodePotongGaji'])
            ->when($filters['anggota_id'] ?? null, fn ($query, $anggotaId) => $query->where('anggota_id', $anggotaId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['periode_mulai'] ?? null, fn ($query, $date) => $query->whereDate('periode', '>=', $this->normalizePeriod($date)->toDateString()))
            ->when($filters['periode_selesai'] ?? null, fn ($query, $date) => $query->whereDate('periode', '<=', $this->normalizePeriod($date)->toDateString()));

        $baseRows = (clone $query)->get();

        return [
            'query' => $query,
            'summary' => [
                'total_tagihan' => (float) $baseRows->sum('nominal_snapshot'),
                'sudah_dialokasikan' => (float) $baseRows
                    ->where('status', JadwalSimpananWajib::STATUS_RESERVED)
                    ->sum('nominal_snapshot'),
                'sudah_dibayar' => (float) $baseRows
                    ->where('status', JadwalSimpananWajib::STATUS_SETTLED)
                    ->sum('nominal_snapshot'),
                'tunggakan' => (float) $baseRows
                    ->where('status', JadwalSimpananWajib::STATUS_OUTSTANDING)
                    ->sum('nominal_snapshot'),
            ],
        ];
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function duePeriodsFor(
        Anggota $anggota,
        SiklusKeanggotaan $siklus,
        JenisSimpanan $jenis,
        CarbonImmutable $target
    ): Collection {
        $interval = (int) $jenis->interval_bulan;
        $startCandidates = [
            $anggota->tanggal_bergabung,
            $siklus->tanggal_mulai,
            $jenis->berlaku_mulai,
        ];

        $start = collect($startCandidates)
            ->filter()
            ->map(fn ($date) => $this->normalizePeriod($date))
            ->max();

        if (! $start || $start->greaterThan($target)) {
            return collect();
        }

        $inactiveBoundary = $anggota->tanggal_nonaktif
            ? $this->normalizePeriod($anggota->tanggal_nonaktif)
            : null;

        $cursor = $this->firstDueOnOrAfter($start, $interval);
        $periods = collect();

        while ($cursor->lessThanOrEqualTo($target)) {
            if ($inactiveBoundary && $cursor->greaterThan($inactiveBoundary)) {
                break;
            }

            $periods->push($cursor);
            $cursor = $cursor->addMonthsNoOverflow($interval);
        }

        return $periods;
    }

    private function firstDueOnOrAfter(CarbonImmutable $period, int $interval): CarbonImmutable
    {
        $cursor = $period->startOfMonth();

        while (! $this->isDueMonth($cursor, $interval)) {
            $cursor = $cursor->addMonthNoOverflow();
        }

        return $cursor;
    }

    private function isDueMonth(CarbonImmutable $period, int $interval): bool
    {
        return (($period->month - 1) % $interval) === 0;
    }

    private function createScheduleWithSimpanan(
        Anggota $anggota,
        SiklusKeanggotaan $siklus,
        JenisSimpanan $jenis,
        CarbonImmutable $periode,
        ?int $userId
    ): JadwalSimpananWajib {
        $existing = JadwalSimpananWajib::query()
            ->where('anggota_id', $anggota->id)
            ->where('siklus_keanggotaan_id', $siklus->id)
            ->where('jenis_simpanan_id', $jenis->id)
            ->whereDate('periode', $periode->toDateString())
            ->lockForUpdate()
            ->first();

        if ($existing) {
            $this->simpananForSchedule($existing, $userId);

            return $existing;
        }

        $nominalCents = $this->decimalToCents($jenis->nominal_default);
        if ($nominalCents <= 0) {
            throw ValidationException::withMessages([
                'jenis_simpanan_id' => 'Nominal default Simpanan Wajib harus lebih besar dari nol.',
            ]);
        }

        try {
            $jadwal = JadwalSimpananWajib::query()->create([
                'kode_tagihan' => $this->nextKodeTagihan($periode),
                'anggota_id' => $anggota->id,
                'siklus_keanggotaan_id' => $siklus->id,
                'jenis_simpanan_id' => $jenis->id,
                'periode' => $periode->toDateString(),
                'nominal_snapshot' => $this->decimalFromCents($nominalCents),
                'interval_bulan_snapshot' => (int) $jenis->interval_bulan,
                'kode_jenis_snapshot' => $jenis->kode,
                'nama_jenis_snapshot' => $jenis->nama_jenis,
                'status' => JadwalSimpananWajib::STATUS_OUTSTANDING,
                'created_by' => $userId,
            ]);
        } catch (UniqueConstraintViolationException|QueryException) {
            $jadwal = JadwalSimpananWajib::query()
                ->where('anggota_id', $anggota->id)
                ->where('siklus_keanggotaan_id', $siklus->id)
                ->where('jenis_simpanan_id', $jenis->id)
                ->whereDate('periode', $periode->toDateString())
                ->lockForUpdate()
                ->first();

            if (! $jadwal) {
                throw ValidationException::withMessages([
                    'simpanan_wajib' => 'Jadwal Simpanan Wajib sudah diproses oleh transaksi lain. Muat ulang lalu coba lagi.',
                ]);
            }
        }

        $this->simpananForSchedule($jadwal, $userId);

        return $jadwal;
    }

    private function simpananForSchedule(JadwalSimpananWajib $jadwal, ?int $userId): Simpanan
    {
        $existing = Simpanan::query()
            ->where('jadwal_simpanan_wajib_id', $jadwal->id)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        $jadwal->loadMissing(['anggota.karyawan', 'jenisSimpanan.akun']);
        $nominalCents = $this->decimalToCents($jadwal->nominal_snapshot);

        $simpanan = Simpanan::query()->create([
            'idempotency_key' => 'simpanan-wajib:jadwal:' . $jadwal->id,
            'anggota_id' => $jadwal->anggota_id,
            'karyawan_id' => $jadwal->anggota?->karyawan_id,
            'siklus_keanggotaan_id' => $jadwal->siklus_keanggotaan_id,
            'jadwal_simpanan_wajib_id' => $jadwal->id,
            'jenis_simpanan_id' => $jadwal->jenis_simpanan_id,
            'kode_jenis_snapshot' => $jadwal->kode_jenis_snapshot,
            'nama_jenis_snapshot' => $jadwal->nama_jenis_snapshot,
            'nominal_snapshot' => $this->decimalFromCents($nominalCents),
            'jumlah' => $this->decimalFromCents($nominalCents),
            'metode_pembayaran' => Simpanan::METODE_POTONG_GAJI,
            'status' => Simpanan::STATUS_PENDING_PAYROLL,
            'tanggal' => $jadwal->periode->toDateString(),
            'created_by' => $userId,
            'keterangan' => 'Tagihan Simpanan Wajib periode ' . $jadwal->periode->format('Y-m'),
        ]);

        $this->akuntansiService->recordSimpananWajibPayroll($simpanan->fresh('jenisSimpanan.akun'), $userId);

        return $simpanan;
    }

    private function createReservedLedgerForJadwal(
        LimitPotongGajiAnggota $limit,
        JadwalSimpananWajib $jadwal,
        int $userId
    ): PemakaianPotongGaji {
        $existingActive = PemakaianPotongGaji::query()
            ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
            ->where('source_type', JadwalSimpananWajib::class)
            ->where('source_id', $jadwal->id)
            ->whereIn('status', [
                PemakaianPotongGaji::STATUS_RESERVED,
                PemakaianPotongGaji::STATUS_CONSUMED,
                PemakaianPotongGaji::STATUS_SETTLED,
            ])
            ->lockForUpdate()
            ->first();

        if ($existingActive) {
            if ((int) $existingActive->limit_potong_gaji_anggota_id === (int) $limit->id) {
                return $existingActive;
            }

            throw ValidationException::withMessages([
                'simpanan_wajib' => 'Jadwal Simpanan Wajib sudah mempunyai ledger payroll aktif.',
            ]);
        }

        try {
            return PemakaianPotongGaji::query()->create([
                'limit_potong_gaji_anggota_id' => $limit->id,
                'kategori' => PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB,
                'source_type' => JadwalSimpananWajib::class,
                'source_id' => $jadwal->id,
                'jenis' => PemakaianPotongGaji::JENIS_RESERVASI,
                'nominal' => $this->decimalFromCents($this->decimalToCents($jadwal->nominal_snapshot)),
                'status' => PemakaianPotongGaji::STATUS_RESERVED,
                'idempotency_key' => 'simpanan-wajib:ledger:' . $limit->id . ':' . $jadwal->id,
                'occurred_at' => now($this->businessTimezone()),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        } catch (UniqueConstraintViolationException) {
            $ledger = PemakaianPotongGaji::query()
                ->where('idempotency_key', 'simpanan-wajib:ledger:' . $limit->id . ':' . $jadwal->id)
                ->first();

            if ($ledger) {
                return $ledger;
            }

            throw ValidationException::withMessages([
                'simpanan_wajib' => 'Reservasi Simpanan Wajib sudah dibuat oleh proses lain. Muat ulang halaman.',
            ]);
        }
    }

    private function activeWajibMasterForUpdate(): JenisSimpanan
    {
        $jenis = JenisSimpanan::query()
            ->with('akun')
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->where('kategori', JenisSimpanan::KATEGORI_WAJIB)
            ->where('aktif', true)
            ->lockForUpdate()
            ->first();

        if (! $jenis) {
            throw ValidationException::withMessages([
                'jenis_simpanan_id' => 'Master Simpanan Wajib aktif belum dikonfigurasi.',
            ]);
        }

        $interval = (int) $jenis->interval_bulan;
        if ($interval < 1 || $interval > 12) {
            throw ValidationException::withMessages([
                'interval_bulan' => 'Interval Simpanan Wajib harus 1 sampai 12 bulan.',
            ]);
        }

        if (! $jenis->akun || ! $jenis->akun->is_aktif || ! in_array($jenis->akun->kategori, ['kewajiban', 'ekuitas'], true) || $jenis->akun->posisi_saldo !== 'kredit') {
            throw ValidationException::withMessages([
                'akun_id' => 'Master Simpanan Wajib wajib memiliki COA aktif kategori Kewajiban/Ekuitas dengan saldo normal Kredit.',
            ]);
        }

        return $jenis;
    }

    private function isPeriodAlreadyConfirmedForAnggota(int $anggotaId, CarbonImmutable $periode): bool
    {
        $periodeRow = PeriodePotongGaji::query()
            ->whereDate('periode', $periode->toDateString())
            ->first();

        if (! $periodeRow) {
            return false;
        }

        if ($periodeRow->status === PeriodePotongGaji::STATUS_CONFIRMED) {
            return true;
        }

        return $periodeRow->limits()
            ->where('anggota_id', $anggotaId)
            ->where('status', LimitPotongGajiAnggota::STATUS_CONFIRMED)
            ->exists();
    }

    private function nextKodeTagihan(CarbonImmutable $periode): string
    {
        $periodeKey = $periode->format('Ym');
        $jenis = 'simpanan_wajib';

        try {
            DB::table('nomor_urut_transaksi')->insert([
                'jenis' => $jenis,
                'periode' => $periodeKey,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
        }

        $counter = DB::table('nomor_urut_transaksi')
            ->where('jenis', $jenis)
            ->where('periode', $periodeKey)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            throw new RuntimeException('Counter nomor Simpanan Wajib tidak dapat dibuat.');
        }

        $next = ((int) $counter->last_number) + 1;

        DB::table('nomor_urut_transaksi')
            ->where('id', $counter->id)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        return sprintf('SWJ-%s-%06d', $periodeKey, $next);
    }

    private function recordPayrollReceiptMutasi(
        string $idempotencyKey,
        DompetKoperasi $dompet,
        int $nominalCents,
        string $keterangan,
        string $referensiTipe,
        int $referensiId,
        string $tanggal
    ): bool {
        $existing = MutasiKas::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return false;
        }

        MutasiKas::query()->create([
            'idempotency_key' => $idempotencyKey,
            'dompet_id' => $dompet->id,
            'tipe' => 'masuk',
            'jumlah' => $this->decimalFromCents($nominalCents),
            'keterangan' => $keterangan,
            'referensi_tipe' => $referensiTipe,
            'referensi_id' => $referensiId,
            'tanggal' => $tanggal,
        ]);

        return true;
    }

    private function increaseSaldoDompet(DompetKoperasi $dompet, int $nominalCents): void
    {
        $saldoCents = $this->decimalToCents($dompet->saldo) + $nominalCents;

        $dompet->update([
            'saldo' => $this->decimalFromCents($saldoCents),
        ]);
    }

    private function limitNominalCents(LimitPotongGajiAnggota $limit): int
    {
        return $this->decimalToCents($limit->limit_nominal);
    }

    private function reservedAndConsumedCents(LimitPotongGajiAnggota $limit): int
    {
        $sum = PemakaianPotongGaji::query()
            ->where('limit_potong_gaji_anggota_id', $limit->id)
            ->whereIn('status', [
                PemakaianPotongGaji::STATUS_RESERVED,
                PemakaianPotongGaji::STATUS_CONSUMED,
            ])
            ->sum('nominal');

        return $this->decimalToCents((string) $sum);
    }

    private function decimalToCents(int|string $value): int
    {
        $normalized = trim((string) $value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');

        if ($normalized === '') {
            return 0;
        }

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
        $whole = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        return ($negative ? '-' : '') . $whole . '.' . str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }
}
