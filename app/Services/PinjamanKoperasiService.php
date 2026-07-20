<?php

namespace App\Services;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\JadwalCicilanPinjaman;
use App\Models\Karyawan;
use App\Models\LimitPotongGajiAnggota;
use App\Models\MutasiKas;
use App\Models\PeriodePotongGaji;
use App\Models\Pinjaman;
use App\Models\SiklusKeanggotaan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PinjamanKoperasiService
{
    public const MAX_PINJAMAN = 5000000;

    public function __construct(
        private readonly AkunResolver $akunResolver,
        private readonly AkuntansiService $akuntansiService
    ) {
    }

    /**
     * Compatibility path for existing services/tests/seeder: create an already-approved
     * Pinjaman and disburse it atomically in one transaction.
     */
    public function create(array $data, ?int $userId = null): Pinjaman
    {
        try {
            return DB::transaction(function () use ($data, $userId): Pinjaman {
                $anggota = $this->lockedAnggota((int) $data['anggota_id']);
                $this->assertEligibleAnggota($anggota);
                $this->assertNoOpenLoan($anggota);
                $siklus = $this->activeCycleForAnggota($anggota, $userId);

                $jumlahRupiah = $this->rupiahInt($data['jumlah_pinjaman']);
                $tenor = (int) $data['tenor_bulan'];
                $this->assertJumlahValid($jumlahRupiah, $anggota);
                $this->assertTenorValid($tenor);
                $this->assertJumlahCukupUntukTenor($jumlahRupiah, $tenor);

                $dompet = DompetKoperasi::query()
                    ->with('akun')
                    ->lockForUpdate()
                    ->findOrFail((int) $data['dompet_id']);
                $akunDompet = $this->assertUsableDompet($dompet);
                $this->assertSaldoCukup($dompet, $jumlahRupiah);

                $tanggalPinjaman = $this->normalizeTanggal($data['tanggal_pinjaman'] ?? $data['tanggal_pengajuan'] ?? now(config('app.timezone')));
                $kodePinjaman = $this->nextKodePinjaman($tanggalPinjaman);
                $jumlahDecimal = $this->rupiahDecimal($jumlahRupiah);
                $now = now();

                $pinjaman = Pinjaman::query()->create([
                    'kode_pinjaman' => $kodePinjaman,
                    'anggota_id' => $anggota->id,
                    'siklus_keanggotaan_id' => $siklus->id,
                    'karyawan_id' => $anggota->karyawan_id,
                    'dompet_id' => $dompet->id,
                    'jumlah_pinjaman' => $jumlahDecimal,
                    'plafon_pinjaman_snapshot' => $this->rupiahDecimal($this->rupiahInt($anggota->plafon_pinjaman)),
                    'bunga_persen' => '0.00',
                    'tenor_bulan' => $tenor,
                    'sisa_pinjaman' => $jumlahDecimal,
                    'status' => Pinjaman::STATUS_AKTIF,
                    'tanggal_pengajuan' => $tanggalPinjaman->toDateString(),
                    'tanggal_pinjaman' => $tanggalPinjaman->toDateString(),
                    'keterangan' => $data['keterangan'] ?? null,
                    'created_by' => $userId,
                    'submitted_by' => $userId,
                    'submitted_at' => $now,
                    'approved_by' => $userId,
                    'approved_at' => $now,
                    'disbursed_by' => $userId,
                    'disbursed_at' => $now,
                ]);

                $this->createJadwal($pinjaman, $jumlahRupiah, $tenor, $tanggalPinjaman);
                $this->reserveFirstInstallmentIfActiveLimit($pinjaman, $userId);
                $this->recordMutasiPencairan($pinjaman, $dompet, $jumlahRupiah);
                $this->decreaseSaldoDompet($dompet, $jumlahRupiah);
                $this->akuntansiService->recordPencairanPinjaman($pinjaman, $akunDompet, $userId);

                return $pinjaman->fresh(['anggota.karyawan', 'dompet.akun', 'jadwalCicilan', 'mutasiKas', 'jurnal.details']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Anggota ini sudah mempunyai proses Pinjaman terbuka atau Pinjaman aktif.',
            ]);
        }
    }

    public function createDraft(array $data, ?int $userId = null): Pinjaman
    {
        try {
            return DB::transaction(function () use ($data, $userId): Pinjaman {
                $anggota = $this->lockedAnggota((int) $data['anggota_id']);
                $this->assertEligibleAnggota($anggota);
                $this->assertNoOpenLoan($anggota);
                $siklus = $this->activeCycleForAnggota($anggota, $userId);

                $jumlahRupiah = $this->rupiahInt($data['jumlah_pinjaman']);
                $tenor = (int) $data['tenor_bulan'];
                $this->assertJumlahValid($jumlahRupiah, $anggota);
                $this->assertTenorValid($tenor);
                $this->assertJumlahCukupUntukTenor($jumlahRupiah, $tenor);

                $tanggalPengajuan = $this->normalizeTanggal($data['tanggal_pengajuan'] ?? $data['tanggal_pinjaman'] ?? now(config('app.timezone')));
                $jumlahDecimal = $this->rupiahDecimal($jumlahRupiah);

                $pinjaman = Pinjaman::query()->create([
                    'kode_pinjaman' => $this->nextKodePinjaman($tanggalPengajuan),
                    'anggota_id' => $anggota->id,
                    'siklus_keanggotaan_id' => $siklus->id,
                    'karyawan_id' => $anggota->karyawan_id,
                    'dompet_id' => null,
                    'jumlah_pinjaman' => $jumlahDecimal,
                    'plafon_pinjaman_snapshot' => $this->rupiahDecimal($this->rupiahInt($anggota->plafon_pinjaman)),
                    'bunga_persen' => '0.00',
                    'tenor_bulan' => $tenor,
                    'sisa_pinjaman' => $jumlahDecimal,
                    'status' => Pinjaman::STATUS_DRAFT,
                    'tanggal_pengajuan' => $tanggalPengajuan->toDateString(),
                    'tanggal_pinjaman' => $tanggalPengajuan->toDateString(),
                    'keterangan' => $data['keterangan'] ?? null,
                    'created_by' => $userId,
                ]);

                return $pinjaman->fresh(['anggota.karyawan']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Anggota ini sudah mempunyai proses Pinjaman terbuka atau Pinjaman aktif.',
            ]);
        }
    }

    public function updateDraft(Pinjaman $pinjaman, array $data, ?int $userId = null): Pinjaman
    {
        try {
            return DB::transaction(function () use ($pinjaman, $data, $userId): Pinjaman {
                $locked = Pinjaman::query()->lockForUpdate()->findOrFail($pinjaman->id);

                if ($locked->status !== Pinjaman::STATUS_DRAFT) {
                    throw ValidationException::withMessages([
                        'pinjaman' => 'Hanya draft Pinjaman yang dapat diedit.',
                    ]);
                }

                $anggota = $this->lockedAnggota((int) $data['anggota_id']);
                $this->assertEligibleAnggota($anggota);
                $this->assertNoOpenLoan($anggota, $locked->id);
                $siklus = $this->activeCycleForAnggota($anggota, $userId);

                $jumlahRupiah = $this->rupiahInt($data['jumlah_pinjaman']);
                $tenor = (int) $data['tenor_bulan'];
                $this->assertJumlahValid($jumlahRupiah, $anggota);
                $this->assertTenorValid($tenor);
                $this->assertJumlahCukupUntukTenor($jumlahRupiah, $tenor);

                $tanggalPengajuan = $this->normalizeTanggal($data['tanggal_pengajuan'] ?? $locked->tanggal_pengajuan ?? $locked->tanggal_pinjaman);
                $jumlahDecimal = $this->rupiahDecimal($jumlahRupiah);

                $locked->update([
                    'anggota_id' => $anggota->id,
                    'siklus_keanggotaan_id' => $siklus->id,
                    'karyawan_id' => $anggota->karyawan_id,
                    'jumlah_pinjaman' => $jumlahDecimal,
                    'plafon_pinjaman_snapshot' => $this->rupiahDecimal($this->rupiahInt($anggota->plafon_pinjaman)),
                    'bunga_persen' => '0.00',
                    'tenor_bulan' => $tenor,
                    'sisa_pinjaman' => $jumlahDecimal,
                    'tanggal_pengajuan' => $tanggalPengajuan->toDateString(),
                    'tanggal_pinjaman' => $tanggalPengajuan->toDateString(),
                    'keterangan' => $data['keterangan'] ?? null,
                ]);

                return $locked->fresh(['anggota.karyawan']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Anggota ini sudah mempunyai proses Pinjaman terbuka atau Pinjaman aktif.',
            ]);
        }
    }

    public function submit(Pinjaman $pinjaman, ?int $userId = null): Pinjaman
    {
        return DB::transaction(function () use ($pinjaman, $userId): Pinjaman {
            $locked = Pinjaman::query()->lockForUpdate()->findOrFail($pinjaman->id);
            $this->assertStatus($locked, [Pinjaman::STATUS_DRAFT], 'Hanya draft Pinjaman yang dapat diajukan.');
            $this->assertNoPostingBeforeDisbursement($locked);

            $anggota = $this->lockedAnggota((int) $locked->anggota_id);
            $this->assertEligibleAnggota($anggota);
            $this->assertPinjamanOnCurrentCycle($locked, $anggota);
            $this->assertNoOpenLoan($anggota, $locked->id);
            $this->assertJumlahValid($this->rupiahInt($locked->jumlah_pinjaman), $anggota);
            $this->assertTenorValid((int) $locked->tenor_bulan);

            $locked->update([
                'status' => Pinjaman::STATUS_DIAJUKAN,
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);

            return $locked->fresh(['anggota.karyawan']);
        });
    }

    public function approve(Pinjaman $pinjaman, ?int $userId = null): Pinjaman
    {
        return DB::transaction(function () use ($pinjaman, $userId): Pinjaman {
            $locked = Pinjaman::query()->lockForUpdate()->findOrFail($pinjaman->id);
            $this->assertStatus($locked, [Pinjaman::STATUS_DIAJUKAN], 'Hanya Pinjaman berstatus diajukan yang dapat disetujui.');
            $this->assertNoPostingBeforeDisbursement($locked);

            $anggota = $this->lockedAnggota((int) $locked->anggota_id);
            $this->assertEligibleAnggota($anggota);
            $this->assertPinjamanOnCurrentCycle($locked, $anggota);
            $this->assertNoOpenLoan($anggota, $locked->id);

            $jumlahRupiah = $this->rupiahInt($locked->jumlah_pinjaman);
            $this->assertJumlahValid($jumlahRupiah, $anggota);
            $this->assertTenorValid((int) $locked->tenor_bulan);

            $locked->update([
                'status' => Pinjaman::STATUS_DISETUJUI,
                'plafon_pinjaman_snapshot' => $this->rupiahDecimal($this->rupiahInt($anggota->plafon_pinjaman)),
                'bunga_persen' => '0.00',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $locked->fresh(['anggota.karyawan']);
        });
    }

    public function reject(Pinjaman $pinjaman, string $reason, ?int $userId = null): Pinjaman
    {
        return DB::transaction(function () use ($pinjaman, $reason, $userId): Pinjaman {
            $locked = Pinjaman::query()->lockForUpdate()->findOrFail($pinjaman->id);
            $this->assertStatus($locked, [Pinjaman::STATUS_DIAJUKAN], 'Hanya Pinjaman berstatus diajukan yang dapat ditolak.');
            $this->assertNoPostingBeforeDisbursement($locked);

            $locked->update([
                'status' => Pinjaman::STATUS_DITOLAK,
                'rejected_by' => $userId,
                'rejected_at' => now(),
                'rejection_reason' => trim($reason),
            ]);

            return $locked->fresh(['anggota.karyawan']);
        });
    }

    public function cancel(Pinjaman $pinjaman, string $reason, ?int $userId = null): Pinjaman
    {
        return DB::transaction(function () use ($pinjaman, $reason, $userId): Pinjaman {
            $locked = Pinjaman::query()->lockForUpdate()->findOrFail($pinjaman->id);
            $this->assertStatus(
                $locked,
                [Pinjaman::STATUS_DRAFT, Pinjaman::STATUS_DIAJUKAN, Pinjaman::STATUS_DISETUJUI],
                'Pinjaman hanya dapat dibatalkan sebelum dicairkan.'
            );
            $this->assertNoPostingBeforeDisbursement($locked);

            $locked->update([
                'status' => Pinjaman::STATUS_DIBATALKAN,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancellation_reason' => trim($reason),
            ]);

            return $locked->fresh(['anggota.karyawan']);
        });
    }

    public function disburse(Pinjaman $pinjaman, array $data, ?int $userId = null): Pinjaman
    {
        try {
            return DB::transaction(function () use ($pinjaman, $data, $userId): Pinjaman {
                $locked = Pinjaman::query()->lockForUpdate()->findOrFail($pinjaman->id);

                if ($locked->status === Pinjaman::STATUS_AKTIF && $locked->disbursed_at) {
                    return $locked->fresh(['anggota.karyawan', 'dompet.akun', 'jadwalCicilan', 'mutasiKas', 'jurnal.details']);
                }

                $this->assertStatus($locked, [Pinjaman::STATUS_DISETUJUI], 'Pinjaman hanya dapat dicairkan setelah disetujui.');
                $this->assertNoPostingBeforeDisbursement($locked);

                $anggota = $this->lockedAnggota((int) $locked->anggota_id);
                $this->assertEligibleAnggota($anggota);
                $this->assertPinjamanOnCurrentCycle($locked, $anggota);
                $this->assertNoOpenLoan($anggota, $locked->id);

                $jumlahRupiah = $this->rupiahInt($locked->jumlah_pinjaman);
                $tenor = (int) $locked->tenor_bulan;
                $this->assertJumlahValidAgainstSnapshot($jumlahRupiah, $locked);
                $this->assertTenorValid($tenor);
                $this->assertJumlahCukupUntukTenor($jumlahRupiah, $tenor);

                $dompet = DompetKoperasi::query()
                    ->with('akun')
                    ->lockForUpdate()
                    ->findOrFail((int) $data['dompet_id']);
                $akunDompet = $this->assertUsableDompet($dompet);
                $this->assertSaldoCukup($dompet, $jumlahRupiah);

                $tanggalPencairan = $this->normalizeTanggal($data['tanggal_pencairan'] ?? $data['tanggal_pinjaman'] ?? now(config('app.timezone')));

                $locked->update([
                    'dompet_id' => $dompet->id,
                    'tanggal_pinjaman' => $tanggalPencairan->toDateString(),
                    'sisa_pinjaman' => $this->rupiahDecimal($jumlahRupiah),
                    'bunga_persen' => '0.00',
                    'status' => Pinjaman::STATUS_AKTIF,
                    'disbursed_by' => $userId,
                    'disbursed_at' => now(),
                ]);

                $this->createJadwal($locked, $jumlahRupiah, $tenor, $tanggalPencairan);
                $this->reserveFirstInstallmentIfActiveLimit($locked, $userId);
                $mutasi = $this->recordMutasiPencairan($locked, $dompet, $jumlahRupiah);

                if ($mutasi->wasRecentlyCreated) {
                    $this->decreaseSaldoDompet($dompet, $jumlahRupiah);
                }

                $this->akuntansiService->recordPencairanPinjaman($locked, $akunDompet, $userId);

                return $locked->fresh(['anggota.karyawan', 'dompet.akun', 'jadwalCicilan', 'mutasiKas', 'jurnal.details']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'pinjaman' => 'Pencairan sudah diproses atau terdapat proses Pinjaman lain untuk Anggota ini.',
            ]);
        }
    }

    /**
     * @return array<int, array{angsuran_ke:int, periode:string, nominal_pokok:string}>
     */
    public function buildJadwalPreview(int|string $jumlahPinjaman, int $tenor, CarbonInterface|string $tanggalPinjaman): array
    {
        $jumlahRupiah = $this->rupiahInt($jumlahPinjaman);
        $this->assertTenorValid($tenor);
        $this->assertJumlahCukupUntukTenor($jumlahRupiah, $tenor);
        $tanggal = $this->normalizeTanggal($tanggalPinjaman);

        $base = intdiv($jumlahRupiah, $tenor);
        $remainder = $jumlahRupiah % $tenor;
        $periode = $tanggal->startOfMonth()->addMonth();
        $rows = [];

        for ($i = 1; $i <= $tenor; $i++) {
            $nominal = $base + ($i === $tenor ? $remainder : 0);
            $rows[] = [
                'angsuran_ke' => $i,
                'periode' => $periode->copy()->addMonths($i - 1)->toDateString(),
                'nominal_pokok' => $this->rupiahDecimal($nominal),
            ];
        }

        return $rows;
    }

    public function pencairanIdempotencyKey(Pinjaman $pinjaman, string $jenis): string
    {
        return "pinjaman:pencairan:{$jenis}:{$pinjaman->id}";
    }

    private function createJadwal(Pinjaman $pinjaman, int $jumlahRupiah, int $tenor, CarbonImmutable $tanggalPinjaman): void
    {
        if ($pinjaman->jadwalCicilan()->exists()) {
            throw ValidationException::withMessages([
                'pinjaman' => 'Jadwal cicilan Pinjaman ini sudah pernah dibuat.',
            ]);
        }

        $rows = collect($this->buildJadwalPreview($jumlahRupiah, $tenor, $tanggalPinjaman))
            ->map(fn (array $row) => $row + [
                'pinjaman_id' => $pinjaman->id,
                'nominal_offset' => '0.00',
                'nominal_sisa' => $row['nominal_pokok'],
                'status' => JadwalCicilanPinjaman::STATUS_SCHEDULED,
                'metode_penyelesaian' => null,
                'paid_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        JadwalCicilanPinjaman::query()->insert($rows);
    }

    private function recordMutasiPencairan(Pinjaman $pinjaman, DompetKoperasi $dompet, int $jumlahRupiah): MutasiKas
    {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => $this->pencairanIdempotencyKey($pinjaman, 'mutasi')],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'keluar',
                'jumlah' => $this->rupiahDecimal($jumlahRupiah),
                'keterangan' => 'Pencairan pinjaman ' . $pinjaman->kode_pinjaman,
                'referensi_tipe' => Pinjaman::class,
                'referensi_id' => $pinjaman->id,
                'tanggal' => $pinjaman->tanggal_pinjaman,
            ]
        );
    }

    private function decreaseSaldoDompet(DompetKoperasi $dompet, int $jumlahRupiah): void
    {
        $saldoBaru = $this->rupiahInt($dompet->saldo) - $jumlahRupiah;

        $dompet->update([
            'saldo' => $this->rupiahDecimal($saldoBaru),
        ]);
    }

    private function lockedAnggota(int $anggotaId): Anggota
    {
        return Anggota::query()
            ->with('karyawan')
            ->lockForUpdate()
            ->findOrFail($anggotaId);
    }

    private function activeCycleForAnggota(Anggota $anggota, ?int $userId = null): SiklusKeanggotaan
    {
        $cycle = SiklusKeanggotaan::query()
            ->where('anggota_id', $anggota->id)
            ->where('status', SiklusKeanggotaan::STATUS_ACTIVE)
            ->lockForUpdate()
            ->first();

        if ($cycle) {
            return $cycle;
        }

        $cycle = app(KeanggotaanLifecycleService::class)->ensureActiveCycle($anggota, $userId);

        return SiklusKeanggotaan::query()
            ->whereKey($cycle->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertPinjamanOnCurrentCycle(Pinjaman $pinjaman, Anggota $anggota): void
    {
        $cycle = $this->activeCycleForAnggota($anggota);

        if ((int) $pinjaman->siklus_keanggotaan_id !== (int) $cycle->id) {
            throw ValidationException::withMessages([
                'siklus' => 'Pinjaman tidak lagi merujuk siklus keanggotaan aktif yang sama.',
            ]);
        }
    }

    private function reserveFirstInstallmentIfActiveLimit(Pinjaman $pinjaman, ?int $userId = null): void
    {
        $firstSchedule = $pinjaman->jadwalCicilan()
            ->orderBy('angsuran_ke')
            ->lockForUpdate()
            ->first();

        if (! $firstSchedule) {
            return;
        }

        $periode = PeriodePotongGaji::query()
            ->whereDate('periode', $firstSchedule->periode->toDateString())
            ->lockForUpdate()
            ->first();

        if (! $periode) {
            return;
        }

        $limit = LimitPotongGajiAnggota::query()
            ->where('periode_potong_gaji_id', $periode->id)
            ->where('anggota_id', $pinjaman->anggota_id)
            ->lockForUpdate()
            ->first();

        if (! $limit || $limit->status === LimitPotongGajiAnggota::STATUS_DRAFT) {
            return;
        }

        if ($limit->status === LimitPotongGajiAnggota::STATUS_ACTIVE) {
            try {
                app(PotongGajiBulananService::class)->reserveDueInstallmentsForActiveLimit($limit, (int) $userId);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    'limit_potong_gaji' => 'Pencairan dibatalkan karena limit payroll Cicilan pertama tidak mencukupi. Finance perlu menyesuaikan limit terlebih dahulu.',
                ]);
            }

            return;
        }

        throw ValidationException::withMessages([
            'limit_potong_gaji' => 'Pencairan dibatalkan karena periode payroll Cicilan pertama sudah terminal dan tidak dapat menerima reservasi Cicilan baru.',
        ]);
    }

    private function assertEligibleAnggota(Anggota $anggota): void
    {
        if ($anggota->status !== Anggota::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Pinjaman hanya dapat dibuat untuk Anggota aktif.',
            ]);
        }

        if ($anggota->karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Pinjaman hanya dapat dibuat untuk Karyawan aktif.',
            ]);
        }
    }

    private function assertNoOpenLoan(Anggota $anggota, ?int $exceptPinjamanId = null): void
    {
        $query = Pinjaman::query()
            ->where('anggota_id', $anggota->id)
            ->whereIn('status', Pinjaman::openStatuses());

        if ($exceptPinjamanId) {
            $query->whereKeyNot($exceptPinjamanId);
        }

        if ($query->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Anggota ini masih mempunyai proses Pinjaman terbuka atau Pinjaman aktif.',
            ]);
        }
    }

    private function assertJumlahValid(int $jumlahRupiah, Anggota $anggota): void
    {
        if ($jumlahRupiah <= 0) {
            throw ValidationException::withMessages([
                'jumlah_pinjaman' => 'Jumlah pinjaman harus lebih besar dari nol.',
            ]);
        }

        if ($jumlahRupiah > self::MAX_PINJAMAN) {
            throw ValidationException::withMessages([
                'jumlah_pinjaman' => 'Jumlah pinjaman maksimal Rp5.000.000.',
            ]);
        }

        $plafon = $this->rupiahInt($anggota->plafon_pinjaman);

        if ($jumlahRupiah > $plafon) {
            throw ValidationException::withMessages([
                'jumlah_pinjaman' => 'Jumlah pinjaman melebihi plafon Anggota.',
            ]);
        }
    }

    private function assertJumlahValidAgainstSnapshot(int $jumlahRupiah, Pinjaman $pinjaman): void
    {
        if ($jumlahRupiah <= 0) {
            throw ValidationException::withMessages([
                'jumlah_pinjaman' => 'Jumlah pinjaman harus lebih besar dari nol.',
            ]);
        }

        if ($jumlahRupiah > self::MAX_PINJAMAN) {
            throw ValidationException::withMessages([
                'jumlah_pinjaman' => 'Jumlah pinjaman maksimal Rp5.000.000.',
            ]);
        }

        if ($jumlahRupiah > $this->rupiahInt($pinjaman->plafon_pinjaman_snapshot)) {
            throw ValidationException::withMessages([
                'jumlah_pinjaman' => 'Jumlah pinjaman melebihi plafon snapshot saat persetujuan.',
            ]);
        }
    }

    private function assertTenorValid(int $tenor): void
    {
        if ($tenor < 1 || $tenor > 12) {
            throw ValidationException::withMessages([
                'tenor_bulan' => 'Tenor pinjaman minimal 1 bulan dan maksimal 12 bulan.',
            ]);
        }
    }

    private function assertJumlahCukupUntukTenor(int $jumlahRupiah, int $tenor): void
    {
        if ($jumlahRupiah < $tenor) {
            throw ValidationException::withMessages([
                'jumlah_pinjaman' => 'Jumlah pinjaman terlalu kecil untuk tenor yang dipilih.',
            ]);
        }
    }

    private function assertUsableDompet(DompetKoperasi $dompet): Akun
    {
        $akun = $dompet->akun;

        if (! $akun) {
            throw ValidationException::withMessages([
                'dompet_id' => 'Dompet sumber pencairan belum memiliki mapping COA.',
            ]);
        }

        if (! $akun->is_aktif || $akun->kategori !== 'aset' || $akun->posisi_saldo !== 'debit') {
            throw ValidationException::withMessages([
                'dompet_id' => 'Akun Dompet harus aktif, kategori Aset, dan saldo normal Debit.',
            ]);
        }

        $this->akunResolver->posting('pinjaman.piutang');

        return $akun;
    }

    private function assertSaldoCukup(DompetKoperasi $dompet, int $jumlahRupiah): void
    {
        if ($this->rupiahInt($dompet->saldo) < $jumlahRupiah) {
            throw ValidationException::withMessages([
                'dompet_id' => 'Saldo Dompet tidak mencukupi untuk pencairan Pinjaman.',
            ]);
        }
    }

    /**
     * @param array<int, string> $allowed
     */
    private function assertStatus(Pinjaman $pinjaman, array $allowed, string $message): void
    {
        if (! in_array($pinjaman->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'pinjaman' => $message,
            ]);
        }
    }

    private function assertNoPostingBeforeDisbursement(Pinjaman $pinjaman): void
    {
        if (
            $pinjaman->jadwalCicilan()->exists()
            || $pinjaman->mutasiKas()->exists()
            || $pinjaman->jurnal()->exists()
        ) {
            throw ValidationException::withMessages([
                'pinjaman' => 'Pinjaman yang belum dicairkan tidak boleh memiliki Jadwal, Mutasi Kas, atau Jurnal.',
            ]);
        }
    }

    private function nextKodePinjaman(CarbonImmutable $tanggalPinjaman): string
    {
        $periode = $tanggalPinjaman->format('Ym');
        $jenis = 'pinjaman';

        try {
            DB::table('nomor_urut_transaksi')->insert([
                'jenis' => $jenis,
                'periode' => $periode,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
            //
        }

        $counter = DB::table('nomor_urut_transaksi')
            ->where('jenis', $jenis)
            ->where('periode', $periode)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            throw new RuntimeException('Counter nomor Pinjaman tidak dapat dibuat.');
        }

        $next = ((int) $counter->last_number) + 1;

        DB::table('nomor_urut_transaksi')
            ->where('id', $counter->id)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        return sprintf('PJM-%s-%06d', $periode, $next);
    }

    private function normalizeTanggal(CarbonInterface|string $tanggal): CarbonImmutable
    {
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');

        if ($tanggal instanceof CarbonInterface) {
            return CarbonImmutable::instance($tanggal)->setTimezone($timezone)->startOfDay();
        }

        return CarbonImmutable::parse((string) $tanggal, $timezone)->setTimezone($timezone)->startOfDay();
    }

    private function rupiahInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $normalized = trim((string) $value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = substr(str_pad(preg_replace('/\D/', '', $fraction) ?? '', 2, '0'), 0, 2);

        if ((int) $fraction !== 0) {
            throw ValidationException::withMessages([
                'jumlah_pinjaman' => 'Nominal pinjaman harus berupa Rupiah bulat.',
            ]);
        }

        $rupiah = (int) $whole;

        return $negative ? -1 * $rupiah : $rupiah;
    }

    private function rupiahDecimal(int $value): string
    {
        return $value . '.00';
    }
}
