<?php

namespace App\Services;

use App\Models\DanaSosialSumber;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\MutasiDanaSosial;
use App\Models\Penjualan;
use App\Models\PeriodeAkuntansi;
use App\Models\ShuAnggota;
use App\Models\ShuConfig;
use App\Models\ShuKoperasi;
use App\Models\ShuTransaksi;
use App\Models\Simpanan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ShuKoperasiService
{
    public function __construct(
        private readonly AkuntansiService $journal,
        private readonly AkunResolver $accounts,
    ) {}

    public function create(array $data, ?int $userId = null): ShuKoperasi
    {
        return DB::transaction(function () use ($data, $userId): ShuKoperasi {
            $period = PeriodeAkuntansi::query()->lockForUpdate()->findOrFail((int) ($data['periode_akuntansi_id'] ?? 0));
            if ($period->status !== PeriodeAkuntansi::STATUS_CLOSED) {
                throw ValidationException::withMessages(['periode_akuntansi_id' => 'SHU hanya dapat dihitung dari periode akuntansi yang sudah closed.']);
            }
            if (ShuKoperasi::query()->where('periode_akuntansi_id', $period->id)->exists()) {
                throw ValidationException::withMessages(['periode_akuntansi_id' => 'Periode akuntansi ini sudah memiliki proses SHU.']);
            }

            $config = ShuConfig::effectiveFor($period->tanggal_selesai);
            if (! $config) {
                throw ValidationException::withMessages(['periode_akuntansi_id' => 'Belum ada konfigurasi SHU approved yang berlaku pada akhir periode.']);
            }

            $profit = round((float) $period->laba_bersih, 2);
            $allocations = $this->topLevelAllocations(max(0, $profit), $config);
            $memberTotal = $allocations['nominal_shu_anggota'];

            $shu = ShuKoperasi::query()->create([
                'periode_akuntansi_id' => $period->id,
                'judul' => trim((string) ($data['judul'] ?? 'SHU '.$period->nama)),
                'tanggal_mulai' => $period->tanggal_mulai,
                'tanggal_selesai' => $period->tanggal_selesai,
                'keterangan' => trim((string) ($data['keterangan'] ?? '')) ?: null,
                ...$this->percentageSnapshot($config),
                'total_pendapatan' => $period->total_pendapatan,
                'total_biaya' => $period->total_beban,
                'shu_total' => $profit,
                ...$allocations,
                'nominal_jasa_modal' => $this->rupiahRound($memberTotal * (float) $config->persen_jasa_modal / 100),
                'nominal_jasa_usaha' => $memberTotal - $this->rupiahRound($memberTotal * (float) $config->persen_jasa_modal / 100),
                'dihitung_pada' => now(),
                'status' => 'calculated',
                'config_snapshot' => $this->configSnapshot($config),
                'source_snapshot' => [
                    'source' => 'closed_accounting_period',
                    'periode_akuntansi_id' => $period->id,
                    'kode' => $period->kode,
                    'tanggal_mulai' => $period->tanggal_mulai->toDateString(),
                    'tanggal_selesai' => $period->tanggal_selesai->toDateString(),
                    'total_pendapatan' => (string) $period->total_pendapatan,
                    'total_beban' => (string) $period->total_beban,
                    'laba_bersih' => (string) $period->laba_bersih,
                    'jumlah_jurnal' => $period->jumlah_jurnal,
                    'checksum' => $period->checksum,
                    'closing_journal_id' => $period->closing_journal_id,
                    'closed_at' => $period->closed_at?->toIso8601String(),
                ],
                'created_by' => $userId,
                'calculated_by' => $userId,
                'calculated_at' => now(),
                'idempotency_key' => 'shu:period:'.$period->id,
            ]);

            $this->syncMemberAllocation($shu);

            return $shu->fresh(['periodeAkuntansi', 'anggotaPembagian.karyawan']);
        });
    }

    public function approve(ShuKoperasi $shu, string $reason, int $userId): ShuKoperasi
    {
        return DB::transaction(function () use ($shu, $reason, $userId): ShuKoperasi {
            $locked = ShuKoperasi::query()->lockForUpdate()->findOrFail($shu->id);
            if ($locked->status === 'approved') {
                return $locked;
            }
            if ($locked->status !== 'calculated') {
                throw ValidationException::withMessages(['status' => 'Hanya perhitungan SHU berstatus calculated yang dapat disetujui.']);
            }
            if ((float) $locked->shu_total <= 0) {
                throw ValidationException::withMessages(['status' => 'Periode tanpa laba positif tidak dapat diproses sebagai pembagian SHU.']);
            }
            $reason = trim($reason);
            if (mb_strlen($reason) < 5) {
                throw ValidationException::withMessages(['approval_reason' => 'Dasar approval wajib diisi minimal 5 karakter.']);
            }
            $locked->update(['status' => 'approved', 'approved_by' => $userId, 'approved_at' => now(), 'approval_reason' => $reason]);

            return $locked->fresh();
        });
    }

    public function post(ShuKoperasi $shu, int $userId): ShuKoperasi
    {
        return DB::transaction(function () use ($shu, $userId): ShuKoperasi {
            $locked = ShuKoperasi::query()->with('periodeAkuntansi')->lockForUpdate()->findOrFail($shu->id);
            if ($locked->status === 'closed') {
                return $locked;
            }
            if ($locked->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'SHU wajib berstatus approved sebelum diposting.']);
            }

            $amount = $this->rupiahRound((float) $locked->shu_total);
            $creditLines = collect([
                ['key' => 'shu.dana_cadangan', 'amount' => $locked->nominal_dana_cadangan],
                ['key' => 'shu.anggota', 'amount' => $locked->nominal_shu_anggota],
                ['key' => 'shu.pengurus', 'amount' => $locked->nominal_pengurus],
                ['key' => 'shu.pengawas', 'amount' => $locked->nominal_pengawas],
                ['key' => 'shu.pembina', 'amount' => $locked->nominal_pembina],
                ['key' => 'shu.dana_sosial', 'amount' => $locked->nominal_dana_sosial],
                ['key' => 'shu.dana_pendidikan', 'amount' => $locked->nominal_dana_pendidikan],
            ])->filter(fn (array $line) => (float) $line['amount'] > 0)
                ->map(fn (array $line) => $this->accounts->line($this->accounts->posting($line['key']), 'kredit', $line['amount']))
                ->values()
                ->all();

            $journal = $this->journal->recordCorrection($locked->periodeAkuntansi, [
                'idempotency_key' => 'shu:allocation:jurnal:'.$locked->id,
                'tanggal' => now()->toDateString(),
                'nomor_bukti' => 'SHU-'.$locked->periodeAkuntansi->kode,
                'keterangan' => 'Penetapan alokasi SHU '.$locked->judul,
                'referensi_tipe' => ShuKoperasi::class,
                'referensi_id' => $locked->id,
                'created_by' => $userId,
            ], [
                $this->accounts->line($this->accounts->posting('shu.laba_belum_dibagi'), 'debit', $amount),
                ...$creditLines,
            ], 'Penetapan SHU dari periode tertutup '.$locked->periodeAkuntansi->kode);

            $this->createSocialFundSource($locked, $userId);
            $locked->update([
                'status' => 'closed',
                'allocation_journal_id' => $journal->id,
                'posted_by' => $userId,
                'posted_at' => now(),
                'closed_by' => $userId,
                'closed_at' => now(),
            ]);

            return $locked->fresh(['allocationJournal.details', 'socialFundSource']);
        });
    }

    public function reverse(ShuKoperasi $shu, string $reason, int $userId): ShuKoperasi
    {
        return DB::transaction(function () use ($shu, $reason, $userId): ShuKoperasi {
            $locked = ShuKoperasi::query()->with(['periodeAkuntansi', 'allocationJournal.details'])->lockForUpdate()->findOrFail($shu->id);
            if ($locked->status === 'reversed') {
                return $locked;
            }
            if ($locked->status !== 'closed' || ! $locked->allocationJournal) {
                throw ValidationException::withMessages(['status' => 'Hanya posting SHU final yang dapat direversal.']);
            }
            $reason = trim($reason);
            if (mb_strlen($reason) < 5) {
                throw ValidationException::withMessages(['reversal_reason' => 'Alasan reversal wajib diisi minimal 5 karakter.']);
            }
            $source = DanaSosialSumber::query()->where('shu_koperasi_id', $locked->id)->lockForUpdate()->first();
            if ($source && ((int) $source->saldo_tersedia !== (int) $source->nominal_awal || $source->claims()->exists())) {
                throw ValidationException::withMessages(['status' => 'Alokasi Dana Sosial sudah digunakan. Reverse seluruh klaim terkait terlebih dahulu.']);
            }

            $lines = $locked->allocationJournal->details->map(fn ($line) => [
                'akun_id' => $line->akun_id,
                'akun_kode' => $line->akun_kode,
                'akun_nama' => $line->akun_nama,
                'debit' => $line->kredit,
                'kredit' => $line->debit,
            ])->all();
            $reversal = $this->journal->recordCorrection($locked->periodeAkuntansi, [
                'idempotency_key' => 'shu:reversal:jurnal:'.$locked->id,
                'tanggal' => now()->toDateString(),
                'nomor_bukti' => 'REV-SHU-'.$locked->id,
                'keterangan' => 'Reversal alokasi SHU: '.$reason,
                'referensi_tipe' => ShuKoperasi::class,
                'referensi_id' => $locked->id,
                'created_by' => $userId,
            ], $lines, $reason);

            if ($source) {
                MutasiDanaSosial::query()->create([
                    'dana_sosial_sumber_id' => $source->id,
                    'tipe' => 'keluar',
                    'nominal' => $source->nominal_awal,
                    'saldo_setelah' => 0,
                    'keterangan' => 'Reversal alokasi SHU: '.$reason,
                    'created_by' => $userId,
                    'idempotency_key' => 'shu:social-source:reversal:'.$locked->id,
                ]);
                $source->update(['saldo_tersedia' => 0, 'status' => DanaSosialSumber::STATUS_REVERSED]);
            }

            $locked->update(['status' => 'reversed', 'reversal_journal_id' => $reversal->id, 'reversed_by' => $userId, 'reversed_at' => now(), 'reversal_reason' => $reason]);

            return $locked->fresh(['reversalJournal.details']);
        });
    }

    public function addTransaksi(ShuKoperasi $shuKoperasi, array $data): ShuTransaksi
    {
        throw new RuntimeException('Input laba/biaya SHU manual dinonaktifkan. Sumber laba hanya jurnal posted dari periode tertutup.');
    }

    private function topLevelAllocations(float $profit, ShuConfig $config): array
    {
        $map = [
            'nominal_dana_cadangan' => 'persen_dana_cadangan',
            'nominal_shu_anggota' => 'persen_anggota',
            'nominal_pengawas' => 'persen_pengawas',
            'nominal_pembina' => 'persen_pembina',
            'nominal_pengurus' => 'persen_pengurus',
            'nominal_dana_sosial' => 'persen_dana_sosial',
            'nominal_dana_pendidikan' => 'persen_dana_pendidikan',
        ];
        $result = [];
        foreach ($map as $nominal => $percentage) {
            $result[$nominal] = $this->rupiahRound($profit * (float) $config->{$percentage} / 100);
        }
        $result['nominal_dana_cadangan'] += $this->rupiahRound($profit) - array_sum($result);

        return $result;
    }

    private function syncMemberAllocation(ShuKoperasi $shu): void
    {
        $members = Karyawan::query()->with('anggota')->whereHas('anggota', fn ($q) => $q->where('status', 'aktif'))->orderBy('id')->get();
        $savings = Simpanan::query()->whereBetween('tanggal', [$shu->tanggal_mulai, $shu->tanggal_selesai])->select('karyawan_id', DB::raw('SUM(jumlah) total'))->groupBy('karyawan_id')->pluck('total', 'karyawan_id');
        $sales = Penjualan::query()->whereBetween('created_at', [$shu->tanggal_mulai->startOfDay(), $shu->tanggal_selesai->endOfDay()])->select('karyawan_id', DB::raw('SUM(grand_total) total'))->groupBy('karyawan_id')->pluck('total', 'karyawan_id');
        $modalWeights = $members->mapWithKeys(fn ($member) => [$member->id => (float) ($savings[$member->id] ?? 0)]);
        $usahaWeights = $members->mapWithKeys(fn ($member) => [$member->id => (float) ($sales[$member->id] ?? 0)]);
        $modal = $this->allocate((int) $shu->nominal_jasa_modal, $modalWeights);
        $usaha = $this->allocate((int) $shu->nominal_jasa_usaha, $usahaWeights);
        $now = now();
        $rows = $members->map(fn ($member) => [
            'shu_koperasi_id' => $shu->id,
            'karyawan_id' => $member->id,
            'anggota_id' => $member->anggota?->id,
            'total_simpanan' => $modalWeights[$member->id],
            'total_transaksi_usaha' => $usahaWeights[$member->id],
            'nominal_jasa_modal' => $modal[$member->id] ?? 0,
            'nominal_jasa_usaha' => $usaha[$member->id] ?? 0,
            'nominal_shu' => ($modal[$member->id] ?? 0) + ($usaha[$member->id] ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        if ($rows !== []) ShuAnggota::query()->insert($rows);
        $shu->update(['total_bobot_modal' => $modalWeights->sum(), 'total_bobot_usaha' => $usahaWeights->sum()]);
    }

    private function allocate(int $amount, Collection $weights): Collection
    {
        $result = $weights->mapWithKeys(fn ($value, $key) => [$key => 0]);
        $eligible = $weights->filter(fn ($value) => $value > 0);
        $total = (float) $eligible->sum();
        if ($amount <= 0 || $total <= 0) return $result;
        $allocated = 0;
        $last = $eligible->keys()->last();
        foreach ($eligible as $key => $weight) {
            $share = $key === $last ? $amount - $allocated : $this->rupiahRound($amount * $weight / $total);
            $result[$key] = $share;
            $allocated += $share;
        }
        return $result;
    }

    private function createSocialFundSource(ShuKoperasi $shu, int $userId): void
    {
        $amount = (int) $shu->nominal_dana_sosial;
        if ($amount <= 0 || DanaSosialSumber::query()->where('shu_koperasi_id', $shu->id)->exists()) return;
        $source = DanaSosialSumber::query()->create([
            'kode_sumber' => 'DSS-SHU-'.$shu->id,
            'nama_sumber' => 'Alokasi '.$shu->judul,
            'jenis_sumber' => DanaSosialSumber::JENIS_SHU,
            'shu_koperasi_id' => $shu->id,
            'nominal_awal' => $amount,
            'saldo_tersedia' => $amount,
            'status' => DanaSosialSumber::STATUS_ACTIVE,
            'keterangan' => 'Dibentuk otomatis saat posting alokasi SHU.',
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => now(),
            'idempotency_key' => 'shu:social-source:'.$shu->id,
        ]);
        MutasiDanaSosial::query()->create([
            'dana_sosial_sumber_id' => $source->id,
            'tipe' => 'masuk',
            'nominal' => $amount,
            'saldo_setelah' => $amount,
            'keterangan' => 'Alokasi Dana Sosial dari SHU posted',
            'created_by' => $userId,
            'idempotency_key' => 'shu:social-source:mutation:'.$shu->id,
        ]);
    }

    private function percentageSnapshot(ShuConfig $config): array
    {
        return ['persen_dana_cadangan' => $config->persen_dana_cadangan, 'persen_shu_anggota' => $config->persen_anggota, 'persen_pengawas' => $config->persen_pengawas, 'persen_pembina' => $config->persen_pembina, 'persen_pengurus' => $config->persen_pengurus, 'persen_dana_sosial' => $config->persen_dana_sosial, 'persen_dana_pendidikan' => $config->persen_dana_pendidikan, 'persen_jasa_modal' => $config->persen_jasa_modal, 'persen_jasa_usaha' => $config->persen_jasa_usaha];
    }

    private function configSnapshot(ShuConfig $config): array
    {
        return ['shu_config_id' => $config->id, 'status_persetujuan' => $config->status_persetujuan, 'berlaku_mulai' => $config->berlaku_mulai?->toDateString(), 'dasar_persetujuan' => $config->dasar_persetujuan, 'approved_by' => $config->approved_by, 'approved_at' => $config->approved_at?->toIso8601String(), 'persentase' => $this->percentageSnapshot($config)];
    }

    private function rupiahRound(float $amount): int
    {
        return (int) round($amount, 0, PHP_ROUND_HALF_UP);
    }
}
