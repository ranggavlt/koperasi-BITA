<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\Akun;
use App\Models\DompetKoperasi;
use App\Models\JadwalCicilanPinjaman;
use App\Models\JenisSimpanan;
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
                ->where('status', '!=', PenyelesaianKeanggotaan::STATUS_CANCELLED)
                ->lockForUpdate()
                ->first();

            if ($existing) {
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

            if ($locked->status === PenyelesaianKeanggotaan::STATUS_COMPLETED) {
                return $locked->fresh(['details.source']);
            }

            $hak = $this->calculateRights($locked);
            $obligations = $this->obligationSources($locked->anggota);

            foreach ($obligations as $index => $obligation) {
                $this->upsertDetail($locked, $obligation, $index + 1);
            }

            $currentKeys = $obligations
                ->map(fn (array $item): string => $item['source_type'] . '#' . $item['source_id'])
                ->all();
            $this->markStaleDetailsAsSettledCash($locked, $currentKeys);

            $totals = $this->detailTotals($locked);
            $totalKewajiban = $totals['awal'];
            $totalOffset = $totals['offset'];
            $sisa = $totals['sisa'];
            $refund = $sisa === 0 ? max(0, $hak['total_cents'] - $totalOffset) : 0;

            $locked->update([
                'simpanan_pokok_snapshot' => $this->decimalFromCents($hak['simpanan_pokok_cents']),
                'kredit_refund_snapshot' => $this->decimalFromCents($hak['kredit_refund_cents']),
                'total_hak_anggota' => $this->decimalFromCents($hak['total_cents']),
                'total_kewajiban_awal' => $this->decimalFromCents($totalKewajiban),
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

            if ($this->decimalToCents($locked->total_offset) > 0) {
                return $locked->fresh(['details.source']);
            }

            $this->reverseUnpaidSimpananPokok($locked, $userId);
            $locked = $this->refreshSnapshot($locked);
            $hak = $this->calculateRights($locked);
            $available = $hak['total_cents'];
            $totalOffset = 0;
            $pinjamanOffset = 0;
            $piutangAnggotaOffset = 0;

            foreach ($locked->details()->whereIn('status', [
                PenyelesaianKeanggotaanDetail::STATUS_OPEN,
                PenyelesaianKeanggotaanDetail::STATUS_PARTIAL,
            ])->lockForUpdate()->get() as $detail) {
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
                ]);

                $this->applyOffsetToSource($detail->fresh('source'), $offset, $remaining);

                if ($detail->kategori_sumber === PenyelesaianKeanggotaanDetail::KATEGORI_PINJAMAN) {
                    $pinjamanOffset += $offset;
                } else {
                    $piutangAnggotaOffset += $offset;
                }

                $available -= $offset;
                $totalOffset += $offset;
            }

            $simpananUsed = min($totalOffset, $hak['simpanan_pokok_cents']);
            $creditUsed = max(0, $totalOffset - $simpananUsed);
            $this->consumeCredits($locked->anggota, $creditUsed);

            $totals = $this->detailTotals($locked);
            $totalKewajiban = $totals['awal'];
            $totalOffset = $totals['offset'];
            $sisa = $totals['sisa'];
            $refund = $sisa === 0 ? max(0, $hak['total_cents'] - $totalOffset) : 0;

            $locked->update([
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

            $this->akuntansiService->recordPenyelesaianKeanggotaanOffset(
                $locked,
                $this->decimalFromCents($simpananUsed),
                $this->decimalFromCents($creditUsed),
                $this->decimalFromCents($pinjamanOffset),
                $this->decimalFromCents($piutangAnggotaOffset),
                $userId
            );

            return $locked->fresh(['details.source', 'jurnal.details']);
        });
    }

    public function processRefund(PenyelesaianKeanggotaan $penyelesaian, DompetKoperasi $dompet, ?int $userId): PenyelesaianKeanggotaan
    {
        return DB::transaction(function () use ($penyelesaian, $dompet, $userId): PenyelesaianKeanggotaan {
            $locked = PenyelesaianKeanggotaan::query()
                ->with(['anggota', 'mutasiKas'])
                ->lockForUpdate()
                ->findOrFail($penyelesaian->id);

            if ($locked->status === PenyelesaianKeanggotaan::STATUS_COMPLETED) {
                throw ValidationException::withMessages(['penyelesaian' => 'Penyelesaian completed tidak dapat direfund ulang.']);
            }

            if ($this->decimalToCents($locked->sisa_kewajiban) > 0) {
                throw ValidationException::withMessages(['penyelesaian' => 'Refund hanya dapat diproses setelah seluruh kewajiban nol.']);
            }

            $refund = $this->decimalToCents($locked->total_refund);
            if ($refund <= 0) {
                return $locked->fresh(['mutasiKas', 'jurnal.details']);
            }

            $lockedDompet = $this->validRefundDompet($dompet->id);
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

            $rights = $this->calculateRights($locked, includeSnapshots: true);
            $offset = $this->decimalToCents($locked->total_offset);
            $simpananUsed = min($offset, $rights['simpanan_pokok_cents']);
            $simpananRefund = min($refund, max(0, $rights['simpanan_pokok_cents'] - $simpananUsed));
            $creditRefund = max(0, $refund - $simpananRefund);
            $this->consumeCredits($locked->anggota, $creditRefund);

            $this->akuntansiService->recordPenyelesaianKeanggotaanRefund(
                $locked,
                $lockedDompet->akun,
                $this->decimalFromCents($simpananRefund),
                $this->decimalFromCents($creditRefund),
                $userId
            );

            $locked->update([
                'dompet_refund_id' => $lockedDompet->id,
                'metode_refund' => $lockedDompet->jenis_dompet === DompetKoperasi::JENIS_BANK
                    ? PenyelesaianKeanggotaan::METODE_TRANSFER_BANK
                    : PenyelesaianKeanggotaan::METODE_TUNAI,
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

            if ($this->decimalToCents($locked->sisa_kewajiban) > 0) {
                throw ValidationException::withMessages(['penyelesaian' => 'Penyelesaian belum dapat completed karena masih ada kewajiban.']);
            }

            if ($this->decimalToCents($locked->total_refund) > 0 && ! $locked->mutasiKas()->exists()) {
                throw ValidationException::withMessages(['penyelesaian' => 'Refund wajib diproses sebelum penyelesaian completed.']);
            }

            $locked->update([
                'status' => PenyelesaianKeanggotaan::STATUS_COMPLETED,
                'completed_by' => $userId,
                'completed_at' => $this->now(),
            ]);

            return $locked->fresh(['details.source', 'mutasiKas', 'jurnal.details']);
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

            if ($latestClosed && $latestClosed->status === SiklusKeanggotaan::STATUS_CLOSED) {
                $settlement = PenyelesaianKeanggotaan::query()
                    ->where('siklus_keanggotaan_id', $latestClosed->id)
                    ->where('status', '!=', PenyelesaianKeanggotaan::STATUS_CANCELLED)
                    ->first();

                if (! $settlement || $settlement->status !== PenyelesaianKeanggotaan::STATUS_COMPLETED) {
                    throw ValidationException::withMessages([
                        'penyelesaian' => 'Reaktivasi Anggota ditolak sampai penyelesaian keanggotaan sebelumnya completed.',
                    ]);
                }
            }

            $locked->update([
                'status' => Anggota::STATUS_AKTIF,
                'tanggal_nonaktif' => null,
            ]);

            $cycle = $this->ensureActiveCycle($locked, $userId, $tanggalMulai ?? now());
            $this->createSimpananPokokForCycle($locked->fresh('karyawan'), $cycle, $userId);

            return $locked->fresh(['karyawan', 'siklusAktif', 'simpanan']);
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
     * @return array{simpanan_pokok_cents:int,kredit_refund_cents:int,total_cents:int}
     */
    private function calculateRights(PenyelesaianKeanggotaan $penyelesaian, bool $includeSnapshots = false): array
    {
        if ($includeSnapshots) {
            $simpanan = $this->decimalToCents($penyelesaian->simpanan_pokok_snapshot);
            $credit = $this->decimalToCents($penyelesaian->kredit_refund_snapshot);

            return [
                'simpanan_pokok_cents' => $simpanan,
                'kredit_refund_cents' => $credit,
                'total_cents' => $simpanan + $credit,
            ];
        }

        $simpanan = Simpanan::query()
            ->where('anggota_id', $penyelesaian->anggota_id)
            ->where('siklus_keanggotaan_id', $penyelesaian->siklus_keanggotaan_id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->whereIn('status', [Simpanan::STATUS_SETTLED, Simpanan::STATUS_SETTLED_CASH, Simpanan::STATUS_SETTLED_OFFSET])
            ->sum('nominal_snapshot');

        $credit = KreditPotongGajiAnggota::query()
            ->where('anggota_id', $penyelesaian->anggota_id)
            ->whereIn('status', [KreditPotongGajiAnggota::STATUS_OPEN, KreditPotongGajiAnggota::STATUS_PARTIALLY_APPLIED])
            ->sum('nominal_sisa');

        $simpananCents = $this->decimalToCents((string) $simpanan);
        $creditCents = $this->decimalToCents((string) $credit);

        return [
            'simpanan_pokok_cents' => $simpananCents,
            'kredit_refund_cents' => $creditCents,
            'total_cents' => $simpananCents + $creditCents,
        ];
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

    private function upsertDetail(PenyelesaianKeanggotaan $penyelesaian, array $obligation, int $order): void
    {
        PenyelesaianKeanggotaanDetail::query()->updateOrCreate(
            [
                'penyelesaian_keanggotaan_id' => $penyelesaian->id,
                'source_type' => $obligation['source_type'],
                'source_id' => $obligation['source_id'],
            ],
            [
                'kategori_sumber' => $obligation['kategori'],
                'nominal_kewajiban_awal' => $this->decimalFromCents($obligation['nominal_cents']),
                'nominal_sisa' => $this->decimalFromCents($obligation['nominal_cents']),
                'urutan_alokasi' => $order,
                'status' => PenyelesaianKeanggotaanDetail::STATUS_OPEN,
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
     * @return array{awal:int,offset:int,cash:int,sisa:int}
     */
    private function detailTotals(PenyelesaianKeanggotaan $penyelesaian): array
    {
        $rows = PenyelesaianKeanggotaanDetail::query()
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->get(['nominal_kewajiban_awal', 'nominal_offset', 'nominal_dibayar_tunai', 'nominal_sisa']);

        return [
            'awal' => $rows->sum(fn (PenyelesaianKeanggotaanDetail $detail): int => $this->decimalToCents($detail->nominal_kewajiban_awal)),
            'offset' => $rows->sum(fn (PenyelesaianKeanggotaanDetail $detail): int => $this->decimalToCents($detail->nominal_offset)),
            'cash' => $rows->sum(fn (PenyelesaianKeanggotaanDetail $detail): int => $this->decimalToCents($detail->nominal_dibayar_tunai)),
            'sisa' => $rows->sum(fn (PenyelesaianKeanggotaanDetail $detail): int => $this->decimalToCents($detail->nominal_sisa)),
        ];
    }

    private function applyOffsetToSource(PenyelesaianKeanggotaanDetail $detail, int $offsetCents, int $remainingCents): void
    {
        if ($detail->source_type === Pinjaman::class) {
            $pinjaman = Pinjaman::query()->with('jadwalCicilan')->lockForUpdate()->findOrFail($detail->source_id);
            $pinjaman->update([
                'sisa_pinjaman' => $this->decimalFromCents(max(0, $this->decimalToCents($pinjaman->sisa_pinjaman) - $offsetCents)),
                'status' => $remainingCents === 0 ? Pinjaman::STATUS_LUNAS : Pinjaman::STATUS_AKTIF,
            ]);
            $this->applyOffsetToSchedules($pinjaman, $offsetCents);
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

            $currentOffset = $this->decimalToCents($jadwal->nominal_offset ?? '0.00');
            $nominal = $this->decimalToCents($jadwal->nominal_pokok);
            $sisa = max(0, $nominal - $currentOffset);
            $applied = min($remaining, $sisa);
            $newOffset = $currentOffset + $applied;
            $newSisa = max(0, $nominal - $newOffset);

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

    private function validRefundDompet(int $dompetId): DompetKoperasi
    {
        $dompet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail($dompetId);

        if (! in_array($dompet->jenis_dompet, [DompetKoperasi::JENIS_KAS, DompetKoperasi::JENIS_BANK], true)) {
            throw ValidationException::withMessages(['dompet_id' => 'Dompet refund harus Kas atau Bank.']);
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
