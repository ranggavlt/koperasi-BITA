<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\AlokasiKreditPotongGaji;
use App\Models\CicilanPinjaman;
use App\Models\DompetKoperasi;
use App\Models\JadwalSimpananWajib;
use App\Models\JadwalCicilanPinjaman;
use App\Models\Karyawan;
use App\Models\KreditPotongGajiAnggota;
use App\Models\LimitPotongGajiAnggota;
use App\Models\MutasiKas;
use App\Models\Pembayaran;
use App\Models\PemakaianPotongGaji;
use App\Models\PeriodePotongGaji;
use App\Models\Penjualan;
use App\Models\Pinjaman;
use App\Models\RiwayatLimitPotongGaji;
use App\Models\Simpanan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PotongGajiBulananService
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

    public function createPeriodeDraft(CarbonInterface|string|null $periode = null, ?int $userId = null): PeriodePotongGaji
    {
        $periodeDate = $this->normalizePeriod($periode)->toDateString();

        return DB::transaction(function () use ($periodeDate, $userId): PeriodePotongGaji {
            $existing = PeriodePotongGaji::query()
                ->whereDate('periode', $periodeDate)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                app(SimpananWajibService::class)->generateUntil($existing->periode, null, $userId);

                return $existing;
            }

            $periode = PeriodePotongGaji::query()->create([
                'periode' => $periodeDate,
                'status' => PeriodePotongGaji::STATUS_DRAFT,
                'created_by' => $userId,
            ]);

            app(SimpananWajibService::class)->generateUntil($periode->periode, null, $userId);

            return $periode;
        });
    }

    public function findLimitFor(Anggota|int $anggota, CarbonInterface|string|null $periode = null): ?LimitPotongGajiAnggota
    {
        $anggotaId = $anggota instanceof Anggota ? $anggota->id : $anggota;
        $periodeDate = $this->normalizePeriod($periode)->toDateString();

        return LimitPotongGajiAnggota::query()
            ->where('anggota_id', $anggotaId)
            ->whereHas('periodePotongGaji', fn ($query) => $query->whereDate('periode', $periodeDate))
            ->first();
    }

    public function createLimit(
        Anggota|int $anggota,
        CarbonInterface|string|null $periode,
        int|string $nominal,
        int $changedBy,
        string $alasan
    ): LimitPotongGajiAnggota {
        $this->assertAuditPayload($changedBy, $alasan);
        $nominalDecimal = $this->decimalFromCents($this->decimalToCents($nominal));

        return DB::transaction(function () use ($anggota, $periode, $nominalDecimal, $changedBy, $alasan): LimitPotongGajiAnggota {
            $anggotaModel = $anggota instanceof Anggota
                ? Anggota::query()->with('karyawan')->lockForUpdate()->findOrFail($anggota->id)
                : Anggota::query()->with('karyawan')->lockForUpdate()->findOrFail($anggota);

            $periodeModel = $this->createPeriodeDraft($periode, $changedBy);

            $existing = LimitPotongGajiAnggota::query()
                ->where('periode_potong_gaji_id', $periodeModel->id)
                ->where('anggota_id', $anggotaModel->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'anggota_id' => 'Limit potong gaji Anggota untuk periode ini sudah dibuat.',
                ]);
            }

            $limit = LimitPotongGajiAnggota::query()->create([
                'periode_potong_gaji_id' => $periodeModel->id,
                'anggota_id' => $anggotaModel->id,
                'limit_nominal' => $nominalDecimal,
                'status' => LimitPotongGajiAnggota::STATUS_DRAFT,
            ]);

            $this->recordHistory($limit, '0.00', $nominalDecimal, $changedBy, $alasan);

            return $limit->fresh(['periodePotongGaji', 'anggota.karyawan', 'pemakaian']);
        });
    }

    public function updateLimit(
        LimitPotongGajiAnggota $limit,
        int|string $nominal,
        int $changedBy,
        string $alasan
    ): LimitPotongGajiAnggota {
        $this->assertAuditPayload($changedBy, $alasan);
        $nominalCents = $this->decimalToCents($nominal);
        $nominalDecimal = $this->decimalFromCents($nominalCents);

        return DB::transaction(function () use ($limit, $nominalCents, $nominalDecimal, $changedBy, $alasan): LimitPotongGajiAnggota {
            $locked = LimitPotongGajiAnggota::query()
                ->with('periodePotongGaji')
                ->lockForUpdate()
                ->findOrFail($limit->id);

            if (in_array($locked->status, [
                LimitPotongGajiAnggota::STATUS_CONFIRMED,
                LimitPotongGajiAnggota::STATUS_CANCELLED,
                LimitPotongGajiAnggota::STATUS_CLOSED_PENDING_CONFIRMATION,
            ], true)) {
                throw ValidationException::withMessages([
                    'limit_nominal' => 'Limit yang sudah ditutup, cancelled, atau confirmed tidak dapat diedit.',
                ]);
            }

            if ($locked->status === LimitPotongGajiAnggota::STATUS_ACTIVE) {
                $minimumCents = $this->reservedAndConsumedCents($locked);

                if ($nominalCents < $minimumCents) {
                    throw ValidationException::withMessages([
                        'limit_nominal' => 'Limit aktif tidak boleh diturunkan di bawah total reservasi dan pemakaian.',
                    ]);
                }
            }

            $before = (string) $locked->limit_nominal;

            $locked->update(['limit_nominal' => $nominalDecimal]);
            $this->recordHistory($locked, $before, $nominalDecimal, $changedBy, $alasan);

            if ($locked->status === LimitPotongGajiAnggota::STATUS_ACTIVE) {
                app(SimpananWajibService::class)->reserveOutstandingForLimit($locked, $changedBy);
            }

            return $locked->fresh(['periodePotongGaji', 'anggota.karyawan', 'pemakaian']);
        });
    }

    public function activateLimit(LimitPotongGajiAnggota $limit, int $userId): LimitPotongGajiAnggota
    {
        return DB::transaction(function () use ($limit, $userId): LimitPotongGajiAnggota {
            $locked = LimitPotongGajiAnggota::query()
                ->with(['anggota.karyawan', 'periodePotongGaji'])
                ->lockForUpdate()
                ->findOrFail($limit->id);

            if ($locked->status === LimitPotongGajiAnggota::STATUS_ACTIVE) {
                return $locked->fresh(['periodePotongGaji', 'anggota.karyawan', 'pemakaian']);
            }

            $this->assertLimitStatus($locked, LimitPotongGajiAnggota::STATUS_DRAFT, 'Limit hanya dapat diaktifkan dari status draft.');
            $this->assertAnggotaEligible($locked->anggota);
            $this->assertPreviousLimitConfirmed($locked);

            $now = now($this->businessTimezone());
            $locked->update([
                'status' => LimitPotongGajiAnggota::STATUS_ACTIVE,
                'activated_by' => $userId,
                'activated_at' => $now,
            ]);

            $periode = PeriodePotongGaji::query()->lockForUpdate()->findOrFail($locked->periode_potong_gaji_id);
            if ($periode->status === PeriodePotongGaji::STATUS_DRAFT) {
                $periode->update([
                    'status' => PeriodePotongGaji::STATUS_ACTIVE,
                    'activated_by' => $userId,
                    'activated_at' => $now,
                    'updated_by' => $userId,
                ]);
            }

            $this->reserveScheduledInstallmentForLimit($locked, $userId);
            $this->allocatePendingSimpananPokokForLimit($locked, $userId);
            app(SimpananWajibService::class)->reserveOutstandingForLimit($locked, $userId);

            return $locked->fresh(['periodePotongGaji', 'anggota.karyawan', 'pemakaian']);
        });
    }

    public function closeLimit(LimitPotongGajiAnggota $limit, int $userId): LimitPotongGajiAnggota
    {
        return DB::transaction(function () use ($limit, $userId): LimitPotongGajiAnggota {
            $locked = LimitPotongGajiAnggota::query()
                ->with('periodePotongGaji')
                ->lockForUpdate()
                ->findOrFail($limit->id);

            $this->assertLimitStatus($locked, LimitPotongGajiAnggota::STATUS_ACTIVE, 'Limit hanya dapat ditutup dari status active.');

            $now = now($this->businessTimezone());
            $locked->update([
                'status' => LimitPotongGajiAnggota::STATUS_CLOSED_PENDING_CONFIRMATION,
                'closed_by' => $userId,
                'closed_at' => $now,
            ]);

            $periode = PeriodePotongGaji::query()->lockForUpdate()->findOrFail($locked->periode_potong_gaji_id);
            if ($periode->status === PeriodePotongGaji::STATUS_ACTIVE) {
                $periode->update([
                    'status' => PeriodePotongGaji::STATUS_CLOSED,
                    'closed_by' => $userId,
                    'closed_at' => $now,
                    'updated_by' => $userId,
                ]);
            }

            return $locked->fresh(['periodePotongGaji', 'anggota.karyawan', 'pemakaian']);
        });
    }

    public function confirmLimit(LimitPotongGajiAnggota $limit, int $userId): LimitPotongGajiAnggota
    {
        return DB::transaction(function () use ($limit, $userId): LimitPotongGajiAnggota {
            $locked = LimitPotongGajiAnggota::query()
                ->with(['periodePotongGaji', 'anggota.karyawan'])
                ->lockForUpdate()
                ->findOrFail($limit->id);

            $this->assertLimitStatus(
                $locked,
                LimitPotongGajiAnggota::STATUS_CLOSED_PENDING_CONFIRMATION,
                'Limit hanya dapat dikonfirmasi setelah ditutup.'
            );

            $payrollEntries = PemakaianPotongGaji::query()
                ->where('limit_potong_gaji_anggota_id', $locked->id)
                ->where(function ($query): void {
                    $query->where(function ($subQuery): void {
                        $subQuery->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN)
                            ->where('source_type', JadwalCicilanPinjaman::class)
                            ->where('status', PemakaianPotongGaji::STATUS_RESERVED);
                    })->orWhere(function ($subQuery): void {
                        $subQuery->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
                            ->where('source_type', JadwalSimpananWajib::class)
                            ->where('status', PemakaianPotongGaji::STATUS_RESERVED);
                    })->orWhere(function ($subQuery): void {
                        $subQuery->whereIn('kategori', [
                            PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK,
                            PemakaianPotongGaji::KATEGORI_POS,
                        ])->where('status', PemakaianPotongGaji::STATUS_CONSUMED);
                    });
                })
                ->lockForUpdate()
                ->get();

            $dompetPayroll = $payrollEntries->isNotEmpty()
                ? $this->defaultPayrollBankForUpdate()
                : null;

            $creditByEntry = $this->prepareCreditApplications($locked, $payrollEntries, $userId);

            foreach ($this->sortPayrollEntriesForSettlement($payrollEntries) as $entry) {
                $creditCents = $creditByEntry[$entry->id] ?? 0;

                if ($entry->kategori === PemakaianPotongGaji::KATEGORI_CICILAN) {
                    $this->settlePayrollReservation($locked, $entry, $dompetPayroll, $userId, $creditCents);
                    continue;
                }

                if ($entry->kategori === PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK) {
                    $this->settleSimpananPokokUsage($locked, $entry, $dompetPayroll, $userId, $creditCents);
                    continue;
                }

                if ($entry->kategori === PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB) {
                    app(SimpananWajibService::class)->settleUsage($locked, $entry, $dompetPayroll, $userId, $creditCents);
                    continue;
                }

                if ($entry->kategori === PemakaianPotongGaji::KATEGORI_POS) {
                    $this->settlePosUsage($locked, $entry, $dompetPayroll, $userId, $creditCents);
                }
            }

            $locked->update([
                'status' => LimitPotongGajiAnggota::STATUS_CONFIRMED,
                'dompet_penerimaan_id' => $dompetPayroll?->id,
                'confirmed_by' => $userId,
                'confirmed_at' => now($this->businessTimezone()),
            ]);

            $this->confirmHeaderWhenAllLimitsConfirmed($locked->periodePotongGaji, $userId);

            return $locked->fresh(['periodePotongGaji', 'anggota.karyawan', 'pemakaian', 'dompetPenerimaan']);
        });
    }

    public function reserveFullPayoffPayroll(LimitPotongGajiAnggota $limit, int $userId): LimitPotongGajiAnggota
    {
        return DB::transaction(function () use ($limit, $userId): LimitPotongGajiAnggota {
            $locked = LimitPotongGajiAnggota::query()
                ->with(['anggota.karyawan', 'periodePotongGaji'])
                ->lockForUpdate()
                ->findOrFail($limit->id);

            $this->assertLimitStatus($locked, LimitPotongGajiAnggota::STATUS_ACTIVE, 'Pelunasan payroll hanya dapat dibuat pada limit active.');
            $this->assertAnggotaEligible($locked->anggota);

            $pinjaman = $this->activePinjamanForUpdate($locked->anggota_id);

            if (! $pinjaman) {
                throw ValidationException::withMessages([
                    'pinjaman' => 'Anggota tidak mempunyai Pinjaman aktif untuk dilunasi.',
                ]);
            }

            $unpaidSchedules = $pinjaman->jadwalCicilan()
                ->where('status', '!=', JadwalCicilanPinjaman::STATUS_PAID)
                ->orderBy('angsuran_ke')
                ->lockForUpdate()
                ->get();

            $additionalCents = 0;
            foreach ($unpaidSchedules as $jadwal) {
                if ($this->activeLedgerForScheduleExists($jadwal)) {
                    continue;
                }

                $additionalCents += $this->decimalToCents($jadwal->nominal_pokok);
            }

            if ($additionalCents <= 0) {
                return $locked->fresh(['periodePotongGaji', 'anggota.karyawan', 'pemakaian']);
            }

            $availableCents = $this->limitNominalCents($locked) - $this->reservedAndConsumedCents($locked);

            if ($additionalCents > $availableCents) {
                throw ValidationException::withMessages([
                    'limit_nominal' => 'Sisa limit bulan berjalan tidak mencukupi untuk pelunasan penuh.',
                ]);
            }

            foreach ($unpaidSchedules as $jadwal) {
                if ($this->activeLedgerForScheduleExists($jadwal)) {
                    continue;
                }

                $this->createReservedLedgerForJadwal($locked, $jadwal, $userId, 'pelunasan-payroll');
                $jadwal->update([
                    'status' => JadwalCicilanPinjaman::STATUS_RESERVED,
                    'metode_penyelesaian' => JadwalCicilanPinjaman::METODE_POTONG_GAJI,
                ]);
            }

            return $locked->fresh(['periodePotongGaji', 'anggota.karyawan', 'pemakaian']);
        });
    }

    public function createLedgerEntry(LimitPotongGajiAnggota $limit, array $data): PemakaianPotongGaji
    {
        return DB::transaction(function () use ($limit, $data): PemakaianPotongGaji {
            $locked = LimitPotongGajiAnggota::query()->lockForUpdate()->findOrFail($limit->id);

            $this->assertLimitStatus($locked, LimitPotongGajiAnggota::STATUS_ACTIVE, 'Pemakaian baru hanya dapat dicatat pada limit active.');

            $jenis = (string) ($data['jenis'] ?? PemakaianPotongGaji::JENIS_PEMAKAIAN);
            $status = (string) ($data['status'] ?? (
                $jenis === PemakaianPotongGaji::JENIS_RESERVASI
                    ? PemakaianPotongGaji::STATUS_RESERVED
                    : PemakaianPotongGaji::STATUS_CONSUMED
            ));
            $nominalCents = $this->decimalToCents($data['nominal']);

            if (in_array($status, [PemakaianPotongGaji::STATUS_RESERVED, PemakaianPotongGaji::STATUS_CONSUMED], true)) {
                $availableCents = $this->limitNominalCents($locked) - $this->reservedAndConsumedCents($locked);

                if ($nominalCents > $availableCents) {
                    throw ValidationException::withMessages([
                        'nominal' => 'Sisa limit potong gaji tidak mencukupi.',
                    ]);
                }
            }

            return PemakaianPotongGaji::query()->create([
                'limit_potong_gaji_anggota_id' => $locked->id,
                'kategori' => $data['kategori'],
                'source_type' => $data['source_type'],
                'source_id' => $data['source_id'],
                'jenis' => $jenis,
                'nominal' => $this->decimalFromCents($nominalCents),
                'status' => $status,
                'idempotency_key' => $data['idempotency_key'],
                'occurred_at' => $data['occurred_at'] ?? now($this->businessTimezone()),
                'created_by' => $data['created_by'] ?? null,
                'updated_by' => $data['updated_by'] ?? null,
            ]);
        });
    }

    public function reverseLedgerEntry(PemakaianPotongGaji $pemakaian, int $userId, string $idempotencyKey): PemakaianPotongGaji
    {
        return DB::transaction(function () use ($pemakaian, $userId, $idempotencyKey): PemakaianPotongGaji {
            $locked = PemakaianPotongGaji::query()->lockForUpdate()->findOrFail($pemakaian->id);
            $now = now($this->businessTimezone());

            if ($locked->status !== PemakaianPotongGaji::STATUS_REVERSED) {
                $locked->update([
                    'status' => PemakaianPotongGaji::STATUS_REVERSED,
                    'reversed_by' => $userId,
                    'reversed_at' => $now,
                ]);
            }

            $existing = PemakaianPotongGaji::query()
                ->where('source_type', PemakaianPotongGaji::class)
                ->where('source_id', $locked->id)
                ->where('kategori', $locked->kategori)
                ->first();

            if ($existing) {
                return $existing;
            }

            return PemakaianPotongGaji::query()->create([
                'limit_potong_gaji_anggota_id' => $locked->limit_potong_gaji_anggota_id,
                'kategori' => $locked->kategori,
                'source_type' => PemakaianPotongGaji::class,
                'source_id' => $locked->id,
                'jenis' => $locked->jenis,
                'nominal' => $this->decimalFromCents(-1 * $this->decimalToCents($locked->nominal)),
                'status' => PemakaianPotongGaji::STATUS_REVERSED,
                'idempotency_key' => $idempotencyKey,
                'occurred_at' => $now,
                'reversed_at' => $now,
                'reversal_of_id' => $locked->id,
                'created_by' => $userId,
                'updated_by' => $userId,
                'reversed_by' => $userId,
            ]);
        });
    }

    public function payScheduledCash(Pinjaman $pinjaman, DompetKoperasi $dompet, int $userId): CicilanPinjaman
    {
        return DB::transaction(function () use ($pinjaman, $dompet, $userId): CicilanPinjaman {
            $lockedPinjaman = Pinjaman::query()
                ->with('anggota.karyawan')
                ->lockForUpdate()
                ->findOrFail($pinjaman->id);

            $this->assertCashAllowed($lockedPinjaman);
            $dompetKas = $this->cashDompetForUpdate($dompet->id);
            $jadwal = $this->firstDueUnpaidScheduleForUpdate($lockedPinjaman);

            return $this->payJadwal($jadwal, $dompetKas, CicilanPinjaman::METODE_TUNAI, $userId, 'tunai-terjadwal');
        });
    }

    /**
     * @return Collection<int, CicilanPinjaman>
     */
    public function payFullCash(Pinjaman $pinjaman, DompetKoperasi $dompet, int $userId): Collection
    {
        return DB::transaction(function () use ($pinjaman, $dompet, $userId): Collection {
            $lockedPinjaman = Pinjaman::query()
                ->with('anggota.karyawan')
                ->lockForUpdate()
                ->findOrFail($pinjaman->id);

            $this->assertCashAllowed($lockedPinjaman);
            $dompetKas = $this->cashDompetForUpdate($dompet->id);

            $jadwalRows = $lockedPinjaman->jadwalCicilan()
                ->where('status', '!=', JadwalCicilanPinjaman::STATUS_PAID)
                ->orderBy('angsuran_ke')
                ->lockForUpdate()
                ->get();

            if ($jadwalRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'pinjaman' => 'Tidak ada jadwal unpaid untuk dilunasi.',
                ]);
            }

            $payments = new Collection();

            foreach ($jadwalRows as $jadwal) {
                $payments->push($this->payJadwal($jadwal, $dompetKas, CicilanPinjaman::METODE_TUNAI, $userId, 'tunai-lunas'));
            }

            return $payments;
        });
    }

    public function releaseReservationsForStoppedAnggota(Anggota $anggota, ?int $userId = null): void
    {
        DB::transaction(function () use ($anggota, $userId): void {
            $limits = LimitPotongGajiAnggota::query()
                ->where('anggota_id', $anggota->id)
                ->whereIn('status', [
                    LimitPotongGajiAnggota::STATUS_DRAFT,
                    LimitPotongGajiAnggota::STATUS_ACTIVE,
                    LimitPotongGajiAnggota::STATUS_CLOSED_PENDING_CONFIRMATION,
                ])
                ->lockForUpdate()
                ->get();

            foreach ($limits as $limit) {
                $reservations = PemakaianPotongGaji::query()
                    ->where('limit_potong_gaji_anggota_id', $limit->id)
                    ->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN)
                    ->where('source_type', JadwalCicilanPinjaman::class)
                    ->where('status', PemakaianPotongGaji::STATUS_RESERVED)
                    ->lockForUpdate()
                    ->get();

                foreach ($reservations as $reservation) {
                    $jadwal = JadwalCicilanPinjaman::query()->lockForUpdate()->find($reservation->source_id);

                    $reservation->update([
                        'status' => PemakaianPotongGaji::STATUS_RELEASED,
                        'released_at' => now($this->businessTimezone()),
                        'released_by' => $userId,
                        'release_reason' => 'Karyawan berhenti sebelum payroll confirmed.',
                        'updated_by' => $userId,
                    ]);

                    if ($jadwal && $jadwal->status === JadwalCicilanPinjaman::STATUS_RESERVED) {
                        $jadwal->update([
                            'status' => JadwalCicilanPinjaman::STATUS_SCHEDULED,
                            'metode_penyelesaian' => null,
                        ]);
                    }
                }

                app(SimpananWajibService::class)->releaseReservationsForLimit(
                    $limit,
                    $userId,
                    'Karyawan berhenti sebelum payroll confirmed; tagihan Simpanan Wajib tetap outstanding.'
                );

                $usages = PemakaianPotongGaji::query()
                    ->where('limit_potong_gaji_anggota_id', $limit->id)
                    ->whereIn('kategori', [
                        PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK,
                        PemakaianPotongGaji::KATEGORI_POS,
                    ])
                    ->where('status', PemakaianPotongGaji::STATUS_CONSUMED)
                    ->lockForUpdate()
                    ->get();

                foreach ($usages as $usage) {
                    $usage->update([
                        'status' => PemakaianPotongGaji::STATUS_RELEASED,
                        'released_at' => now($this->businessTimezone()),
                        'released_by' => $userId,
                        'release_reason' => 'Karyawan berhenti sebelum payroll confirmed; kewajiban harus ditagih tunai.',
                        'updated_by' => $userId,
                    ]);

                    if ($usage->kategori === PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK && $usage->source_type === Simpanan::class) {
                        Simpanan::query()
                            ->whereKey($usage->source_id)
                            ->whereIn('status', [Simpanan::STATUS_PENDING_PAYROLL, Simpanan::STATUS_ALLOCATED])
                            ->update(['status' => Simpanan::STATUS_OUTSTANDING_CASH]);
                    }

                    if ($usage->kategori === PemakaianPotongGaji::KATEGORI_POS && $usage->source_type === Penjualan::class) {
                        Pembayaran::query()
                            ->where('pemakaian_potong_gaji_id', $usage->id)
                            ->where('status', Pembayaran::STATUS_PENDING_PAYROLL)
                            ->update(['status' => Pembayaran::STATUS_OUTSTANDING_CASH]);
                    }
                }

                $limit->update([
                    'status' => LimitPotongGajiAnggota::STATUS_CANCELLED,
                    'cancelled_by' => $userId,
                    'cancelled_at' => now($this->businessTimezone()),
                    'cancellation_reason' => 'Karyawan berhenti sebelum payroll confirmed.',
                ]);
            }

            Simpanan::query()
                ->where('anggota_id', $anggota->id)
                ->where('kode_jenis_snapshot', \App\Models\JenisSimpanan::KODE_SIMPANAN_POKOK)
                ->whereIn('status', [Simpanan::STATUS_PENDING_PAYROLL, Simpanan::STATUS_ALLOCATED])
                ->lockForUpdate()
                ->get()
                ->each(fn (Simpanan $simpanan) => $simpanan->update(['status' => Simpanan::STATUS_OUTSTANDING_CASH]));
        });
    }

    public function reservedAndConsumedCents(LimitPotongGajiAnggota $limit): int
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

    private function reserveScheduledInstallmentForLimit(LimitPotongGajiAnggota $limit, int $userId): void
    {
        $pinjaman = $this->activePinjamanForUpdate($limit->anggota_id);

        if (! $pinjaman) {
            return;
        }

        $periode = $limit->periodePotongGaji->periode->toDateString();

        $jadwal = $pinjaman->jadwalCicilan()
            ->whereDate('periode', $periode)
            ->lockForUpdate()
            ->first();

        if (! $jadwal || in_array($jadwal->status, [
            JadwalCicilanPinjaman::STATUS_PAID,
            JadwalCicilanPinjaman::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        if ($this->activeLedgerForScheduleExists($jadwal)) {
            return;
        }

        $nominalCents = $this->decimalToCents($jadwal->nominal_pokok);

        if ($nominalCents > $this->limitNominalCents($limit)) {
            throw ValidationException::withMessages([
                'limit_nominal' => 'Limit tidak mencukupi untuk reservasi cicilan bulan ini.',
            ]);
        }

        $this->createReservedLedgerForJadwal($limit, $jadwal, $userId, 'reservasi-bulanan');

        $jadwal->update([
            'status' => JadwalCicilanPinjaman::STATUS_RESERVED,
            'metode_penyelesaian' => JadwalCicilanPinjaman::METODE_POTONG_GAJI,
        ]);
    }

    private function allocatePendingSimpananPokokForLimit(LimitPotongGajiAnggota $limit, int $userId): void
    {
        $pendingRows = Simpanan::query()
            ->where('anggota_id', $limit->anggota_id)
            ->where('kode_jenis_snapshot', \App\Models\JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->where('status', Simpanan::STATUS_PENDING_PAYROLL)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($pendingRows as $simpanan) {
            $nominalCents = $this->decimalToCents($simpanan->nominal_snapshot ?? $simpanan->jumlah);
            $availableCents = $this->limitNominalCents($limit) - $this->reservedAndConsumedCents($limit);

            if ($nominalCents > $availableCents) {
                throw ValidationException::withMessages([
                    'limit_nominal' => 'Sisa limit setelah reservasi cicilan tidak mencukupi untuk Simpanan Pokok.',
                ]);
            }

            try {
                $ledger = PemakaianPotongGaji::query()->create([
                    'limit_potong_gaji_anggota_id' => $limit->id,
                    'kategori' => PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK,
                    'source_type' => Simpanan::class,
                    'source_id' => $simpanan->id,
                    'jenis' => PemakaianPotongGaji::JENIS_PEMAKAIAN,
                    'nominal' => $this->decimalFromCents($nominalCents),
                    'status' => PemakaianPotongGaji::STATUS_CONSUMED,
                    'idempotency_key' => 'simpanan-pokok:ledger:' . $simpanan->id,
                    'occurred_at' => now($this->businessTimezone()),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            } catch (UniqueConstraintViolationException) {
                $ledger = PemakaianPotongGaji::query()
                    ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK)
                    ->where('source_type', Simpanan::class)
                    ->where('source_id', $simpanan->id)
                    ->first();

                if (! $ledger) {
                    throw ValidationException::withMessages([
                        'simpanan_pokok' => 'Simpanan Pokok sudah dialokasikan oleh proses lain. Muat ulang halaman.',
                    ]);
                }
            }

            $simpanan->update([
                'pemakaian_potong_gaji_id' => $ledger->id,
                'metode_pembayaran' => Simpanan::METODE_POTONG_GAJI,
                'status' => Simpanan::STATUS_ALLOCATED,
            ]);
        }
    }

    private function createReservedLedgerForJadwal(
        LimitPotongGajiAnggota $limit,
        JadwalCicilanPinjaman $jadwal,
        int $userId,
        string $scope
    ): PemakaianPotongGaji {
        try {
            return PemakaianPotongGaji::query()->create([
                'limit_potong_gaji_anggota_id' => $limit->id,
                'kategori' => PemakaianPotongGaji::KATEGORI_CICILAN,
                'source_type' => JadwalCicilanPinjaman::class,
                'source_id' => $jadwal->id,
                'jenis' => PemakaianPotongGaji::JENIS_RESERVASI,
                'nominal' => $this->decimalFromCents($this->decimalToCents($jadwal->nominal_pokok)),
                'status' => PemakaianPotongGaji::STATUS_RESERVED,
                'idempotency_key' => "potong-gaji:{$scope}:{$limit->id}:{$jadwal->id}",
                'occurred_at' => now($this->businessTimezone()),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = PemakaianPotongGaji::query()
                ->where('source_type', JadwalCicilanPinjaman::class)
                ->where('source_id', $jadwal->id)
                ->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN)
                ->first();

            if ($existing) {
                return $existing;
            }

            throw ValidationException::withMessages([
                'cicilan' => 'Reservasi cicilan sudah pernah dibuat. Muat ulang halaman lalu coba kembali.',
            ]);
        }
    }

    private function settlePayrollReservation(
        LimitPotongGajiAnggota $limit,
        PemakaianPotongGaji $reservation,
        DompetKoperasi $dompetPayroll,
        int $userId,
        int $creditCents = 0
    ): CicilanPinjaman {
        $jadwal = JadwalCicilanPinjaman::query()
            ->with('pinjaman')
            ->lockForUpdate()
            ->findOrFail($reservation->source_id);

        if ($jadwal->status === JadwalCicilanPinjaman::STATUS_PAID) {
            $reservation->update([
                'status' => PemakaianPotongGaji::STATUS_SETTLED,
                'settled_at' => now($this->businessTimezone()),
                'updated_by' => $userId,
            ]);

            return $jadwal->cicilanPembayaran()->firstOrFail();
        }

        if ($this->decimalToCents($reservation->nominal) !== $this->decimalToCents($jadwal->nominal_pokok)) {
            throw ValidationException::withMessages([
                'cicilan' => 'Nominal reservasi cicilan tidak sama dengan nominal jadwal.',
            ]);
        }

        if ((int) $jadwal->pinjaman->anggota_id !== (int) $limit->anggota_id) {
            throw ValidationException::withMessages([
                'cicilan' => 'Reservasi cicilan tidak sesuai dengan Anggota pada limit.',
            ]);
        }

        $payment = $this->payJadwal($jadwal, $dompetPayroll, CicilanPinjaman::METODE_POTONG_GAJI, $userId, 'payroll', $creditCents);

        $reservation->update([
            'status' => PemakaianPotongGaji::STATUS_SETTLED,
            'settled_at' => now($this->businessTimezone()),
            'updated_by' => $userId,
        ]);

        return $payment;
    }

    private function settleSimpananPokokUsage(
        LimitPotongGajiAnggota $limit,
        PemakaianPotongGaji $usage,
        DompetKoperasi $dompetPayroll,
        int $userId,
        int $creditCents = 0
    ): Simpanan {
        if ($usage->source_type !== Simpanan::class || $usage->status !== PemakaianPotongGaji::STATUS_CONSUMED) {
            throw ValidationException::withMessages([
                'simpanan_pokok' => 'Ledger Simpanan Pokok tidak valid untuk settlement payroll.',
            ]);
        }

        $simpanan = Simpanan::query()
            ->lockForUpdate()
            ->findOrFail($usage->source_id);

        if ((int) $simpanan->anggota_id !== (int) $limit->anggota_id) {
            throw ValidationException::withMessages([
                'simpanan_pokok' => 'Simpanan Pokok tidak sesuai dengan Anggota pada limit.',
            ]);
        }

        if (! $simpanan->isSimpananPokok()) {
            throw ValidationException::withMessages([
                'simpanan_pokok' => 'Ledger payroll Simpanan hanya boleh untuk Simpanan Pokok.',
            ]);
        }

        $nominalCents = $this->decimalToCents($simpanan->nominal_snapshot ?? $simpanan->jumlah);
        if ($this->decimalToCents($usage->nominal) !== $nominalCents) {
            throw ValidationException::withMessages([
                'simpanan_pokok' => 'Nominal ledger Simpanan Pokok tidak sama dengan snapshot transaksi.',
            ]);
        }

        if ($simpanan->status === Simpanan::STATUS_SETTLED) {
            $usage->update([
                'status' => PemakaianPotongGaji::STATUS_SETTLED,
                'settled_at' => now($this->businessTimezone()),
                'updated_by' => $userId,
            ]);

            return $simpanan;
        }

        if (! in_array($simpanan->status, [Simpanan::STATUS_PENDING_PAYROLL, Simpanan::STATUS_ALLOCATED], true)) {
            throw ValidationException::withMessages([
                'simpanan_pokok' => 'Status Simpanan Pokok tidak dapat disettle lewat payroll.',
            ]);
        }

        $creditCents = min($creditCents, $nominalCents);
        $netCents = $nominalCents - $creditCents;

        if ($netCents > 0 && $this->recordPayrollReceiptMutasi(
            'simpanan-pokok:payroll:mutasi:' . $usage->id,
            $dompetPayroll,
            $netCents,
            'Penerimaan payroll Simpanan Pokok Anggota',
            PemakaianPotongGaji::class,
            $usage->id,
            now($this->businessTimezone())->toDateString()
        )) {
            $this->increaseSaldoDompet($dompetPayroll, $netCents);
        }

        $this->akuntansiService->recordPenerimaanPayrollPotongGajiNet(
            'simpanan-pokok:payroll:jurnal:' . $usage->id,
            'PG-SMP-' . $usage->id,
            now($this->businessTimezone())->toDateString(),
            (float) $this->decimalFromCents($nominalCents),
            (float) $this->decimalFromCents($creditCents),
            $dompetPayroll->akun,
            PemakaianPotongGaji::class,
            $usage->id,
            $userId
        );

        $simpanan->update([
            'pemakaian_potong_gaji_id' => $usage->id,
            'metode_pembayaran' => Simpanan::METODE_POTONG_GAJI,
            'status' => Simpanan::STATUS_SETTLED,
            'settled_at' => now($this->businessTimezone()),
        ]);

        $usage->update([
            'status' => PemakaianPotongGaji::STATUS_SETTLED,
            'settled_at' => now($this->businessTimezone()),
            'updated_by' => $userId,
        ]);

        return $simpanan->fresh(['ledger', 'jurnal.details']);
    }

    private function settlePosUsage(
        LimitPotongGajiAnggota $limit,
        PemakaianPotongGaji $usage,
        DompetKoperasi $dompetPayroll,
        int $userId,
        int $creditCents = 0
    ): Pembayaran {
        if ($usage->source_type !== Penjualan::class || $usage->status !== PemakaianPotongGaji::STATUS_CONSUMED) {
            throw ValidationException::withMessages([
                'pos' => 'Ledger POS tidak valid untuk settlement payroll.',
            ]);
        }

        $penjualan = Penjualan::query()
            ->with('pembayaran')
            ->lockForUpdate()
            ->findOrFail($usage->source_id);

        if ((int) $penjualan->anggota_id !== (int) $limit->anggota_id) {
            throw ValidationException::withMessages([
                'pos' => 'Penjualan POS tidak sesuai dengan Anggota pada limit.',
            ]);
        }

        $pembayaran = Pembayaran::query()
            ->where('penjualan_id', $penjualan->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ((int) $pembayaran->pemakaian_potong_gaji_id !== (int) $usage->id) {
            throw ValidationException::withMessages([
                'pos' => 'Pembayaran POS tidak terhubung ke ledger payroll yang benar.',
            ]);
        }

        $nominalCents = $this->decimalToCents($penjualan->grand_total);
        if ($this->decimalToCents($usage->nominal) !== $nominalCents) {
            throw ValidationException::withMessages([
                'pos' => 'Nominal ledger POS tidak sama dengan grand total Penjualan.',
            ]);
        }

        if ($pembayaran->status === Pembayaran::STATUS_PAID) {
            $usage->update([
                'status' => PemakaianPotongGaji::STATUS_SETTLED,
                'settled_at' => now($this->businessTimezone()),
                'updated_by' => $userId,
            ]);

            return $pembayaran;
        }

        if ($pembayaran->status !== Pembayaran::STATUS_PENDING_PAYROLL) {
            throw ValidationException::withMessages([
                'pos' => 'Status Pembayaran POS tidak dapat disettle lewat payroll.',
            ]);
        }

        $creditCents = min($creditCents, $nominalCents);
        $netCents = $nominalCents - $creditCents;

        if ($netCents > 0 && $this->recordPayrollReceiptMutasi(
            'pos:payroll:mutasi:' . $pembayaran->id,
            $dompetPayroll,
            $netCents,
            'Penerimaan payroll POS ' . $penjualan->kode_transaksi,
            Pembayaran::class,
            $pembayaran->id,
            now($this->businessTimezone())->toDateString()
        )) {
            $this->increaseSaldoDompet($dompetPayroll, $netCents);
        }

        $this->akuntansiService->recordPenerimaanPayrollPotongGajiNet(
            'pos:payroll:jurnal:' . $pembayaran->id,
            'PG-POS-' . $pembayaran->id,
            now($this->businessTimezone())->toDateString(),
            (float) $this->decimalFromCents($nominalCents),
            (float) $this->decimalFromCents($creditCents),
            $dompetPayroll->akun,
            Pembayaran::class,
            $pembayaran->id,
            $userId
        );

        $pembayaran->update([
            'status' => Pembayaran::STATUS_PAID,
            'dompet_id' => $dompetPayroll->id,
            'paid_at' => now($this->businessTimezone()),
        ]);

        $usage->update([
            'status' => PemakaianPotongGaji::STATUS_SETTLED,
            'settled_at' => now($this->businessTimezone()),
            'updated_by' => $userId,
        ]);

        return $pembayaran->fresh(['penjualan', 'dompet', 'ledger']);
    }

    /**
     * @param  Collection<int, PemakaianPotongGaji>  $entries
     * @return Collection<int, PemakaianPotongGaji>
     */
    private function sortPayrollEntriesForSettlement(Collection $entries): Collection
    {
        $priority = [
            PemakaianPotongGaji::KATEGORI_CICILAN => 10,
            PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK => 20,
            PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB => 30,
            PemakaianPotongGaji::KATEGORI_POS => 40,
        ];

        return $entries
            ->sortBy(fn (PemakaianPotongGaji $entry): string => sprintf('%02d-%010d', $priority[$entry->kategori] ?? 99, $entry->id))
            ->values();
    }

    private function payJadwal(
        JadwalCicilanPinjaman $jadwal,
        DompetKoperasi $dompet,
        string $metode,
        int $userId,
        string $scope,
        int $creditCents = 0
    ): CicilanPinjaman {
        $jadwal = JadwalCicilanPinjaman::query()
            ->with('pinjaman')
            ->lockForUpdate()
            ->findOrFail($jadwal->id);

        if ($jadwal->status === JadwalCicilanPinjaman::STATUS_PAID) {
            throw ValidationException::withMessages([
                'jadwal' => 'Jadwal cicilan ini sudah dibayar.',
            ]);
        }

        if ($jadwal->cicilanPembayaran()->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'jadwal' => 'Jadwal cicilan ini sudah mempunyai pembayaran.',
            ]);
        }

        $pinjaman = Pinjaman::query()
            ->with('anggota.karyawan')
            ->lockForUpdate()
            ->findOrFail($jadwal->pinjaman_id);

        $nominalCents = $this->decimalToCents($jadwal->nominal_pokok);
        $period = $jadwal->periode->format('Y-m');

        $payment = CicilanPinjaman::query()->create([
            'idempotency_key' => "cicilan:{$scope}:{$jadwal->id}",
            'pinjaman_id' => $pinjaman->id,
            'anggota_id' => $pinjaman->anggota_id,
            'jadwal_cicilan_pinjaman_id' => $jadwal->id,
            'jumlah_cicilan' => $this->decimalFromCents($nominalCents),
            'metode_pembayaran' => $metode,
            'dompet_id' => $dompet->id,
            'periode' => $period,
            'status' => CicilanPinjaman::STATUS_SUDAH_BAYAR,
            'created_by' => $userId,
            'tanggal_bayar' => now($this->businessTimezone())->toDateString(),
        ]);

        $jadwal->update([
            'status' => JadwalCicilanPinjaman::STATUS_PAID,
            'metode_penyelesaian' => $metode,
            'paid_at' => now($this->businessTimezone()),
        ]);

        $sisaCents = max(0, $this->decimalToCents($pinjaman->sisa_pinjaman) - $nominalCents);
        $pinjaman->update([
            'sisa_pinjaman' => $this->decimalFromCents($sisaCents),
            'status' => $sisaCents === 0 ? Pinjaman::STATUS_LUNAS : Pinjaman::STATUS_AKTIF,
        ]);

        $creditCents = $metode === CicilanPinjaman::METODE_POTONG_GAJI ? min($creditCents, $nominalCents) : 0;
        $netCents = $nominalCents - $creditCents;

        if ($netCents > 0) {
            $this->recordMutasiPembayaranCicilan($payment, $dompet, $netCents);
            $this->increaseSaldoDompet($dompet, $netCents);
        }

        if ($metode === CicilanPinjaman::METODE_POTONG_GAJI && $creditCents > 0) {
            $this->akuntansiService->recordPembayaranCicilanPayrollNet($payment, $dompet->akun, (float) $this->decimalFromCents($creditCents), $userId);
        } else {
            $this->akuntansiService->recordPembayaranCicilan($payment, $dompet->akun, $userId);
        }

        return $payment->fresh(['pinjaman', 'jadwal', 'dompet', 'mutasiKas', 'jurnal.details']);
    }

    private function recordMutasiPembayaranCicilan(CicilanPinjaman $payment, DompetKoperasi $dompet, int $nominalCents): MutasiKas
    {
        return MutasiKas::query()->create([
            'idempotency_key' => 'cicilan:pembayaran:mutasi:' . $payment->id,
            'dompet_id' => $dompet->id,
            'tipe' => 'masuk',
            'jumlah' => $this->decimalFromCents($nominalCents),
            'keterangan' => 'Pembayaran cicilan pinjaman periode ' . $payment->periode,
            'referensi_tipe' => CicilanPinjaman::class,
            'referensi_id' => $payment->id,
            'tanggal' => $payment->tanggal_bayar?->toDateString() ?? now($this->businessTimezone())->toDateString(),
        ]);
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

    /**
     * @param  Collection<int, PemakaianPotongGaji>  $entries
     * @return array<int, int>
     */
    private function prepareCreditApplications(LimitPotongGajiAnggota $limit, Collection $entries, int $userId): array
    {
        if ($entries->isEmpty()) {
            return [];
        }

        $grossCents = $entries->sum(fn (PemakaianPotongGaji $entry): int => $this->decimalToCents($entry->nominal));
        if ($grossCents <= 0) {
            return [];
        }

        $credits = KreditPotongGajiAnggota::query()
            ->where('anggota_id', $limit->anggota_id)
            ->whereIn('status', [
                KreditPotongGajiAnggota::STATUS_OPEN,
                KreditPotongGajiAnggota::STATUS_PARTIALLY_APPLIED,
            ])
            ->where('nominal_sisa', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($credits->isEmpty()) {
            return [];
        }

        $remainingGrossCents = $grossCents;
        $totalAppliedCents = 0;

        foreach ($credits as $credit) {
            if ($remainingGrossCents <= 0) {
                break;
            }

            $availableCents = $this->decimalToCents($credit->nominal_sisa);
            $appliedCents = min($availableCents, $remainingGrossCents);

            if ($appliedCents <= 0) {
                continue;
            }

            AlokasiKreditPotongGaji::query()->create([
                'kredit_potong_gaji_anggota_id' => $credit->id,
                'limit_potong_gaji_anggota_id' => $limit->id,
                'nominal_dialokasikan' => $this->decimalFromCents($appliedCents),
                'nominal_diterapkan' => $this->decimalFromCents($appliedCents),
                'status' => AlokasiKreditPotongGaji::STATUS_APPLIED,
                'applied_at' => now($this->businessTimezone()),
                'created_by' => $userId,
                'idempotency_key' => 'kredit-payroll:alokasi:' . $credit->id . ':' . $limit->id,
            ]);

            $usedCents = $this->decimalToCents($credit->nominal_terpakai) + $appliedCents;
            $remainingCreditCents = $availableCents - $appliedCents;

            $credit->update([
                'nominal_terpakai' => $this->decimalFromCents($usedCents),
                'nominal_sisa' => $this->decimalFromCents($remainingCreditCents),
                'status' => $remainingCreditCents === 0
                    ? KreditPotongGajiAnggota::STATUS_APPLIED
                    : KreditPotongGajiAnggota::STATUS_PARTIALLY_APPLIED,
            ]);

            $remainingGrossCents -= $appliedCents;
            $totalAppliedCents += $appliedCents;
        }

        if ($totalAppliedCents <= 0) {
            return [];
        }

        $creditByEntry = [];
        $remainingCreditCents = $totalAppliedCents;

        foreach ($this->sortPayrollEntriesForSettlement($entries) as $entry) {
            if ($remainingCreditCents <= 0) {
                break;
            }

            $entryCents = $this->decimalToCents($entry->nominal);
            $appliedToEntry = min($entryCents, $remainingCreditCents);
            $creditByEntry[$entry->id] = $appliedToEntry;
            $remainingCreditCents -= $appliedToEntry;
        }

        return $creditByEntry;
    }

    private function increaseSaldoDompet(DompetKoperasi $dompet, int $nominalCents): void
    {
        $saldoCents = $this->decimalToCents($dompet->saldo) + $nominalCents;

        $dompet->update([
            'saldo' => $this->decimalFromCents($saldoCents),
        ]);
    }

    private function defaultPayrollBankForUpdate(): DompetKoperasi
    {
        $defaults = DompetKoperasi::query()
            ->with('akun')
            ->where('is_default_penerimaan_payroll', true)
            ->lockForUpdate()
            ->get();

        if ($defaults->count() !== 1) {
            throw ValidationException::withMessages([
                'dompet_penerimaan' => 'Harus ada tepat satu Dompet Bank default penerimaan payroll.',
            ]);
        }

        return $this->assertDompetUsable($defaults->first(), DompetKoperasi::JENIS_BANK, 'Dompet default payroll');
    }

    private function cashDompetForUpdate(int $dompetId): DompetKoperasi
    {
        $dompet = DompetKoperasi::query()
            ->with('akun')
            ->lockForUpdate()
            ->findOrFail($dompetId);

        return $this->assertDompetUsable($dompet, DompetKoperasi::JENIS_KAS, 'Dompet kas pembayaran tunai');
    }

    private function assertDompetUsable(DompetKoperasi $dompet, string $jenis, string $label): DompetKoperasi
    {
        if ($dompet->jenis_dompet !== $jenis) {
            throw ValidationException::withMessages([
                'dompet_id' => "{$label} harus berjenis {$jenis}.",
            ]);
        }

        if (! $dompet->akun || ! $dompet->akun->is_aktif || $dompet->akun->kategori !== 'aset' || $dompet->akun->posisi_saldo !== 'debit') {
            throw ValidationException::withMessages([
                'dompet_id' => "{$label} wajib memiliki COA Aset aktif dengan saldo normal Debit.",
            ]);
        }

        return $dompet;
    }

    private function activePinjamanForUpdate(int $anggotaId): ?Pinjaman
    {
        return Pinjaman::query()
            ->where('anggota_id', $anggotaId)
            ->where('status', Pinjaman::STATUS_AKTIF)
            ->lockForUpdate()
            ->first();
    }

    private function firstDueUnpaidScheduleForUpdate(Pinjaman $pinjaman): JadwalCicilanPinjaman
    {
        $currentPeriod = $this->normalizePeriod()->toDateString();

        $jadwal = $pinjaman->jadwalCicilan()
            ->where('status', JadwalCicilanPinjaman::STATUS_SCHEDULED)
            ->whereDate('periode', '<=', $currentPeriod)
            ->orderBy('angsuran_ke')
            ->lockForUpdate()
            ->first();

        if (! $jadwal) {
            throw ValidationException::withMessages([
                'jadwal' => 'Tidak ada cicilan terjadwal yang sudah jatuh tempo untuk dibayar tunai.',
            ]);
        }

        return $jadwal;
    }

    private function activeLedgerForScheduleExists(JadwalCicilanPinjaman $jadwal): bool
    {
        return PemakaianPotongGaji::query()
            ->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN)
            ->where('source_type', JadwalCicilanPinjaman::class)
            ->where('source_id', $jadwal->id)
            ->whereIn('status', [
                PemakaianPotongGaji::STATUS_RESERVED,
                PemakaianPotongGaji::STATUS_CONSUMED,
                PemakaianPotongGaji::STATUS_SETTLED,
            ])
            ->exists();
    }

    private function assertCashAllowed(Pinjaman $pinjaman): void
    {
        $pinjaman->loadMissing('anggota.karyawan');
        $anggota = $pinjaman->anggota;
        $karyawan = $anggota?->karyawan;

        if ($anggota?->status === Anggota::STATUS_AKTIF && $karyawan?->status_kerja === Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'pinjaman' => 'Anggota aktif tidak boleh membayar cicilan rutin secara tunai.',
            ]);
        }
    }

    private function assertPreviousLimitConfirmed(LimitPotongGajiAnggota $limit): void
    {
        $previous = LimitPotongGajiAnggota::query()
            ->join('periode_potong_gaji', 'periode_potong_gaji.id', '=', 'limit_potong_gaji_anggota.periode_potong_gaji_id')
            ->where('limit_potong_gaji_anggota.anggota_id', $limit->anggota_id)
            ->whereDate('periode_potong_gaji.periode', '<', $limit->periodePotongGaji->periode->toDateString())
            ->orderByDesc('periode_potong_gaji.periode')
            ->select('limit_potong_gaji_anggota.*')
            ->first();

        if ($previous && $previous->status !== LimitPotongGajiAnggota::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'periode' => 'Limit periode sebelumnya untuk Anggota ini belum confirmed.',
            ]);
        }
    }

    private function assertAnggotaEligible(Anggota $anggota): void
    {
        if ($anggota->status !== Anggota::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Limit tidak dapat diaktifkan karena Anggota nonaktif.',
            ]);
        }

        if ($anggota->karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Limit tidak dapat diaktifkan karena Karyawan sudah berhenti.',
            ]);
        }
    }

    private function assertLimitStatus(LimitPotongGajiAnggota $limit, string $expected, string $message): void
    {
        if ($limit->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => $message,
            ]);
        }
    }

    private function assertAuditPayload(int $changedBy, string $alasan): void
    {
        if ($changedBy <= 0 || trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'User dan alasan perubahan limit wajib diisi.',
            ]);
        }
    }

    private function recordHistory(
        LimitPotongGajiAnggota $limit,
        string $before,
        string $after,
        int $changedBy,
        string $alasan
    ): void {
        RiwayatLimitPotongGaji::query()->create([
            'limit_potong_gaji_anggota_id' => $limit->id,
            'nominal_sebelum' => $this->decimalFromCents($this->decimalToCents($before)),
            'nominal_sesudah' => $this->decimalFromCents($this->decimalToCents($after)),
            'alasan' => $alasan,
            'changed_by' => $changedBy,
            'changed_at' => now($this->businessTimezone()),
        ]);
    }

    private function confirmHeaderWhenAllLimitsConfirmed(PeriodePotongGaji $periode, int $userId): void
    {
        $periode = PeriodePotongGaji::query()->lockForUpdate()->findOrFail($periode->id);

        $hasOpenLimit = $periode->limits()
            ->whereNotIn('status', [
                LimitPotongGajiAnggota::STATUS_CONFIRMED,
                LimitPotongGajiAnggota::STATUS_CANCELLED,
            ])
            ->exists();

        if ($hasOpenLimit) {
            return;
        }

        $periode->update([
            'status' => PeriodePotongGaji::STATUS_CONFIRMED,
            'updated_by' => $userId,
        ]);
    }

    private function limitNominalCents(LimitPotongGajiAnggota $limit): int
    {
        return $this->decimalToCents($limit->limit_nominal);
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
