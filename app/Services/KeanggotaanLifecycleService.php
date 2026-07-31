<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\Akun;
use App\Models\DompetKoperasi;
use App\Models\JadwalCicilanPinjaman;
use App\Models\JadwalSimpananWajib;
use App\Models\JenisSimpanan;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\KreditPotongGajiAnggota;
use App\Models\MutasiKas;
use App\Models\Pembayaran;
use App\Models\PemakaianPotongGaji;
use App\Models\Penjualan;
use App\Models\PenyelesaianKeanggotaan;
use App\Models\PenyelesaianKeanggotaanDetail;
use App\Models\Pinjaman;
use App\Models\ReversalTransaksi;
use App\Models\SaldoSimpananManasuka;
use App\Models\SiklusKeanggotaan;
use App\Models\Simpanan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class KeanggotaanLifecycleService
{
    public function __construct(private readonly AkuntansiService $akuntansiService)
    {
    }

    public function ensureActiveCycle(Anggota $anggota, ?int $userId = null, CarbonInterface|string|null $tanggalMulai = null): SiklusKeanggotaan
    {
        return DB::transaction(function () use ($anggota, $userId, $tanggalMulai): SiklusKeanggotaan {
            $locked = Anggota::query()->lockForUpdate()->findOrFail($anggota->id);
            $active = SiklusKeanggotaan::query()
                ->where('anggota_id', $locked->id)
                ->where('status', SiklusKeanggotaan::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($active) {
                return $active;
            }

            $next = ((int) SiklusKeanggotaan::query()->where('anggota_id', $locked->id)->max('siklus_ke')) + 1;

            return SiklusKeanggotaan::query()->create([
                'anggota_id' => $locked->id,
                'siklus_ke' => $next,
                'tanggal_mulai' => $this->normalizeDate($tanggalMulai ?? $locked->tanggal_bergabung ?? now())->toDateString(),
                'status' => SiklusKeanggotaan::STATUS_ACTIVE,
                'alasan_selesai' => null,
                'created_by' => $userId,
            ]);
        });
    }

    public function closeActiveCycleForExit(Anggota $anggota, CarbonInterface|string $tanggalKeluar, ?int $userId, string $alasan): SiklusKeanggotaan
    {
        return DB::transaction(function () use ($anggota, $tanggalKeluar, $userId, $alasan): SiklusKeanggotaan {
            $locked = Anggota::query()->lockForUpdate()->findOrFail($anggota->id);
            $cycle = SiklusKeanggotaan::query()
                ->where('anggota_id', $locked->id)
                ->where('status', SiklusKeanggotaan::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $cycle) {
                $cycle = SiklusKeanggotaan::query()
                    ->where('anggota_id', $locked->id)
                    ->where('status', SiklusKeanggotaan::STATUS_CLOSED)
                    ->orderByDesc('siklus_ke')
                    ->lockForUpdate()
                    ->first();

                if (! $cycle) {
                    $cycle = $this->ensureActiveCycle($locked, $userId, $locked->tanggal_bergabung ?? $tanggalKeluar);
                    $cycle = SiklusKeanggotaan::query()->lockForUpdate()->findOrFail($cycle->id);
                }
            }

            if ($cycle->status === SiklusKeanggotaan::STATUS_CLOSED) {
                return $cycle;
            }

            $cycle->update([
                'status' => SiklusKeanggotaan::STATUS_CLOSED,
                'tanggal_selesai' => $this->normalizeDate($tanggalKeluar)->toDateString(),
                'alasan_selesai' => trim($alasan),
                'closed_by' => $userId,
            ]);

            return $cycle->fresh();
        });
    }

    public function createPenyelesaianForExit(
        Anggota $anggota,
        SiklusKeanggotaan $siklus,
        CarbonInterface|string $tanggalKeluar,
        string $alasan,
        ?int $userId
    ): PenyelesaianKeanggotaan {
        return DB::transaction(function () use ($anggota, $siklus, $tanggalKeluar, $alasan, $userId): PenyelesaianKeanggotaan {
            $lockedAnggota = Anggota::query()->lockForUpdate()->findOrFail($anggota->id);
            $lockedSiklus = SiklusKeanggotaan::query()->lockForUpdate()->findOrFail($siklus->id);

            $existing = PenyelesaianKeanggotaan::query()
                ->where('siklus_keanggotaan_id', $lockedSiklus->id)
                ->whereNotIn('status', [
                    PenyelesaianKeanggotaan::STATUS_CANCELLED,
                    PenyelesaianKeanggotaan::STATUS_DEACTIVATION_CANCELLED,
                ])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->cancelUnpaidWajibForExit($existing, $userId);
                $this->freezeManasukaSaldo($existing);

                return $this->refreshSnapshot($existing);
            }

            $penyelesaian = PenyelesaianKeanggotaan::query()->create([
                'kode_penyelesaian' => $this->nextCode('penyelesaian_keanggotaan', 'PKA'),
                'anggota_id' => $lockedAnggota->id,
                'siklus_keanggotaan_id' => $lockedSiklus->id,
                'tanggal_keluar' => $this->normalizeDate($tanggalKeluar)->toDateString(),
                'simpanan_pokok_snapshot' => '0.00',
                'kredit_refund_snapshot' => '0.00',
                'total_hak_anggota' => '0.00',
                'total_kewajiban_awal' => '0.00',
                'total_offset' => '0.00',
                'total_refund' => '0.00',
                'sisa_kewajiban' => '0.00',
                'status' => PenyelesaianKeanggotaan::STATUS_PENDING_REVIEW,
                'alasan' => trim($alasan),
                'created_by' => $userId,
                'idempotency_key' => 'penyelesaian-keanggotaan:siklus:' . $lockedSiklus->id,
            ]);

            $this->cancelUnpaidWajibForExit($penyelesaian, $userId);
            $this->freezeManasukaSaldo($penyelesaian);

            return $this->refreshSnapshot($penyelesaian);
        });
    }

    public function refreshSnapshot(PenyelesaianKeanggotaan $penyelesaian): PenyelesaianKeanggotaan
    {
        return DB::transaction(function () use ($penyelesaian): PenyelesaianKeanggotaan {
            $locked = PenyelesaianKeanggotaan::query()
                ->with(['anggota', 'siklus'])
                ->lockForUpdate()
                ->findOrFail($penyelesaian->id);

            if (in_array($locked->status, [
                PenyelesaianKeanggotaan::STATUS_COMPLETED,
                PenyelesaianKeanggotaan::STATUS_DEACTIVATION_CANCELLED,
            ], true)) {
                return $locked->fresh(['details.source']);
            }

            $this->cancelUnpaidWajibForExit($locked, $locked->processed_by ?? $locked->created_by);
            $this->freezeManasukaSaldo($locked);

            $rights = $this->rightSources($locked);
            foreach ($rights as $index => $right) {
                $this->upsertRightDetail($locked, $right, $index + 1);
            }

            $obligations = $this->obligationSources($locked->anggota);

            foreach ($obligations as $index => $obligation) {
                $this->upsertObligationDetail($locked, $obligation, $index + 1);
            }

            $currentKeys = $obligations
                ->map(fn (array $item): string => $item['source_type'] . '#' . $item['source_id'])
                ->all();
            $this->markStaleDetailsAsSettledCash($locked, $currentKeys);

            $totals = $this->detailTotals($locked);
            $totalKewajiban = $totals['awal'];
            $totalOffset = $totals['offset'];
            $sisa = $totals['sisa'];
            $refund = $sisa === 0
                ? max($totals['refund'], max(0, $totals['hak'] - $totalOffset))
                : $totals['refund'];

            $locked->update([
                'simpanan_pokok_snapshot' => $this->decimalFromCents($totals['hak_pokok']),
                'kredit_refund_snapshot' => $this->decimalFromCents($totals['hak_kredit']),
                'total_hak_anggota' => $this->decimalFromCents($totals['hak']),
                'total_kewajiban_awal' => $this->decimalFromCents($totalKewajiban),
                'total_offset' => $this->decimalFromCents($totalOffset),
                'sisa_kewajiban' => $this->decimalFromCents($sisa),
                'total_refund' => $this->decimalFromCents($refund),
            ]);

            return $locked->fresh(['details.source', 'anggota.karyawan', 'siklus']);
        });
    }

    public function processOffset(PenyelesaianKeanggotaan $penyelesaian, ?int $userId): PenyelesaianKeanggotaan
    {
        return DB::transaction(function () use ($penyelesaian, $userId): PenyelesaianKeanggotaan {
            $locked = PenyelesaianKeanggotaan::query()
                ->with(['anggota', 'siklus', 'details.source'])
                ->lockForUpdate()
                ->findOrFail($penyelesaian->id);

            if ($locked->status === PenyelesaianKeanggotaan::STATUS_COMPLETED) {
                throw ValidationException::withMessages(['penyelesaian' => 'Penyelesaian yang sudah completed tidak dapat diproses ulang.']);
            }

            if ($locked->status === PenyelesaianKeanggotaan::STATUS_DEACTIVATION_CANCELLED) {
                throw ValidationException::withMessages(['penyelesaian' => 'Penonaktifan sudah dibatalkan; settlement ini hanya menjadi histori audit.']);
            }

            if ($this->decimalToCents($locked->total_offset) > 0) {
                return $locked->fresh(['details.source']);
            }

            $this->reverseUnpaidSimpananPokok($locked, $userId);
            $this->cancelUnpaidWajibForExit($locked, $userId);
            $locked = $this->refreshSnapshot($locked);
            $totalOffset = 0;
            $available = $this->rightRemainingCents($locked);

            foreach ($locked->details()->whereIn('status', [
                PenyelesaianKeanggotaanDetail::STATUS_OPEN,
                PenyelesaianKeanggotaanDetail::STATUS_PARTIAL,
            ])
                ->where('tipe_detail', PenyelesaianKeanggotaanDetail::TIPE_KEWAJIBAN)
                ->lockForUpdate()
                ->get() as $detail) {
                if ($available <= 0) {
                    break;
                }

                $kewajiban = $this->decimalToCents($detail->nominal_sisa);
                $offset = min($available, $kewajiban);
                if ($offset <= 0) {
                    continue;
                }

                $remaining = $kewajiban - $offset;
                $newOffset = $this->decimalToCents($detail->nominal_offset) + $offset;
                $detail->update([
                    'nominal_offset' => $this->decimalFromCents($newOffset),
                    'nominal_sisa' => $this->decimalFromCents($remaining),
                    'status' => $remaining === 0
                        ? PenyelesaianKeanggotaanDetail::STATUS_OFFSET
                        : PenyelesaianKeanggotaanDetail::STATUS_PARTIAL,
                    'processed_by' => $userId,
                    'processed_at' => $this->now(),
                ]);

                $this->allocateRightsToOffset($locked, $offset, $userId);
                $this->applyOffsetToSource($detail->fresh('source'), $offset, $remaining);

                $available -= $offset;
                $totalOffset += $offset;
            }

            $totals = $this->detailTotals($locked);
            $totalKewajiban = $totals['awal'];
            $totalOffset = $totals['offset'];
            $sisa = $totals['sisa'];
            $refund = $sisa === 0 ? max($totals['refund'], max(0, $totals['hak'] - $totalOffset)) : $totals['refund'];

            $locked->update([
                'total_hak_anggota' => $this->decimalFromCents($totals['hak']),
                'total_kewajiban_awal' => $this->decimalFromCents($totalKewajiban),
                'total_offset' => $this->decimalFromCents($totalOffset),
                'total_refund' => $this->decimalFromCents($refund),
                'sisa_kewajiban' => $this->decimalFromCents($sisa),
                'status' => $sisa === 0
                    ? PenyelesaianKeanggotaan::STATUS_READY_TO_COMPLETE
                    : PenyelesaianKeanggotaan::STATUS_WAITING_SETTLEMENT,
                'processed_by' => $userId,
                'processed_at' => $this->now(),
            ]);

            $this->akuntansiService->recordPenyelesaianKeanggotaanOffsetFromDetails($locked, $userId);

            return $locked->fresh(['details.source', 'jurnal.details']);
        });
    }

    public function processRefund(PenyelesaianKeanggotaan $penyelesaian, DompetKoperasi $dompet, ?int $userId, ?string $metodeRefund = null): PenyelesaianKeanggotaan
    {
        return DB::transaction(function () use ($penyelesaian, $dompet, $userId, $metodeRefund): PenyelesaianKeanggotaan {
            $locked = PenyelesaianKeanggotaan::query()
                ->with(['anggota', 'mutasiKas'])
                ->lockForUpdate()
                ->findOrFail($penyelesaian->id);

            if ($locked->status === PenyelesaianKeanggotaan::STATUS_COMPLETED) {
                throw ValidationException::withMessages(['penyelesaian' => 'Penyelesaian completed tidak dapat direfund ulang.']);
            }

            if ($locked->status === PenyelesaianKeanggotaan::STATUS_DEACTIVATION_CANCELLED) {
                throw ValidationException::withMessages(['penyelesaian' => 'Penonaktifan sudah dibatalkan; settlement ini tidak dapat direfund.']);
            }

            if ($this->decimalToCents($locked->sisa_kewajiban) > 0) {
                throw ValidationException::withMessages(['penyelesaian' => 'Refund hanya dapat diproses setelah seluruh kewajiban nol.']);
            }

            $locked = $this->refreshSnapshot($locked);
            if ($this->decimalToCents($locked->sisa_kewajiban) > 0) {
                throw ValidationException::withMessages(['penyelesaian' => 'Refund hanya dapat diproses setelah seluruh kewajiban nol.']);
            }

            $refund = $this->rightRemainingCents($locked);
            if ($refund <= 0) {
                return $locked->fresh(['mutasiKas', 'jurnal.details']);
            }

            $lockedDompet = $this->validRefundDompet($dompet->id, $metodeRefund);
            if ($this->decimalToCents($lockedDompet->saldo) < $refund) {
                throw ValidationException::withMessages(['dompet_id' => 'Saldo Dompet tidak mencukupi untuk refund penyelesaian keanggotaan.']);
            }

            $existingMutasi = MutasiKas::query()
                ->where('idempotency_key', 'keanggotaan:refund:mutasi:' . $locked->id)
                ->first();

            if (! $existingMutasi) {
                MutasiKas::query()->create([
                    'idempotency_key' => 'keanggotaan:refund:mutasi:' . $locked->id,
                    'dompet_id' => $lockedDompet->id,
                    'tipe' => 'keluar',
                    'jumlah' => $this->decimalFromCents($refund),
                    'keterangan' => 'Refund penyelesaian keanggotaan ' . $locked->kode_penyelesaian,
                    'referensi_tipe' => PenyelesaianKeanggotaan::class,
                    'referensi_id' => $locked->id,
                    'tanggal' => $this->today(),
                ]);

                $lockedDompet->update([
                    'saldo' => $this->decimalFromCents($this->decimalToCents($lockedDompet->saldo) - $refund),
                ]);
            }

            $this->allocateRightsToRefund($locked, $refund, $userId);

            $this->akuntansiService->recordPenyelesaianKeanggotaanRefundFromDetails(
                $locked,
                $lockedDompet->akun,
                $userId
            );

            $totals = $this->detailTotals($locked);
            $locked->update([
                'dompet_refund_id' => $lockedDompet->id,
                'metode_refund' => $lockedDompet->jenis_dompet === DompetKoperasi::JENIS_BANK
                    ? PenyelesaianKeanggotaan::METODE_TRANSFER_BANK
                    : PenyelesaianKeanggotaan::METODE_TUNAI,
                'total_refund' => $this->decimalFromCents($totals['refund']),
                'processed_by' => $userId,
                'processed_at' => $locked->processed_at ?? $this->now(),
            ]);

            return $locked->fresh(['dompetRefund', 'mutasiKas', 'jurnal.details']);
        });
    }

    public function complete(PenyelesaianKeanggotaan $penyelesaian, ?int $userId): PenyelesaianKeanggotaan
    {
        return DB::transaction(function () use ($penyelesaian, $userId): PenyelesaianKeanggotaan {
            $locked = PenyelesaianKeanggotaan::query()->with('mutasiKas')->lockForUpdate()->findOrFail($penyelesaian->id);

            if ($locked->status === PenyelesaianKeanggotaan::STATUS_COMPLETED) {
                return $locked;
            }

            if ($locked->status === PenyelesaianKeanggotaan::STATUS_DEACTIVATION_CANCELLED) {
                throw ValidationException::withMessages(['penyelesaian' => 'Penonaktifan sudah dibatalkan; settlement ini tidak dapat ditandai completed.']);
            }

            if ($this->decimalToCents($locked->sisa_kewajiban) > 0) {
                throw ValidationException::withMessages(['penyelesaian' => 'Penyelesaian belum dapat completed karena masih ada kewajiban.']);
            }

            if ($this->rightRemainingCents($locked) > 0) {
                throw ValidationException::withMessages(['penyelesaian' => 'Penyelesaian belum dapat completed karena masih ada hak Anggota yang belum di-offset atau direfund.']);
            }

            if ($this->decimalToCents($locked->total_refund) > 0 && ! $locked->mutasiKas()->exists()) {
                throw ValidationException::withMessages(['penyelesaian' => 'Refund wajib diproses sebelum penyelesaian completed.']);
            }

            if ($this->oldManasukaSaldoCents($locked) > 0) {
                throw ValidationException::withMessages(['penyelesaian' => 'Saldo Simpanan Manasuka siklus lama wajib nol sebelum penyelesaian completed.']);
            }

            $locked->update([
                'status' => PenyelesaianKeanggotaan::STATUS_COMPLETED,
                'completed_by' => $userId,
                'completed_at' => $this->now(),
            ]);

            return $locked->fresh(['details.source', 'mutasiKas', 'jurnal.details']);
        });
    }

    /**
     * @return array{eligible:bool,reasons:array<int,string>}
     */
    public function deactivationCancellationEligibility(PenyelesaianKeanggotaan $penyelesaian): array
    {
        $fresh = PenyelesaianKeanggotaan::query()
            ->with(['anggota.karyawan', 'siklus'])
            ->findOrFail($penyelesaian->id);
        $reasons = $this->deactivationCancellationBlockers($fresh);

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array{eligible:bool,reasons:array<int,string>}
     */
    public function reRegistrationEligibility(PenyelesaianKeanggotaan $penyelesaian): array
    {
        $fresh = PenyelesaianKeanggotaan::query()
            ->with(['anggota.karyawan', 'siklus'])
            ->findOrFail($penyelesaian->id);
        $reasons = $this->reRegistrationBlockers($fresh, null);

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    public function cancelDeactivation(PenyelesaianKeanggotaan $penyelesaian, string $reason, ?int $userId): PenyelesaianKeanggotaan
    {
        return DB::transaction(function () use ($penyelesaian, $reason, $userId): PenyelesaianKeanggotaan {
            $locked = PenyelesaianKeanggotaan::query()
                ->with(['anggota.karyawan', 'siklus'])
                ->lockForUpdate()
                ->findOrFail($penyelesaian->id);

            if ($locked->status === PenyelesaianKeanggotaan::STATUS_DEACTIVATION_CANCELLED) {
                return $locked->fresh(['anggota.karyawan', 'siklus', 'details.source']);
            }

            $blockers = $this->deactivationCancellationBlockers($locked);
            if ($blockers !== []) {
                throw ValidationException::withMessages(['penyelesaian' => implode(' ', $blockers)]);
            }

            $anggota = Anggota::query()->with('karyawan.user')->lockForUpdate()->findOrFail($locked->anggota_id);
            $karyawan = Karyawan::query()->with('user')->lockForUpdate()->findOrFail($anggota->karyawan_id);
            $siklus = SiklusKeanggotaan::query()->lockForUpdate()->findOrFail($locked->siklus_keanggotaan_id);

            $this->restoreCancelledWajibForDeactivation($locked, $userId, $reason);
            $this->unfreezeManasukaSaldo($locked);

            $siklus->update([
                'status' => SiklusKeanggotaan::STATUS_ACTIVE,
                'tanggal_selesai' => null,
                'alasan_selesai' => null,
                'closed_by' => null,
            ]);

            $anggota->update([
                'status' => Anggota::STATUS_AKTIF,
                'tanggal_nonaktif' => null,
            ]);

            $karyawan->update([
                'status_kerja' => Karyawan::STATUS_AKTIF,
                'tanggal_berhenti' => null,
            ]);

            $karyawan->user()->update([
                'is_active' => true,
                'account_updated_by' => $userId,
                'account_deactivated_by' => null,
                'account_deactivated_at' => null,
            ]);

            $this->syncLegacyIsAnggotaForLifecycle($karyawan->fresh(), $anggota->fresh());

            $locked->update([
                'status' => PenyelesaianKeanggotaan::STATUS_DEACTIVATION_CANCELLED,
                'deactivation_cancelled_by' => $userId,
                'deactivation_cancelled_at' => $this->now(),
                'deactivation_cancel_reason' => trim($reason),
                'processed_by' => $userId,
                'processed_at' => $this->now(),
            ]);

            return $locked->fresh(['anggota.karyawan', 'siklus', 'details.source']);
        });
    }

    public function reRegisterMember(PenyelesaianKeanggotaan $penyelesaian, CarbonInterface|string $tanggalBergabung, string $reason, ?int $userId): Anggota
    {
        return DB::transaction(function () use ($penyelesaian, $tanggalBergabung, $reason, $userId): Anggota {
            $tanggal = $this->normalizeDate($tanggalBergabung);
            if ($tanggal->greaterThan(CarbonImmutable::now($this->timezone())->startOfDay())) {
                throw ValidationException::withMessages(['tanggal_bergabung' => 'Tanggal bergabung baru tidak boleh melebihi hari ini.']);
            }

            $locked = PenyelesaianKeanggotaan::query()
                ->with(['anggota.karyawan', 'siklus'])
                ->lockForUpdate()
                ->findOrFail($penyelesaian->id);

            if ($locked->re_registered_cycle_id) {
                return $locked->anggota->fresh(['karyawan', 'siklusAktif', 'saldoSimpananManasuka', 'simpanan']);
            }

            $blockers = $this->reRegistrationBlockers($locked, $tanggal);
            if ($blockers !== []) {
                throw ValidationException::withMessages(['penyelesaian' => implode(' ', $blockers)]);
            }

            $anggota = Anggota::query()->with('karyawan')->lockForUpdate()->findOrFail($locked->anggota_id);
            $karyawan = Karyawan::query()->lockForUpdate()->findOrFail($anggota->karyawan_id);

            $anggota->update([
                'status' => Anggota::STATUS_AKTIF,
                'tanggal_nonaktif' => null,
                'tanggal_bergabung' => $tanggal->toDateString(),
            ]);

            $cycle = $this->createReRegistrationCycle($anggota->fresh(), $tanggal, $userId);
            $this->ensureZeroManasukaSaldoForCycle($anggota->fresh(), $cycle);
            $this->createSimpananPokokForCycle($anggota->fresh(), $cycle, $userId);
            app(SimpananWajibService::class)->generateUntil($tanggal, $anggota->fresh(), $userId);

            $this->syncLegacyIsAnggotaForLifecycle($karyawan->fresh(), $anggota->fresh());

            $locked->update([
                're_registered_by' => $userId,
                're_registered_at' => $this->now(),
                're_register_reason' => trim($reason),
                're_registered_cycle_id' => $cycle->id,
                're_registration_idempotency_key' => 'keanggotaan:daftar-ulang:' . $locked->id,
            ]);

            return $anggota->fresh(['karyawan', 'siklusAktif', 'saldoSimpananManasuka', 'simpanan']);
        });
    }

    public function reactivateAnggota(Anggota $anggota, CarbonInterface|string|null $tanggalMulai, ?int $userId): Anggota
    {
        return DB::transaction(function () use ($anggota, $tanggalMulai, $userId): Anggota {
            $locked = Anggota::query()->with('karyawan')->lockForUpdate()->findOrFail($anggota->id);

            if ($locked->karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
                throw ValidationException::withMessages(['status' => 'Anggota tidak dapat diaktifkan karena Karyawannya belum aktif.']);
            }

            $latestClosed = SiklusKeanggotaan::query()
                ->where('anggota_id', $locked->id)
                ->orderByDesc('siklus_ke')
                ->lockForUpdate()
                ->first();

            if (! $latestClosed || $latestClosed->status !== SiklusKeanggotaan::STATUS_CLOSED) {
                throw ValidationException::withMessages([
                    'penyelesaian' => 'Aktivasi Anggota harus melalui Batalkan Penonaktifan atau Daftarkan Kembali pada detail Penyelesaian Keanggotaan.',
                ]);
            }

            $settlement = PenyelesaianKeanggotaan::query()
                ->where('siklus_keanggotaan_id', $latestClosed->id)
                ->where('status', PenyelesaianKeanggotaan::STATUS_COMPLETED)
                ->latest('id')
                ->first();

            if (! $settlement) {
                throw ValidationException::withMessages([
                    'penyelesaian' => 'Daftarkan kembali ditolak sampai penyelesaian keanggotaan sebelumnya completed.',
                ]);
            }

            return $this->reRegisterMember(
                $settlement,
                $tanggalMulai ?? $this->today(),
                'Pendaftaran kembali Anggota melalui flow aktivasi legacy.',
                $userId
            );
        });
    }

    public function createSimpananPokokForCycle(Anggota $anggota, SiklusKeanggotaan $cycle, ?int $userId = null): ?Simpanan
    {
        $jenis = $this->resolveSimpananPokokMaster();
        $existing = Simpanan::query()
            ->where('siklus_keanggotaan_id', $cycle->id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->whereNotIn('status', [Simpanan::STATUS_REVERSED, Simpanan::STATUS_REVERSED_DUE_TO_EXIT])
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        $nominal = $jenis->nominal_default;
        if ($nominal === null || (float) $nominal <= 0) {
            throw ValidationException::withMessages(['simpanan_pokok' => 'Nominal default Simpanan Pokok aktif wajib lebih besar dari nol.']);
        }

        $simpanan = Simpanan::query()->create([
            'idempotency_key' => 'simpanan-pokok:siklus:' . $cycle->id,
            'anggota_id' => $anggota->id,
            'karyawan_id' => $anggota->karyawan_id,
            'siklus_keanggotaan_id' => $cycle->id,
            'jenis_simpanan_id' => $jenis->id,
            'kode_jenis_snapshot' => JenisSimpanan::KODE_SIMPANAN_POKOK,
            'nama_jenis_snapshot' => $jenis->nama_jenis,
            'nominal_snapshot' => $nominal,
            'jumlah' => $nominal,
            'metode_pembayaran' => Simpanan::METODE_POTONG_GAJI,
            'status' => Simpanan::STATUS_PENDING_PAYROLL,
            'tanggal' => $cycle->tanggal_mulai,
            'keterangan' => 'Simpanan Pokok otomatis saat siklus keanggotaan aktif.',
            'created_by' => $userId,
        ]);

        $this->akuntansiService->recordSimpananPokokPayroll($simpanan, $userId);

        return $simpanan;
    }

    /**
     * @return array<int, string>
     */
    private function deactivationCancellationBlockers(PenyelesaianKeanggotaan $penyelesaian): array
    {
        $reasons = [];

        if ($penyelesaian->status === PenyelesaianKeanggotaan::STATUS_DEACTIVATION_CANCELLED) {
            $reasons[] = 'Penonaktifan sudah dibatalkan sebelumnya.';
        }

        if ($penyelesaian->status === PenyelesaianKeanggotaan::STATUS_COMPLETED) {
            $reasons[] = 'Penyelesaian sudah completed sehingga tidak dapat dibatalkan sebagai salah input.';
        }

        if (in_array($penyelesaian->status, [
            PenyelesaianKeanggotaan::STATUS_WAITING_SETTLEMENT,
            PenyelesaianKeanggotaan::STATUS_READY_TO_COMPLETE,
        ], true)) {
            $reasons[] = 'Penyelesaian sudah masuk proses material. Gunakan koreksi manual, bukan Batalkan Penonaktifan.';
        }

        if ($penyelesaian->siklus && $penyelesaian->siklus->status !== SiklusKeanggotaan::STATUS_CLOSED) {
            $reasons[] = 'Siklus lama tidak dalam status closed.';
        }

        if ($this->hasMaterialSettlementProcess($penyelesaian)) {
            $reasons[] = 'Sudah ada refund, offset, pembayaran tunai, Mutasi Kas, atau pembalikan Pokok yang membuat pembatalan otomatis tidak aman.';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @return array<int, string>
     */
    private function reRegistrationBlockers(PenyelesaianKeanggotaan $penyelesaian, ?CarbonImmutable $tanggalBergabung): array
    {
        $reasons = [];
        $anggota = $penyelesaian->anggota;
        $karyawan = $anggota?->karyawan;

        if ($penyelesaian->status !== PenyelesaianKeanggotaan::STATUS_COMPLETED) {
            $reasons[] = 'Daftarkan kembali hanya dapat dilakukan setelah penyelesaian completed.';
        }

        if ($penyelesaian->re_registered_cycle_id) {
            $reasons[] = 'Pendaftaran kembali sudah pernah diproses untuk settlement ini.';
        }

        if (! $anggota || ! $karyawan) {
            $reasons[] = 'Data Anggota/Karyawan tidak lengkap.';
        } elseif ($karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
            $reasons[] = 'Karyawan harus aktif sebelum didaftarkan kembali sebagai Anggota.';
        }

        if ($anggota && SiklusKeanggotaan::query()
            ->where('anggota_id', $anggota->id)
            ->where('status', SiklusKeanggotaan::STATUS_ACTIVE)
            ->exists()) {
            $reasons[] = 'Anggota masih mempunyai siklus keanggotaan aktif.';
        }

        if ($this->decimalToCents($penyelesaian->sisa_kewajiban) > 0) {
            $reasons[] = 'Masih ada sisa kewajiban pada penyelesaian lama.';
        }

        if ($this->rightRemainingCentsReadOnly($penyelesaian) > 0) {
            $reasons[] = 'Masih ada hak Anggota pada penyelesaian lama yang belum dialokasikan/refund.';
        }

        if ($this->oldManasukaSaldoCents($penyelesaian) > 0) {
            $reasons[] = 'Saldo Simpanan Manasuka siklus lama belum nol.';
        }

        if ($anggota && $this->hasActiveOldPayrollReservations($anggota->id, $penyelesaian->siklus_keanggotaan_id)) {
            $reasons[] = 'Masih ada reservasi/pemakaian payroll aktif dari siklus lama.';
        }

        if ($anggota && $this->hasUnpaidOldPinjaman($anggota->id, $penyelesaian->siklus_keanggotaan_id)) {
            $reasons[] = 'Masih ada Pinjaman lama yang belum lunas. Lunasi kewajiban Pinjaman sebelum daftar ulang.';
        }

        if ($tanggalBergabung && $tanggalBergabung->greaterThan(CarbonImmutable::now($this->timezone())->startOfDay())) {
            $reasons[] = 'Tanggal bergabung baru tidak boleh melebihi hari ini.';
        }

        return array_values(array_unique($reasons));
    }

    private function hasMaterialSettlementProcess(PenyelesaianKeanggotaan $penyelesaian): bool
    {
        if ($penyelesaian->mutasiKas()->exists()) {
            return true;
        }

        if (JurnalUmum::query()
            ->where('referensi_tipe', PenyelesaianKeanggotaan::class)
            ->where('referensi_id', $penyelesaian->id)
            ->where(function ($query): void {
                $query->where('idempotency_key', 'like', 'keanggotaan:offset:jurnal:%')
                    ->orWhere('idempotency_key', 'like', 'keanggotaan:refund:jurnal:%');
            })
            ->exists()) {
            return true;
        }

        if (PenyelesaianKeanggotaanDetail::query()
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->where(function ($query): void {
                $query->whereRaw('CAST(nominal_dipakai_offset AS DECIMAL(15,2)) > 0')
                    ->orWhereRaw('CAST(nominal_direfund AS DECIMAL(15,2)) > 0')
                    ->orWhereRaw('CAST(nominal_offset AS DECIMAL(15,2)) > 0')
                    ->orWhereRaw('CAST(nominal_dibayar_tunai AS DECIMAL(15,2)) > 0');
            })
            ->exists()) {
            return true;
        }

        return Simpanan::query()
            ->where('anggota_id', $penyelesaian->anggota_id)
            ->where('siklus_keanggotaan_id', $penyelesaian->siklus_keanggotaan_id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->where('status', Simpanan::STATUS_REVERSED_DUE_TO_EXIT)
            ->exists();
    }

    private function rightRemainingCentsReadOnly(PenyelesaianKeanggotaan $penyelesaian): int
    {
        return PenyelesaianKeanggotaanDetail::query()
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->where('tipe_detail', PenyelesaianKeanggotaanDetail::TIPE_HAK)
            ->get()
            ->sum(function (PenyelesaianKeanggotaanDetail $detail): int {
                $total = $this->decimalToCents($detail->nominal_hak_awal);
                $used = $this->decimalToCents($detail->nominal_dipakai_offset)
                    + $this->decimalToCents($detail->nominal_direfund);

                return max(0, $total - $used);
            });
    }

    private function restoreCancelledWajibForDeactivation(PenyelesaianKeanggotaan $penyelesaian, ?int $userId, string $reason): void
    {
        $jadwals = JadwalSimpananWajib::query()
            ->with(['simpanan.jenisSimpanan.akun', 'simpanan.jadwalSimpananWajib'])
            ->where('anggota_id', $penyelesaian->anggota_id)
            ->where('siklus_keanggotaan_id', $penyelesaian->siklus_keanggotaan_id)
            ->where('status', JadwalSimpananWajib::STATUS_CANCELLED_EXIT)
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->orderBy('periode')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($jadwals as $jadwal) {
            $simpanan = $jadwal->simpanan;
            if (! $simpanan) {
                throw ValidationException::withMessages([
                    'simpanan_wajib' => 'Jadwal Simpanan Wajib yang dibatalkan tidak mempunyai transaksi Simpanan untuk dipulihkan.',
                ]);
            }

            $jurnal = $jadwal->recovery_jurnal_id
                ? JurnalUmum::query()->find($jadwal->recovery_jurnal_id)
                : null;
            $jurnal ??= $this->akuntansiService->recordSimpananWajibExitRecovery($simpanan, $userId);

            if ($simpanan->status === Simpanan::STATUS_REVERSED_DUE_TO_EXIT) {
                $simpanan->update([
                    'status' => Simpanan::STATUS_PENDING_PAYROLL,
                    'pemakaian_potong_gaji_id' => null,
                    'reversal_transaksi_id' => null,
                    'penyelesaian_keanggotaan_id' => null,
                    'metode_pembayaran' => Simpanan::METODE_POTONG_GAJI,
                ]);
            }

            $jadwal->update([
                'status' => JadwalSimpananWajib::STATUS_OUTSTANDING,
                'reserved_at' => null,
                'penyelesaian_keanggotaan_id' => null,
                'recovery_jurnal_id' => $jurnal?->id,
                'recovered_at' => $jadwal->recovered_at ?? $this->now(),
                'recovered_by' => $userId,
                'recovery_reason' => trim($reason),
            ]);
        }
    }

    private function unfreezeManasukaSaldo(PenyelesaianKeanggotaan $penyelesaian): void
    {
        SaldoSimpananManasuka::query()
            ->where('anggota_id', $penyelesaian->anggota_id)
            ->where('siklus_keanggotaan_id', $penyelesaian->siklus_keanggotaan_id)
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->lockForUpdate()
            ->update([
                'penyelesaian_keanggotaan_id' => null,
                'frozen_at' => null,
            ]);
    }

    private function createReRegistrationCycle(Anggota $anggota, CarbonImmutable $tanggalMulai, ?int $userId): SiklusKeanggotaan
    {
        $existingActive = SiklusKeanggotaan::query()
            ->where('anggota_id', $anggota->id)
            ->where('status', SiklusKeanggotaan::STATUS_ACTIVE)
            ->lockForUpdate()
            ->first();

        if ($existingActive) {
            throw ValidationException::withMessages(['siklus' => 'Anggota sudah mempunyai siklus aktif.']);
        }

        $next = ((int) SiklusKeanggotaan::query()->where('anggota_id', $anggota->id)->max('siklus_ke')) + 1;

        try {
            return SiklusKeanggotaan::query()->create([
                'anggota_id' => $anggota->id,
                'siklus_ke' => $next,
                'tanggal_mulai' => $tanggalMulai->toDateString(),
                'tanggal_selesai' => null,
                'status' => SiklusKeanggotaan::STATUS_ACTIVE,
                'alasan_selesai' => null,
                'created_by' => $userId,
            ]);
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'siklus' => 'Pendaftaran kembali sudah diproses oleh transaksi lain. Muat ulang halaman.',
            ]);
        }
    }

    private function syncLegacyIsAnggotaForLifecycle(Karyawan $karyawan, Anggota $anggota): void
    {
        $aktif = $karyawan->status_kerja === Karyawan::STATUS_AKTIF
            && $anggota->status === Anggota::STATUS_AKTIF;

        $karyawan->forceFill(['is_anggota' => $aktif])->saveQuietly();
    }

    private function rightSources(PenyelesaianKeanggotaan $penyelesaian): Collection
    {
        $simpananRows = Simpanan::query()
            ->with('jenisSimpanan.akun')
            ->where('anggota_id', $penyelesaian->anggota_id)
            ->where('siklus_keanggotaan_id', $penyelesaian->siklus_keanggotaan_id)
            ->whereIn('kode_jenis_snapshot', [
                JenisSimpanan::KODE_SIMPANAN_POKOK,
                JenisSimpanan::KODE_SIMPANAN_WAJIB,
            ])
            ->whereIn('status', [Simpanan::STATUS_SETTLED, Simpanan::STATUS_SETTLED_CASH])
            ->whereNull('penyelesaian_keanggotaan_id')
            ->orderByRaw("case when kode_jenis_snapshot = 'SIMPANAN_POKOK' then 1 else 2 end")
            ->orderBy('tanggal')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->map(function (Simpanan $simpanan): array {
                $kategori = $simpanan->kode_jenis_snapshot === JenisSimpanan::KODE_SIMPANAN_POKOK
                    ? PenyelesaianKeanggotaanDetail::KATEGORI_SIMPANAN_POKOK
                    : PenyelesaianKeanggotaanDetail::KATEGORI_SIMPANAN_WAJIB;

                return $this->rightPayload(
                    $kategori,
                    Simpanan::class,
                    $simpanan->id,
                    $this->decimalToCents($simpanan->nominal_snapshot ?? $simpanan->jumlah),
                    $simpanan->jenisSimpanan?->akun
                );
            });

        $saldoRows = SaldoSimpananManasuka::query()
            ->with('jenisSimpanan.akun')
            ->where('anggota_id', $penyelesaian->anggota_id)
            ->where('siklus_keanggotaan_id', $penyelesaian->siklus_keanggotaan_id)
            ->where(function ($query) use ($penyelesaian): void {
                $query->whereNull('penyelesaian_keanggotaan_id')
                    ->orWhere('penyelesaian_keanggotaan_id', $penyelesaian->id);
            })
            ->whereRaw('CAST(saldo AS DECIMAL(15,2)) > 0')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->map(fn (SaldoSimpananManasuka $saldo): array => $this->rightPayload(
                PenyelesaianKeanggotaanDetail::KATEGORI_SIMPANAN_MANASUKA,
                SaldoSimpananManasuka::class,
                $saldo->id,
                $this->decimalToCents($saldo->saldo),
                $saldo->jenisSimpanan?->akun
            ));

        $creditRows = KreditPotongGajiAnggota::query()
            ->where('anggota_id', $penyelesaian->anggota_id)
            ->whereIn('status', [KreditPotongGajiAnggota::STATUS_OPEN, KreditPotongGajiAnggota::STATUS_PARTIALLY_APPLIED])
            ->whereRaw('CAST(nominal_sisa AS DECIMAL(15,2)) > 0')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->map(fn (KreditPotongGajiAnggota $credit): array => $this->rightPayload(
                PenyelesaianKeanggotaanDetail::KATEGORI_KREDIT_REFUND,
                KreditPotongGajiAnggota::class,
                $credit->id,
                $this->decimalToCents($credit->nominal_sisa),
                $this->accountForRightCategory(PenyelesaianKeanggotaanDetail::KATEGORI_KREDIT_REFUND)
            ));

        return $simpananRows
            ->concat($saldoRows)
            ->concat($creditRows)
            ->filter(fn (array $source): bool => $source['nominal_cents'] > 0)
            ->values();
    }

    private function rightPayload(string $kategori, string $sourceType, int $sourceId, int $nominalCents, ?Akun $akun): array
    {
        if (! $akun || ! $akun->is_aktif || ! in_array($akun->kategori, ['kewajiban', 'ekuitas'], true) || $akun->posisi_saldo !== 'kredit') {
            throw new RuntimeException('Akun sumber hak Anggota tidak valid untuk penyelesaian keanggotaan.');
        }

        return [
            'kategori' => $kategori,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'nominal_cents' => $nominalCents,
            'akun' => $akun,
        ];
    }

    private function accountForRightCategory(string $kategori): Akun
    {
        return match ($kategori) {
            PenyelesaianKeanggotaanDetail::KATEGORI_SIMPANAN_POKOK => app(AkunResolver::class)->posting('keanggotaan.simpanan_pokok'),
            PenyelesaianKeanggotaanDetail::KATEGORI_SIMPANAN_WAJIB => app(AkunResolver::class)->posting('keanggotaan.simpanan_wajib'),
            PenyelesaianKeanggotaanDetail::KATEGORI_SIMPANAN_MANASUKA => app(AkunResolver::class)->posting('keanggotaan.simpanan_manasuka'),
            PenyelesaianKeanggotaanDetail::KATEGORI_KREDIT_REFUND => app(AkunResolver::class)->posting('keanggotaan.utang_refund_anggota'),
            default => throw new RuntimeException('Kategori hak Anggota tidak dikenal.'),
        };
    }

    private function obligationSources(Anggota $anggota): Collection
    {
        $pinjaman = Pinjaman::query()
            ->where('anggota_id', $anggota->id)
            ->where('status', Pinjaman::STATUS_AKTIF)
            ->whereRaw('CAST(sisa_pinjaman AS DECIMAL(15,2)) > 0')
            ->get()
            ->map(fn (Pinjaman $pinjaman): array => [
                'kategori' => PenyelesaianKeanggotaanDetail::KATEGORI_PINJAMAN,
                'source_type' => Pinjaman::class,
                'source_id' => $pinjaman->id,
                'nominal_cents' => $this->decimalToCents($pinjaman->sisa_pinjaman),
            ]);

        $pos = Pembayaran::query()
            ->join('penjualan', 'penjualan.id', '=', 'pembayaran.penjualan_id')
            ->where('penjualan.anggota_id', $anggota->id)
            ->where('pembayaran.status', Pembayaran::STATUS_OUTSTANDING_CASH)
            ->select('pembayaran.*')
            ->get()
            ->map(fn (Pembayaran $pembayaran): array => [
                'kategori' => PenyelesaianKeanggotaanDetail::KATEGORI_POS,
                'source_type' => Pembayaran::class,
                'source_id' => $pembayaran->id,
                'nominal_cents' => $this->decimalToCents($pembayaran->jumlah_bayar),
            ]);

        $simpanan = Simpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('status', Simpanan::STATUS_OUTSTANDING_CASH)
            ->get()
            ->map(fn (Simpanan $simpanan): array => [
                'kategori' => PenyelesaianKeanggotaanDetail::KATEGORI_SIMPANAN,
                'source_type' => Simpanan::class,
                'source_id' => $simpanan->id,
                'nominal_cents' => $this->decimalToCents($simpanan->nominal_snapshot ?? $simpanan->jumlah),
            ]);

        return $pinjaman->concat($pos)->concat($simpanan)->values();
    }

    private function upsertRightDetail(PenyelesaianKeanggotaan $penyelesaian, array $right, int $order): void
    {
        $existing = PenyelesaianKeanggotaanDetail::query()
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->where('source_type', $right['source_type'])
            ->where('source_id', $right['source_id'])
            ->lockForUpdate()
            ->first();

        if ($existing && $this->decimalToCents($existing->nominal_dipakai_offset) + $this->decimalToCents($existing->nominal_direfund) > 0) {
            return;
        }

        PenyelesaianKeanggotaanDetail::query()->updateOrCreate(
            [
                'penyelesaian_keanggotaan_id' => $penyelesaian->id,
                'source_type' => $right['source_type'],
                'source_id' => $right['source_id'],
            ],
            [
                'tipe_detail' => PenyelesaianKeanggotaanDetail::TIPE_HAK,
                'kategori_sumber' => $right['kategori'],
                'akun_id' => $right['akun']->id,
                'akun_kode_snapshot' => $right['akun']->kode_akun,
                'akun_nama_snapshot' => $right['akun']->nama_akun,
                'nominal_hak_awal' => $this->decimalFromCents($right['nominal_cents']),
                'nominal_kewajiban_awal' => '0.00',
                'nominal_sisa' => '0.00',
                'urutan_alokasi' => $order,
                'status' => PenyelesaianKeanggotaanDetail::STATUS_OPEN,
                'idempotency_key' => 'penyelesaian:hak:' . $penyelesaian->id . ':' . class_basename($right['source_type']) . ':' . $right['source_id'],
            ]
        );
    }

    private function upsertObligationDetail(PenyelesaianKeanggotaan $penyelesaian, array $obligation, int $order): void
    {
        $existing = PenyelesaianKeanggotaanDetail::query()
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->where('source_type', $obligation['source_type'])
            ->where('source_id', $obligation['source_id'])
            ->lockForUpdate()
            ->first();

        if ($existing && (
            $this->decimalToCents($existing->nominal_offset)
            + $this->decimalToCents($existing->nominal_dibayar_tunai)
        ) > 0) {
            return;
        }

        PenyelesaianKeanggotaanDetail::query()->updateOrCreate(
            [
                'penyelesaian_keanggotaan_id' => $penyelesaian->id,
                'source_type' => $obligation['source_type'],
                'source_id' => $obligation['source_id'],
            ],
            [
                'tipe_detail' => PenyelesaianKeanggotaanDetail::TIPE_KEWAJIBAN,
                'kategori_sumber' => $obligation['kategori'],
                'nominal_kewajiban_awal' => $this->decimalFromCents($obligation['nominal_cents']),
                'nominal_sisa' => $this->decimalFromCents($obligation['nominal_cents']),
                'urutan_alokasi' => $order,
                'status' => PenyelesaianKeanggotaanDetail::STATUS_OPEN,
                'idempotency_key' => 'penyelesaian:kewajiban:' . $penyelesaian->id . ':' . class_basename($obligation['source_type']) . ':' . $obligation['source_id'],
            ]
        );
    }

    /**
     * @param  array<int, string>  $currentSourceKeys
     */
    private function markStaleDetailsAsSettledCash(PenyelesaianKeanggotaan $penyelesaian, array $currentSourceKeys): void
    {
        PenyelesaianKeanggotaanDetail::query()
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->where('tipe_detail', PenyelesaianKeanggotaanDetail::TIPE_KEWAJIBAN)
            ->whereIn('status', [PenyelesaianKeanggotaanDetail::STATUS_OPEN, PenyelesaianKeanggotaanDetail::STATUS_PARTIAL])
            ->lockForUpdate()
            ->get()
            ->each(function (PenyelesaianKeanggotaanDetail $detail) use ($currentSourceKeys): void {
                $key = $detail->source_type . '#' . $detail->source_id;
                if (in_array($key, $currentSourceKeys, true)) {
                    return;
                }

                $sisa = $this->decimalToCents($detail->nominal_sisa);
                $dibayar = $this->decimalToCents($detail->nominal_dibayar_tunai) + $sisa;

                $detail->update([
                    'nominal_dibayar_tunai' => $this->decimalFromCents($dibayar),
                    'nominal_sisa' => '0.00',
                    'status' => PenyelesaianKeanggotaanDetail::STATUS_SETTLED_CASH,
                ]);
            });
    }

    /**
     * @return array{awal:int,offset:int,cash:int,sisa:int,hak:int,hak_pokok:int,hak_kredit:int,refund:int}
     */
    private function detailTotals(PenyelesaianKeanggotaan $penyelesaian): array
    {
        $rows = PenyelesaianKeanggotaanDetail::query()
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->get([
                'tipe_detail',
                'kategori_sumber',
                'nominal_hak_awal',
                'nominal_dipakai_offset',
                'nominal_direfund',
                'nominal_kewajiban_awal',
                'nominal_offset',
                'nominal_dibayar_tunai',
                'nominal_sisa',
            ]);

        $hakRows = $rows->where('tipe_detail', PenyelesaianKeanggotaanDetail::TIPE_HAK);
        $kewajibanRows = $rows->where('tipe_detail', PenyelesaianKeanggotaanDetail::TIPE_KEWAJIBAN);

        return [
            'awal' => $kewajibanRows->sum(fn (PenyelesaianKeanggotaanDetail $detail): int => $this->decimalToCents($detail->nominal_kewajiban_awal)),
            'offset' => $kewajibanRows->sum(fn (PenyelesaianKeanggotaanDetail $detail): int => $this->decimalToCents($detail->nominal_offset)),
            'cash' => $kewajibanRows->sum(fn (PenyelesaianKeanggotaanDetail $detail): int => $this->decimalToCents($detail->nominal_dibayar_tunai)),
            'sisa' => $kewajibanRows->sum(fn (PenyelesaianKeanggotaanDetail $detail): int => $this->decimalToCents($detail->nominal_sisa)),
            'hak' => $hakRows->sum(fn (PenyelesaianKeanggotaanDetail $detail): int => $this->decimalToCents($detail->nominal_hak_awal)),
            'hak_pokok' => $hakRows
                ->where('kategori_sumber', PenyelesaianKeanggotaanDetail::KATEGORI_SIMPANAN_POKOK)
                ->sum(fn (PenyelesaianKeanggotaanDetail $detail): int => $this->decimalToCents($detail->nominal_hak_awal)),
            'hak_kredit' => $hakRows
                ->where('kategori_sumber', PenyelesaianKeanggotaanDetail::KATEGORI_KREDIT_REFUND)
                ->sum(fn (PenyelesaianKeanggotaanDetail $detail): int => $this->decimalToCents($detail->nominal_hak_awal)),
            'refund' => $hakRows->sum(fn (PenyelesaianKeanggotaanDetail $detail): int => $this->decimalToCents($detail->nominal_direfund)),
        ];
    }

    private function rightRemainingCents(PenyelesaianKeanggotaan $penyelesaian): int
    {
        return PenyelesaianKeanggotaanDetail::query()
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->where('tipe_detail', PenyelesaianKeanggotaanDetail::TIPE_HAK)
            ->lockForUpdate()
            ->get()
            ->sum(function (PenyelesaianKeanggotaanDetail $detail): int {
                $total = $this->decimalToCents($detail->nominal_hak_awal);
                $used = $this->decimalToCents($detail->nominal_dipakai_offset)
                    + $this->decimalToCents($detail->nominal_direfund);

                return max(0, $total - $used);
            });
    }

    private function allocateRightsToOffset(PenyelesaianKeanggotaan $penyelesaian, int $nominalCents, ?int $userId): void
    {
        $this->allocateRights($penyelesaian, $nominalCents, 'offset', $userId);
    }

    private function allocateRightsToRefund(PenyelesaianKeanggotaan $penyelesaian, int $nominalCents, ?int $userId): void
    {
        $this->allocateRights($penyelesaian, $nominalCents, 'refund', $userId);
    }

    private function allocateRights(PenyelesaianKeanggotaan $penyelesaian, int $nominalCents, string $mode, ?int $userId): void
    {
        $remaining = $nominalCents;
        $details = PenyelesaianKeanggotaanDetail::query()
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->where('tipe_detail', PenyelesaianKeanggotaanDetail::TIPE_HAK)
            ->whereIn('status', [
                PenyelesaianKeanggotaanDetail::STATUS_OPEN,
                PenyelesaianKeanggotaanDetail::STATUS_PARTIAL,
            ])
            ->orderBy('urutan_alokasi')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($details as $detail) {
            if ($remaining <= 0) {
                break;
            }

            $hak = $this->decimalToCents($detail->nominal_hak_awal);
            $offset = $this->decimalToCents($detail->nominal_dipakai_offset);
            $refund = $this->decimalToCents($detail->nominal_direfund);
            $available = max(0, $hak - $offset - $refund);
            $used = min($remaining, $available);

            if ($used <= 0) {
                continue;
            }

            $newOffset = $mode === 'offset' ? $offset + $used : $offset;
            $newRefund = $mode === 'refund' ? $refund + $used : $refund;
            $fullyUsed = ($newOffset + $newRefund) >= $hak;

            $detail->update([
                'nominal_dipakai_offset' => $this->decimalFromCents($newOffset),
                'nominal_direfund' => $this->decimalFromCents($newRefund),
                'status' => $fullyUsed
                    ? ($mode === 'refund' && $newOffset === 0
                        ? PenyelesaianKeanggotaanDetail::STATUS_REFUNDED
                        : ($newRefund > 0 ? PenyelesaianKeanggotaanDetail::STATUS_PARTIAL : PenyelesaianKeanggotaanDetail::STATUS_OFFSET))
                    : PenyelesaianKeanggotaanDetail::STATUS_PARTIAL,
                'processed_by' => $userId,
                'processed_at' => $this->now(),
            ]);

            $this->syncRightSourceAllocation($detail->fresh(), $userId);
            $remaining -= $used;
        }

        if ($remaining > 0) {
            throw new RuntimeException('Hak Anggota tidak cukup untuk alokasi settlement.');
        }
    }

    private function syncRightSourceAllocation(PenyelesaianKeanggotaanDetail $detail, ?int $userId): void
    {
        $allocated = $this->decimalToCents($detail->nominal_dipakai_offset)
            + $this->decimalToCents($detail->nominal_direfund);

        if ($detail->source_type === Simpanan::class) {
            Simpanan::query()
                ->whereKey($detail->source_id)
                ->whereNull('penyelesaian_keanggotaan_id')
                ->update(['penyelesaian_keanggotaan_id' => $detail->penyelesaian_keanggotaan_id]);
            return;
        }

        if ($detail->source_type === SaldoSimpananManasuka::class) {
            $saldo = SaldoSimpananManasuka::query()->lockForUpdate()->find($detail->source_id);
            if (! $saldo) {
                return;
            }

            $remaining = max(0, $this->decimalToCents($detail->nominal_hak_awal) - $allocated);
            $saldo->update([
                'saldo' => $this->decimalFromCents($remaining),
                'penyelesaian_keanggotaan_id' => $detail->penyelesaian_keanggotaan_id,
                'frozen_at' => $saldo->frozen_at ?? $this->now(),
            ]);
            return;
        }

        if ($detail->source_type === KreditPotongGajiAnggota::class) {
            $credit = KreditPotongGajiAnggota::query()->lockForUpdate()->find($detail->source_id);
            if (! $credit) {
                return;
            }

            $total = $this->decimalToCents($credit->nominal_awal);
            $baselineTerpakai = max(0, $total - $this->decimalToCents($detail->nominal_hak_awal));
            $newTerpakai = min($total, $baselineTerpakai + $allocated);
            $newSisa = max(0, $total - $newTerpakai);

            $credit->update([
                'nominal_terpakai' => $this->decimalFromCents($newTerpakai),
                'nominal_sisa' => $this->decimalFromCents($newSisa),
                'status' => $newSisa === 0
                    ? KreditPotongGajiAnggota::STATUS_APPLIED
                    : KreditPotongGajiAnggota::STATUS_PARTIALLY_APPLIED,
            ]);
        }
    }

    private function oldManasukaSaldoCents(PenyelesaianKeanggotaan $penyelesaian): int
    {
        return $this->decimalToCents((string) SaldoSimpananManasuka::query()
            ->where('anggota_id', $penyelesaian->anggota_id)
            ->where('siklus_keanggotaan_id', $penyelesaian->siklus_keanggotaan_id)
            ->sum('saldo'));
    }

    private function hasActiveOldPayrollReservations(int $anggotaId, int $siklusId): bool
    {
        return PemakaianPotongGaji::query()
            ->whereIn('status', [PemakaianPotongGaji::STATUS_RESERVED, PemakaianPotongGaji::STATUS_CONSUMED])
            ->where(function ($query) use ($anggotaId, $siklusId): void {
                $query->where(function ($jadwal) use ($anggotaId, $siklusId): void {
                    $jadwal->where('source_type', JadwalSimpananWajib::class)
                        ->whereExists(function ($exists) use ($anggotaId, $siklusId): void {
                            $exists->selectRaw('1')
                                ->from('jadwal_simpanan_wajib')
                                ->whereColumn('jadwal_simpanan_wajib.id', 'pemakaian_potong_gaji.source_id')
                                ->where('jadwal_simpanan_wajib.anggota_id', $anggotaId)
                                ->where('jadwal_simpanan_wajib.siklus_keanggotaan_id', $siklusId);
                        });
                })->orWhere(function ($simpanan) use ($anggotaId, $siklusId): void {
                    $simpanan->where('source_type', Simpanan::class)
                        ->whereExists(function ($exists) use ($anggotaId, $siklusId): void {
                            $exists->selectRaw('1')
                                ->from('simpanan')
                                ->whereColumn('simpanan.id', 'pemakaian_potong_gaji.source_id')
                                ->where('simpanan.anggota_id', $anggotaId)
                                ->where('simpanan.siklus_keanggotaan_id', $siklusId);
                        });
                })->orWhere(function ($cicilan) use ($anggotaId, $siklusId): void {
                    $cicilan->where('source_type', JadwalCicilanPinjaman::class)
                        ->whereExists(function ($exists) use ($anggotaId, $siklusId): void {
                            $exists->selectRaw('1')
                                ->from('jadwal_cicilan_pinjaman')
                                ->join('pinjaman', 'pinjaman.id', '=', 'jadwal_cicilan_pinjaman.pinjaman_id')
                                ->whereColumn('jadwal_cicilan_pinjaman.id', 'pemakaian_potong_gaji.source_id')
                                ->where('pinjaman.anggota_id', $anggotaId)
                                ->where('pinjaman.siklus_keanggotaan_id', $siklusId);
                        });
                });
            })
            ->exists();
    }

    private function hasUnpaidOldPinjaman(int $anggotaId, int $siklusId): bool
    {
        return Pinjaman::query()
            ->where('anggota_id', $anggotaId)
            ->where('status', Pinjaman::STATUS_AKTIF)
            ->whereRaw('CAST(sisa_pinjaman AS DECIMAL(15,2)) > 0')
            ->where(function ($query) use ($siklusId): void {
                $query->where('siklus_keanggotaan_id', $siklusId)
                    ->orWhereNull('siklus_keanggotaan_id');
            })
            ->exists();
    }

    private function applyOffsetToSource(PenyelesaianKeanggotaanDetail $detail, int $offsetCents, int $remainingCents): void
    {
        if ($detail->source_type === Pinjaman::class) {
            $pinjaman = Pinjaman::query()->with('jadwalCicilan')->lockForUpdate()->findOrFail($detail->source_id);
            $this->applyOffsetToSchedules($pinjaman, $offsetCents);
            $remainingScheduleCents = $this->remainingPinjamanScheduleCents($pinjaman);

            $pinjaman->update([
                'sisa_pinjaman' => $this->decimalFromCents($remainingScheduleCents),
                'status' => $remainingScheduleCents === 0 ? Pinjaman::STATUS_LUNAS : Pinjaman::STATUS_AKTIF,
            ]);
            return;
        }

        if ($detail->source_type === Pembayaran::class && $remainingCents === 0) {
            Pembayaran::query()->lockForUpdate()->findOrFail($detail->source_id)
                ->update(['status' => Pembayaran::STATUS_SETTLED_OFFSET]);
            return;
        }

        if ($detail->source_type === Simpanan::class && $remainingCents === 0) {
            Simpanan::query()->lockForUpdate()->findOrFail($detail->source_id)
                ->update(['status' => Simpanan::STATUS_SETTLED_OFFSET, 'settled_at' => $this->now()]);
        }
    }

    private function applyOffsetToSchedules(Pinjaman $pinjaman, int $offsetCents): void
    {
        $remaining = $offsetCents;
        $jadwals = JadwalCicilanPinjaman::query()
            ->where('pinjaman_id', $pinjaman->id)
            ->whereIn('status', [JadwalCicilanPinjaman::STATUS_SCHEDULED, JadwalCicilanPinjaman::STATUS_RESERVED])
            ->orderBy('periode')
            ->orderBy('angsuran_ke')
            ->lockForUpdate()
            ->get();

        foreach ($jadwals as $jadwal) {
            if ($remaining <= 0) {
                break;
            }

            if ($this->hasActivePayrollLedgerForJadwal($jadwal)) {
                throw ValidationException::withMessages([
                    'pinjaman' => 'Offset Pinjaman ditolak karena masih ada reservasi payroll aktif pada jadwal cicilan.',
                ]);
            }

            $currentOffset = $this->decimalToCents($jadwal->nominal_offset ?? '0.00');
            $nominal = $this->decimalToCents($jadwal->nominal_pokok);
            $sisa = $this->decimalToCents($jadwal->nominal_sisa ?? max(0, $nominal - $currentOffset));
            $applied = min($remaining, $sisa);
            $newOffset = $currentOffset + $applied;
            $newSisa = max(0, $sisa - $applied);

            $jadwal->update([
                'nominal_offset' => $this->decimalFromCents($newOffset),
                'nominal_sisa' => $this->decimalFromCents($newSisa),
                'status' => $newSisa === 0 ? JadwalCicilanPinjaman::STATUS_PAID : JadwalCicilanPinjaman::STATUS_SCHEDULED,
                'metode_penyelesaian' => JadwalCicilanPinjaman::METODE_OFFSET_SIMPANAN_POKOK,
                'paid_at' => $newSisa === 0 ? $this->now() : null,
            ]);

            $remaining -= $applied;
        }
    }

    private function hasActivePayrollLedgerForJadwal(JadwalCicilanPinjaman $jadwal): bool
    {
        return PemakaianPotongGaji::query()
            ->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN)
            ->where('source_type', JadwalCicilanPinjaman::class)
            ->where('source_id', $jadwal->id)
            ->whereIn('status', [
                PemakaianPotongGaji::STATUS_RESERVED,
                PemakaianPotongGaji::STATUS_CONSUMED,
            ])
            ->lockForUpdate()
            ->exists();
    }

    private function remainingPinjamanScheduleCents(Pinjaman $pinjaman): int
    {
        $total = JadwalCicilanPinjaman::query()
            ->where('pinjaman_id', $pinjaman->id)
            ->where('status', '!=', JadwalCicilanPinjaman::STATUS_CANCELLED)
            ->selectRaw('COALESCE(SUM(COALESCE(nominal_sisa, nominal_pokok)), 0) as total')
            ->value('total');

        return $this->decimalToCents((string) $total);
    }

    private function consumeCredits(Anggota $anggota, int $nominalCents): void
    {
        $remaining = $nominalCents;
        if ($remaining <= 0) {
            return;
        }

        $credits = KreditPotongGajiAnggota::query()
            ->where('anggota_id', $anggota->id)
            ->whereIn('status', [KreditPotongGajiAnggota::STATUS_OPEN, KreditPotongGajiAnggota::STATUS_PARTIALLY_APPLIED])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($credits as $credit) {
            if ($remaining <= 0) {
                break;
            }

            $sisa = $this->decimalToCents($credit->nominal_sisa);
            $used = min($remaining, $sisa);
            $newSisa = $sisa - $used;
            $newTerpakai = $this->decimalToCents($credit->nominal_terpakai) + $used;

            $credit->update([
                'nominal_terpakai' => $this->decimalFromCents($newTerpakai),
                'nominal_sisa' => $this->decimalFromCents($newSisa),
                'status' => $newSisa === 0
                    ? KreditPotongGajiAnggota::STATUS_APPLIED
                    : KreditPotongGajiAnggota::STATUS_PARTIALLY_APPLIED,
            ]);

            $remaining -= $used;
        }

        if ($remaining > 0) {
            throw new RuntimeException('Kredit refund tidak cukup untuk alokasi yang diminta.');
        }
    }

    private function reverseUnpaidSimpananPokok(PenyelesaianKeanggotaan $penyelesaian, ?int $userId): void
    {
        $simpananList = Simpanan::query()
            ->with(['ledger', 'jurnal'])
            ->where('anggota_id', $penyelesaian->anggota_id)
            ->where('siklus_keanggotaan_id', $penyelesaian->siklus_keanggotaan_id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->whereIn('status', [Simpanan::STATUS_PENDING_PAYROLL, Simpanan::STATUS_ALLOCATED, Simpanan::STATUS_OUTSTANDING_CASH])
            ->lockForUpdate()
            ->get();

        foreach ($simpananList as $simpanan) {
            if ($simpanan->reversal_transaksi_id) {
                continue;
            }

            $reversal = ReversalTransaksi::query()->create([
                'kode_reversal' => $this->nextCode('reversal', 'REV'),
                'source_type' => Simpanan::class,
                'source_id' => $simpanan->id,
                'jenis_reversal' => ReversalTransaksi::JENIS_SIMPANAN_POKOK_EXIT_CANCEL,
                'nominal' => $simpanan->nominal_snapshot ?? $simpanan->jumlah,
                'alasan' => 'Simpanan Pokok belum settled saat Anggota keluar.',
                'status' => ReversalTransaksi::STATUS_PROCESSED,
                'original_ledger_id' => $simpanan->pemakaian_potong_gaji_id,
                'original_jurnal_id' => $simpanan->jurnal?->id,
                'created_by' => $userId,
                'processed_by' => $userId,
                'processed_at' => $this->now(),
                'idempotency_key' => 'reversal:simpanan-pokok-exit:' . $simpanan->id,
            ]);

            if ($simpanan->ledger && in_array($simpanan->ledger->status, [PemakaianPotongGaji::STATUS_RESERVED, PemakaianPotongGaji::STATUS_CONSUMED], true)) {
                $simpanan->ledger->update([
                    'status' => PemakaianPotongGaji::STATUS_REVERSED,
                    'reversed_by' => $userId,
                    'reversed_at' => $this->now(),
                    'reversal_transaksi_id' => $reversal->id,
                    'updated_by' => $userId,
                ]);
            }

            $this->akuntansiService->recordSimpananPokokReversal($simpanan, $reversal, $userId);

            $simpanan->update([
                'status' => Simpanan::STATUS_REVERSED_DUE_TO_EXIT,
                'reversal_transaksi_id' => $reversal->id,
                'penyelesaian_keanggotaan_id' => $penyelesaian->id,
            ]);
        }
    }

    private function cancelUnpaidWajibForExit(PenyelesaianKeanggotaan $penyelesaian, ?int $userId): void
    {
        $jadwals = JadwalSimpananWajib::query()
            ->with(['simpanan.jurnal', 'simpanan.ledger', 'jenisSimpanan.akun', 'activeLedger'])
            ->where('anggota_id', $penyelesaian->anggota_id)
            ->where('siklus_keanggotaan_id', $penyelesaian->siklus_keanggotaan_id)
            ->whereIn('status', [
                JadwalSimpananWajib::STATUS_OUTSTANDING,
                JadwalSimpananWajib::STATUS_RESERVED,
                JadwalSimpananWajib::STATUS_CANCELLED_EXIT,
            ])
            ->orderBy('periode')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($jadwals as $jadwal) {
            if ($jadwal->status === JadwalSimpananWajib::STATUS_CANCELLED_EXIT) {
                $this->upsertWajibCancellationDetail($penyelesaian, $jadwal, $userId);
                continue;
            }

            $simpanan = $jadwal->simpanan;
            if (! $simpanan || $simpanan->status === Simpanan::STATUS_SETTLED) {
                continue;
            }

            if (! in_array($simpanan->status, [Simpanan::STATUS_PENDING_PAYROLL, Simpanan::STATUS_ALLOCATED], true)) {
                continue;
            }

            $ledger = $simpanan->ledger ?: $jadwal->activeLedger;
            if ($ledger && $ledger->status === PemakaianPotongGaji::STATUS_RESERVED) {
                $ledger->update([
                    'status' => PemakaianPotongGaji::STATUS_RELEASED,
                    'released_at' => $this->now(),
                    'released_by' => $userId,
                    'release_reason' => 'Keanggotaan berakhir; tagihan Simpanan Wajib dibatalkan.',
                    'updated_by' => $userId,
                ]);
            }

            if ($ledger && in_array($ledger->status, [PemakaianPotongGaji::STATUS_CONSUMED, PemakaianPotongGaji::STATUS_SETTLED], true)) {
                continue;
            }

            $reversal = ReversalTransaksi::query()
                ->where('source_type', Simpanan::class)
                ->where('source_id', $simpanan->id)
                ->lockForUpdate()
                ->first();

            if (! $reversal) {
                $reversal = ReversalTransaksi::query()->create([
                    'kode_reversal' => $this->nextCode('reversal', 'REV'),
                    'source_type' => Simpanan::class,
                    'source_id' => $simpanan->id,
                    'jenis_reversal' => ReversalTransaksi::JENIS_SIMPANAN_WAJIB_EXIT_CANCEL,
                    'nominal' => $simpanan->nominal_snapshot ?? $simpanan->jumlah,
                    'alasan' => 'Tagihan Simpanan Wajib belum dibayar saat keanggotaan berakhir.',
                    'status' => ReversalTransaksi::STATUS_PROCESSED,
                    'original_ledger_id' => $simpanan->pemakaian_potong_gaji_id,
                    'original_jurnal_id' => $simpanan->jurnal?->id,
                    'created_by' => $userId,
                    'processed_by' => $userId,
                    'processed_at' => $this->now(),
                    'idempotency_key' => 'reversal:simpanan-wajib-exit:' . $simpanan->id,
                ]);
            }

            $this->akuntansiService->recordSimpananWajibExitCancellation($simpanan->fresh('jenisSimpanan.akun'), $reversal, $userId);

            $simpanan->update([
                'status' => Simpanan::STATUS_REVERSED_DUE_TO_EXIT,
                'reversal_transaksi_id' => $reversal->id,
                'penyelesaian_keanggotaan_id' => $penyelesaian->id,
            ]);

            $jadwal->update([
                'status' => JadwalSimpananWajib::STATUS_CANCELLED_EXIT,
                'penyelesaian_keanggotaan_id' => $penyelesaian->id,
                'cancellation_reversal_id' => $reversal->id,
                'cancelled_at' => $this->now(),
                'cancelled_by' => $userId,
                'cancel_reason' => 'Tagihan Wajib dibatalkan karena Keanggotaan Berakhir.',
            ]);

            $this->upsertWajibCancellationDetail($penyelesaian, $jadwal->fresh(['jenisSimpanan.akun']), $userId);
        }
    }

    private function upsertWajibCancellationDetail(PenyelesaianKeanggotaan $penyelesaian, JadwalSimpananWajib $jadwal, ?int $userId): void
    {
        $akun = $jadwal->jenisSimpanan?->akun ?: $this->accountForRightCategory(PenyelesaianKeanggotaanDetail::KATEGORI_SIMPANAN_WAJIB);
        $nominalCents = $this->decimalToCents($jadwal->nominal_snapshot);

        PenyelesaianKeanggotaanDetail::query()->updateOrCreate(
            [
                'penyelesaian_keanggotaan_id' => $penyelesaian->id,
                'source_type' => JadwalSimpananWajib::class,
                'source_id' => $jadwal->id,
            ],
            [
                'tipe_detail' => PenyelesaianKeanggotaanDetail::TIPE_PEMBATALAN_WAJIB,
                'kategori_sumber' => PenyelesaianKeanggotaanDetail::KATEGORI_PEMBATALAN_WAJIB,
                'akun_id' => $akun->id,
                'akun_kode_snapshot' => $akun->kode_akun,
                'akun_nama_snapshot' => $akun->nama_akun,
                'nominal_dibatalkan' => $this->decimalFromCents($nominalCents),
                'nominal_hak_awal' => '0.00',
                'nominal_kewajiban_awal' => '0.00',
                'nominal_sisa' => '0.00',
                'urutan_alokasi' => 9000 + (int) $jadwal->id,
                'status' => PenyelesaianKeanggotaanDetail::STATUS_CANCELLED,
                'processed_by' => $userId,
                'processed_at' => $this->now(),
                'idempotency_key' => 'penyelesaian:pembatalan-wajib:' . $penyelesaian->id . ':' . $jadwal->id,
            ]
        );
    }

    private function freezeManasukaSaldo(PenyelesaianKeanggotaan $penyelesaian): void
    {
        SaldoSimpananManasuka::query()
            ->where('anggota_id', $penyelesaian->anggota_id)
            ->where('siklus_keanggotaan_id', $penyelesaian->siklus_keanggotaan_id)
            ->where(function ($query) use ($penyelesaian): void {
                $query->whereNull('penyelesaian_keanggotaan_id')
                    ->orWhere('penyelesaian_keanggotaan_id', $penyelesaian->id);
            })
            ->lockForUpdate()
            ->get()
            ->each(function (SaldoSimpananManasuka $saldo) use ($penyelesaian): void {
                $saldo->update([
                    'penyelesaian_keanggotaan_id' => $penyelesaian->id,
                    'frozen_at' => $saldo->frozen_at ?? $this->now(),
                ]);
            });
    }

    private function ensureZeroManasukaSaldoForCycle(Anggota $anggota, SiklusKeanggotaan $cycle): void
    {
        $jenis = JenisSimpanan::query()
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
            ->where('kategori', JenisSimpanan::KATEGORI_MANASUKA)
            ->where('aktif', true)
            ->first();

        if (! $jenis) {
            return;
        }

        try {
            SaldoSimpananManasuka::query()->firstOrCreate(
                [
                    'anggota_id' => $anggota->id,
                    'siklus_keanggotaan_id' => $cycle->id,
                    'jenis_simpanan_id' => $jenis->id,
                ],
                [
                    'saldo' => '0.00',
                    'penyelesaian_keanggotaan_id' => null,
                    'frozen_at' => null,
                ]
            );
        } catch (QueryException) {
        }
    }

    private function validRefundDompet(int $dompetId, ?string $metodeRefund = null): DompetKoperasi
    {
        $dompet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail($dompetId);

        if (! in_array($dompet->jenis_dompet, [DompetKoperasi::JENIS_KAS, DompetKoperasi::JENIS_BANK], true)) {
            throw ValidationException::withMessages(['dompet_id' => 'Dompet refund harus Kas atau Bank.']);
        }

        if ($metodeRefund === PenyelesaianKeanggotaan::METODE_TUNAI && $dompet->jenis_dompet !== DompetKoperasi::JENIS_KAS) {
            throw ValidationException::withMessages(['dompet_id' => 'Refund Tunai wajib memakai Dompet Kas.']);
        }

        if ($metodeRefund === PenyelesaianKeanggotaan::METODE_TRANSFER_BANK && $dompet->jenis_dompet !== DompetKoperasi::JENIS_BANK) {
            throw ValidationException::withMessages(['dompet_id' => 'Refund Transfer Bank wajib memakai Dompet Bank.']);
        }

        if (! $dompet->akun || ! $dompet->akun->is_aktif || $dompet->akun->kategori !== 'aset' || $dompet->akun->posisi_saldo !== 'debit') {
            throw ValidationException::withMessages(['dompet_id' => 'Dompet refund wajib memiliki COA Aset aktif dengan saldo normal Debit.']);
        }

        return $dompet;
    }

    private function resolveSimpananPokokMaster(): JenisSimpanan
    {
        $active = JenisSimpanan::query()
            ->with('akun')
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->where('aktif', true)
            ->get();

        if ($active->count() !== 1) {
            throw ValidationException::withMessages(['simpanan_pokok' => 'Harus ada tepat satu Master Jenis Simpanan Pokok aktif.']);
        }

        $jenis = $active->first();
        $akun = $jenis->akun;

        if (! $akun instanceof Akun || ! $akun->is_aktif || ! in_array($akun->kategori, ['kewajiban', 'ekuitas'], true) || $akun->posisi_saldo !== 'kredit') {
            throw ValidationException::withMessages(['simpanan_pokok' => 'Master Simpanan Pokok wajib memiliki COA aktif kategori kewajiban/ekuitas dengan saldo normal Kredit.']);
        }

        return $jenis;
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
            throw new RuntimeException('Counter nomor penyelesaian keanggotaan tidak dapat dibuat.');
        }

        $next = ((int) $counter->last_number) + 1;
        DB::table('nomor_urut_transaksi')
            ->where('id', $counter->id)
            ->update(['last_number' => $next, 'updated_at' => now()]);

        return sprintf('%s-%s-%06d', $prefix, $periode, $next);
    }

    private function normalizeDate(CarbonInterface|string $tanggal): CarbonImmutable
    {
        if ($tanggal instanceof CarbonInterface) {
            return CarbonImmutable::instance($tanggal)->setTimezone($this->timezone())->startOfDay();
        }

        return CarbonImmutable::parse((string) $tanggal, $this->timezone())->startOfDay();
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

    private function decimalToCents(int|string|null $value): int
    {
        $normalized = trim((string) ($value ?? '0'));
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
