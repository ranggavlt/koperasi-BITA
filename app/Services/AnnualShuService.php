<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\DanaSosialSumber;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\PembayaranShu;
use App\Models\PengurusKoperasi;
use App\Models\Penjualan;
use App\Models\PeriodeAkuntansi;
use App\Models\ShuConfig;
use App\Models\ShuKoperasi;
use App\Models\ShuPenerima;
use App\Models\Simpanan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnualShuService
{
    private const OFFICER_GROUPS = ['pengurus', 'pengawas', 'pembina'];

    public function __construct(
        private readonly AkuntansiService $accounting,
        private readonly MutasiKasService $cash,
    ) {}

    public function applyPeriod(PeriodeAkuntansi $period, int $userId): ShuKoperasi
    {
        return $this->calculate($this->create($period, $userId), $userId);
    }

    public function create(PeriodeAkuntansi $period, int $userId): ShuKoperasi
    {
        return DB::transaction(function () use ($period, $userId): ShuKoperasi {
            $locked = PeriodeAkuntansi::query()->lockForUpdate()->findOrFail($period->id);
            if ($locked->status !== PeriodeAkuntansi::STATUS_CLOSED) {
                throw ValidationException::withMessages(['periode' => 'SHU hanya dapat dibuat dari periode yang sudah ditutup.']);
            }

            $existing = ShuKoperasi::query()->where('periode_akuntansi_id', $locked->id)->first();
            if ($existing) {
                return $existing;
            }

            $config = ShuConfig::query()
                ->whereDate('berlaku_mulai', '<=', $locked->tanggal_selesai)
                ->latest('berlaku_mulai')
                ->latest('versi')
                ->first();
            if (! $config) {
                throw ValidationException::withMessages(['periode' => 'Belum ada Pengaturan SHU yang berlaku untuk periode ini.']);
            }

            $snapshot = $config->snapshot();

            return ShuKoperasi::query()->create([
                'periode_akuntansi_id' => $locked->id,
                'shu_config_id' => $config->id,
                'config_snapshot' => $snapshot,
                'judul' => 'SHU ' . $locked->nama,
                'tanggal_mulai' => $locked->tanggal_mulai,
                'tanggal_selesai' => $locked->tanggal_selesai,
                'status' => ShuKoperasi::STATUS_DRAFT,
                'total_pendapatan' => $locked->total_pendapatan,
                'total_biaya' => $locked->total_beban,
                'shu_total' => $locked->laba_bersih,
                ...collect($snapshot)->only([
                    'persen_dana_cadangan', 'persen_shu_anggota', 'persen_pengurus',
                    'persen_pengawas', 'persen_pembina', 'persen_dana_sosial',
                    'persen_dana_pendidikan', 'persen_jasa_modal', 'persen_jasa_usaha',
                ])->all(),
                'created_by' => $userId,
                'idempotency_key' => 'shu:tahunan:' . $locked->id,
            ]);
        });
    }

    public function changePeriod(ShuKoperasi $shu, PeriodeAkuntansi $period, int $userId): ShuKoperasi
    {
        DB::transaction(function () use ($shu, $period): void {
            $locked = ShuKoperasi::query()->lockForUpdate()->findOrFail($shu->id);
            if (! in_array($locked->status, [ShuKoperasi::STATUS_DRAFT, ShuKoperasi::STATUS_CALCULATED], true)) {
                throw ValidationException::withMessages(['periode' => 'Periode tidak dapat diganti setelah pembagian diajukan.']);
            }
            $target = PeriodeAkuntansi::query()->lockForUpdate()->findOrFail($period->id);
            if ($target->status !== PeriodeAkuntansi::STATUS_CLOSED) {
                throw ValidationException::withMessages(['periode' => 'Periode pengganti harus sudah ditutup.']);
            }
            if (ShuKoperasi::query()->where('periode_akuntansi_id', $target->id)->where('id', '!=', $locked->id)->exists()) {
                throw ValidationException::withMessages(['periode' => 'Periode tersebut sudah mempunyai pembagian SHU.']);
            }

            $config = ShuConfig::query()->whereDate('berlaku_mulai', '<=', $target->tanggal_selesai)->latest('berlaku_mulai')->latest('versi')->first();
            if (! $config) {
                throw ValidationException::withMessages(['periode' => 'Belum ada Pengaturan SHU yang berlaku untuk periode ini.']);
            }
            $snapshot = $config->snapshot();
            $locked->update([
                'periode_akuntansi_id' => $target->id,
                'shu_config_id' => $config->id,
                'config_snapshot' => $snapshot,
                'judul' => 'SHU ' . $target->nama,
                'tanggal_mulai' => $target->tanggal_mulai,
                'tanggal_selesai' => $target->tanggal_selesai,
                'total_pendapatan' => $target->total_pendapatan,
                'total_biaya' => $target->total_beban,
                'shu_total' => $target->laba_bersih,
                ...collect($snapshot)->only([
                    'persen_dana_cadangan', 'persen_shu_anggota', 'persen_pengurus',
                    'persen_pengawas', 'persen_pembina', 'persen_dana_sosial',
                    'persen_dana_pendidikan', 'persen_jasa_modal', 'persen_jasa_usaha',
                ])->all(),
                'status' => ShuKoperasi::STATUS_DRAFT,
                'calculated_by' => null,
                'dihitung_pada' => null,
                'total_dibayar' => 0,
                'total_belum_dibayar' => 0,
                'idempotency_key' => 'shu:tahunan:' . $target->id,
            ]);
        });

        return $this->calculate($shu->fresh(), $userId);
    }

    public function calculate(ShuKoperasi $shu, int $userId): ShuKoperasi
    {
        return DB::transaction(function () use ($shu, $userId): ShuKoperasi {
            $locked = ShuKoperasi::query()->with('periode')->lockForUpdate()->findOrFail($shu->id);
            if (! in_array($locked->status, [ShuKoperasi::STATUS_DRAFT, ShuKoperasi::STATUS_CALCULATED], true)) {
                throw ValidationException::withMessages(['shu' => 'Hasil yang sudah diajukan tidak dapat dihitung ulang.']);
            }

            $profit = (float) $locked->periode->laba_bersih;
            if ($profit <= 0) {
                throw ValidationException::withMessages(['shu' => 'Tahun buku tidak memiliki laba positif yang dapat dibagikan.']);
            }

            $mainPools = $this->allocate($profit, collect([
                'cadangan' => (float) $locked->persen_dana_cadangan,
                'anggota' => (float) $locked->persen_shu_anggota,
                'pengurus' => (float) $locked->persen_pengurus,
                'pengawas' => (float) $locked->persen_pengawas,
                'pembina' => (float) $locked->persen_pembina,
                'sosial' => (float) $locked->persen_dana_sosial,
                'pendidikan' => (float) $locked->persen_dana_pendidikan,
            ]));

            $memberPool = (float) $mainPools['anggota'];
            $memberPools = $this->allocate($memberPool, collect([
                'modal' => (float) $locked->persen_jasa_modal,
                'usaha' => (float) $locked->persen_jasa_usaha,
            ]));

            $this->syncMemberRecipients($locked, (float) $memberPools['modal'], (float) $memberPools['usaha']);
            foreach (self::OFFICER_GROUPS as $group) {
                $this->syncOfficerRecipients($locked, $group, (float) $mainPools[$group]);
            }

            $personalTotal = round(
                $memberPool + (float) $mainPools['pengurus'] + (float) $mainPools['pengawas'] + (float) $mainPools['pembina'],
                2
            );

            $locked->update([
                'nominal_dana_cadangan' => $mainPools['cadangan'],
                'nominal_shu_anggota' => $memberPool,
                'nominal_pengurus' => $mainPools['pengurus'],
                'nominal_pengawas' => $mainPools['pengawas'],
                'nominal_pembina' => $mainPools['pembina'],
                'nominal_dana_sosial' => $mainPools['sosial'],
                'nominal_dana_pendidikan' => $mainPools['pendidikan'],
                'nominal_jasa_modal' => $memberPools['modal'],
                'nominal_jasa_usaha' => $memberPools['usaha'],
                'total_bobot_modal' => $locked->recipients()->where('jenis_penerima', 'anggota')->sum('basis_jasa_modal'),
                'total_bobot_usaha' => $locked->recipients()->where('jenis_penerima', 'anggota')->sum('basis_jasa_usaha'),
                'status' => ShuKoperasi::STATUS_CALCULATED,
                'calculated_by' => $userId,
                'dihitung_pada' => now(),
                'total_dibayar' => 0,
                'total_belum_dibayar' => $personalTotal,
            ]);

            return $locked->fresh($this->detailRelations());
        });
    }

    public function applyWeights(ShuKoperasi $shu, array $weights, int $userId): ShuKoperasi
    {
        DB::transaction(function () use ($shu, $weights): void {
            $locked = ShuKoperasi::query()->lockForUpdate()->findOrFail($shu->id);
            if (! in_array($locked->status, [ShuKoperasi::STATUS_DRAFT, ShuKoperasi::STATUS_CALCULATED], true)) {
                throw ValidationException::withMessages(['bobot' => 'Bobot tidak dapat diubah setelah SHU diajukan.']);
            }

            $recipients = ShuPenerima::query()
                ->where('shu_koperasi_id', $locked->id)
                ->whereIn('jenis_penerima', self::OFFICER_GROUPS)
                ->lockForUpdate()
                ->get();

            foreach ($recipients as $recipient) {
                $weight = array_key_exists($recipient->id, $weights)
                    ? (float) $weights[$recipient->id]
                    : (float) $recipient->bobot;
                if ($weight <= 0 || $weight > 99999) {
                    throw ValidationException::withMessages(['bobot' => 'Setiap penerima jabatan wajib mempunyai bobot lebih dari 0.']);
                }
                $recipient->update(['bobot' => $weight]);
            }
        });

        return $this->calculate($shu, $userId);
    }

    public function resetWeights(ShuKoperasi $shu, int $userId): ShuKoperasi
    {
        DB::transaction(function () use ($shu): void {
            $locked = ShuKoperasi::query()->lockForUpdate()->findOrFail($shu->id);
            if (! in_array($locked->status, [ShuKoperasi::STATUS_DRAFT, ShuKoperasi::STATUS_CALCULATED], true)) {
                throw ValidationException::withMessages(['bobot' => 'Bobot tidak dapat diubah setelah SHU diajukan.']);
            }
            $locked->recipients()->whereIn('jenis_penerima', self::OFFICER_GROUPS)->update(['bobot' => 1]);
        });

        return $this->calculate($shu, $userId);
    }

    public function submit(ShuKoperasi $shu, int $userId): ShuKoperasi
    {
        return DB::transaction(function () use ($shu, $userId): ShuKoperasi {
            $locked = ShuKoperasi::query()->lockForUpdate()->findOrFail($shu->id);
            if ($locked->status !== ShuKoperasi::STATUS_CALCULATED) {
                throw ValidationException::withMessages(['shu' => 'Terapkan pembagian sebelum mengajukan persetujuan.']);
            }
            $this->assertRecipientTotals($locked);
            $locked->update([
                'status' => ShuKoperasi::STATUS_SUBMITTED,
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function approve(ShuKoperasi $shu, int $userId): ShuKoperasi
    {
        return DB::transaction(function () use ($shu, $userId): ShuKoperasi {
            $locked = ShuKoperasi::query()->with('periode')->lockForUpdate()->findOrFail($shu->id);
            if ($locked->status !== ShuKoperasi::STATUS_SUBMITTED) {
                throw ValidationException::withMessages(['shu' => 'SHU belum menunggu persetujuan.']);
            }
            if (in_array($userId, array_map('intval', array_filter([$locked->created_by, $locked->calculated_by, $locked->submitted_by])), true)) {
                throw ValidationException::withMessages(['shu' => 'Pembuat/perhitungan SHU tidak boleh menyetujui record yang sama. Gunakan akun Admin berbeda.']);
            }
            $this->assertRecipientTotals($locked);

            $hasPayments = (float) $locked->recipients()->sum('nominal_hak') > 0;
            $locked->update([
                'status' => $hasPayments ? ShuKoperasi::STATUS_READY_TO_PAY : ShuKoperasi::STATUS_COMPLETED,
                'approved_by' => $userId,
                'approved_at' => now(),
                'completed_at' => $hasPayments ? null : now(),
            ]);
            $this->accounting->recordShuApproval($locked, $userId);

            if ((float) $locked->nominal_dana_sosial > 0) {
                DanaSosialSumber::query()->firstOrCreate(
                    ['shu_koperasi_id' => $locked->id],
                    [
                        'kode_sumber' => 'DS-SHU-' . str_pad((string) $locked->id, 6, '0', STR_PAD_LEFT),
                        'jenis' => DanaSosialSumber::JENIS_SHU,
                        'jumlah' => $locked->nominal_dana_sosial,
                        'saldo_tersedia' => $locked->nominal_dana_sosial,
                        'tanggal' => $locked->approved_at->toDateString(),
                        'keterangan' => 'Alokasi Dana Sosial dari ' . $locked->judul,
                        'created_by' => $locked->created_by,
                        'approved_by' => $userId,
                        'approved_at' => now(),
                        'status' => 'approved',
                        'idempotency_key' => 'dana-sosial:shu:' . $locked->id,
                    ]
                );
            }

            return $locked->fresh(['socialFund']);
        });
    }

    public function pay(ShuPenerima $recipient, array $data, int $userId): PembayaranShu
    {
        return DB::transaction(function () use ($recipient, $data, $userId): PembayaranShu {
            $locked = ShuPenerima::query()->with('shu')->lockForUpdate()->findOrFail($recipient->id);
            if (! in_array($locked->shu->status, [ShuKoperasi::STATUS_READY_TO_PAY, ShuKoperasi::STATUS_APPROVED], true)) {
                throw ValidationException::withMessages(['shu' => 'SHU belum siap dibayar.']);
            }
            if ($locked->status_pembayaran === ShuPenerima::DIBAYAR || $locked->pembayaran()->exists()) {
                throw ValidationException::withMessages(['shu' => 'Penerima ini sudah dibayar.']);
            }

            $wallet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail((int) $data['dompet_id']);
            $this->assertWallet($wallet, $data['metode']);
            $amount = (float) $locked->nominal_hak;
            if ($amount <= 0) {
                throw ValidationException::withMessages(['shu' => 'Penerima ini tidak mempunyai nominal yang perlu dibayar.']);
            }
            if ((float) $wallet->saldo < $amount) {
                throw ValidationException::withMessages(['dompet_id' => 'Saldo Dompet tidak cukup.']);
            }

            $payment = PembayaranShu::query()->create([
                'shu_penerima_id' => $locked->id,
                'dompet_id' => $wallet->id,
                'metode' => $data['metode'],
                'jumlah' => $amount,
                'tanggal_bayar' => $data['tanggal_bayar'],
                'nomor_referensi' => $data['nomor_referensi'] ?? null,
                'created_by' => $userId,
                'idempotency_key' => 'shu:pembayaran:' . $locked->id,
            ]);
            $this->cash->record([
                'idempotency_key' => 'shu:pembayaran:mutasi:' . $payment->id,
                'dompet_id' => $wallet->id,
                'tipe' => 'keluar',
                'jumlah' => $amount,
                'keterangan' => 'Pembayaran SHU ' . $locked->nama_snapshot,
                'referensi_tipe' => PembayaranShu::class,
                'referensi_id' => $payment->id,
                'tanggal' => $data['tanggal_bayar'],
            ]);
            $this->accounting->recordShuPayment($payment, $userId);
            $locked->update(['status_pembayaran' => ShuPenerima::DIBAYAR]);
            $this->refreshPaymentStatus($locked->shu);

            return $payment->fresh(['penerima', 'dompet']);
        });
    }

    private function syncMemberRecipients(ShuKoperasi $shu, float $capitalPool, float $businessPool): void
    {
        $members = Anggota::query()
            ->with('karyawan')
            ->where('status', Anggota::STATUS_AKTIF)
            ->whereDate('tanggal_bergabung', '<=', $shu->tanggal_selesai)
            ->whereHas('karyawan', fn ($query) => $query->where('status_kerja', Karyawan::STATUS_AKTIF))
            ->get();
        $employeeIds = $members->pluck('karyawan_id');
        $capital = Simpanan::query()
            ->whereBetween('tanggal', [$shu->tanggal_mulai, $shu->tanggal_selesai])
            ->whereIn('karyawan_id', $employeeIds)
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', Simpanan::STATUS_REVERSED))
            ->groupBy('karyawan_id')
            ->selectRaw('karyawan_id, SUM(jumlah) total')
            ->pluck('total', 'karyawan_id');
        $business = Penjualan::query()
            ->whereBetween('created_at', [$shu->tanggal_mulai->copy()->startOfDay(), $shu->tanggal_selesai->copy()->endOfDay()])
            ->whereIn('karyawan_id', $employeeIds)
            ->groupBy('karyawan_id')
            ->selectRaw('karyawan_id, SUM(grand_total) total')
            ->pluck('total', 'karyawan_id');

        $eligible = $members->filter(fn ($member) => (float) ($capital[$member->karyawan_id] ?? 0) > 0 || (float) ($business[$member->karyawan_id] ?? 0) > 0)->values();
        $capitalWeights = $eligible->mapWithKeys(fn ($member) => [$member->id => (float) ($capital[$member->karyawan_id] ?? 0)]);
        $businessWeights = $eligible->mapWithKeys(fn ($member) => [$member->id => (float) ($business[$member->karyawan_id] ?? 0)]);

        if ($capitalPool > 0 && (float) $capitalWeights->sum() <= 0) {
            throw ValidationException::withMessages(['shu' => 'Jasa Modal tidak dapat dibagikan karena tidak ada basis simpanan pada periode.']);
        }
        if ($businessPool > 0 && (float) $businessWeights->sum() <= 0) {
            throw ValidationException::withMessages(['shu' => 'Jasa Usaha tidak dapat dibagikan karena tidak ada basis transaksi usaha pada periode.']);
        }

        $capitalShares = $this->allocate($capitalPool, $capitalWeights);
        $businessShares = $this->allocate($businessPool, $businessWeights);
        $eligibleIds = $eligible->pluck('id');
        $shu->recipients()->where('jenis_penerima', 'anggota')->when($eligibleIds->isNotEmpty(), fn ($query) => $query->whereNotIn('anggota_id', $eligibleIds))->delete();

        foreach ($eligible as $member) {
            $modal = $capitalShares[$member->id] ?? 0;
            $usaha = $businessShares[$member->id] ?? 0;
            ShuPenerima::query()->updateOrCreate(
                ['shu_koperasi_id' => $shu->id, 'jenis_penerima' => 'anggota', 'anggota_id' => $member->id],
                [
                    'pengurus_koperasi_id' => null,
                    'nama_snapshot' => $member->karyawan->nama,
                    'jabatan_snapshot' => 'Anggota',
                    'bobot' => 1,
                    'basis_jasa_modal' => $capitalWeights[$member->id],
                    'basis_jasa_usaha' => $businessWeights[$member->id],
                    'nominal_jasa_modal' => $modal,
                    'nominal_jasa_usaha' => $usaha,
                    'nominal_hak' => round($modal + $usaha, 2),
                    'status_pembayaran' => ShuPenerima::BELUM_DIBAYAR,
                    'formula_snapshot' => [
                        'metode' => 'proporsional_kontribusi',
                        'total_basis_modal' => $capitalWeights->sum(),
                        'total_basis_usaha' => $businessWeights->sum(),
                        'pembulatan' => 'rupiah_penuh_sisa_ke_penerima_terakhir',
                    ],
                ]
            );
        }
    }

    private function syncOfficerRecipients(ShuKoperasi $shu, string $group, float $pool): void
    {
        $officers = PengurusKoperasi::query()
            ->with('anggota.karyawan')
            ->aktif()
            ->where('kelompok', $group)
            ->whereHas('anggota', fn ($query) => $query->where('status', Anggota::STATUS_AKTIF))
            ->whereHas('anggota.karyawan', fn ($query) => $query->where('status_kerja', Karyawan::STATUS_AKTIF))
            ->orderBy('id')
            ->get();

        if ($pool > 0 && $officers->isEmpty()) {
            throw ValidationException::withMessages(['shu' => 'Pool ' . ucfirst($group) . ' belum mempunyai penerima aktif yang dapat diaudit.']);
        }

        $officerIds = $officers->pluck('id');
        $stale = $shu->recipients()->where('jenis_penerima', $group);
        if ($officerIds->isNotEmpty()) {
            $stale->whereNotIn('pengurus_koperasi_id', $officerIds);
        }
        $stale->delete();

        if ($pool <= 0) {
            return;
        }

        $existingWeights = $shu->recipients()->where('jenis_penerima', $group)->pluck('bobot', 'pengurus_koperasi_id');
        foreach ($officers as $officer) {
            ShuPenerima::query()->updateOrCreate(
                ['shu_koperasi_id' => $shu->id, 'pengurus_koperasi_id' => $officer->id],
                [
                    'jenis_penerima' => $group,
                    'anggota_id' => $officer->anggota_id,
                    'nama_snapshot' => $officer->anggota->karyawan->nama,
                    'jabatan_snapshot' => $officer->jabatan,
                    'bobot' => (float) ($existingWeights[$officer->id] ?? 1),
                    'basis_jasa_modal' => 0,
                    'basis_jasa_usaha' => 0,
                    'nominal_jasa_modal' => 0,
                    'nominal_jasa_usaha' => 0,
                    'nominal_hak' => 0,
                    'status_pembayaran' => ShuPenerima::BELUM_DIBAYAR,
                ]
            );
        }

        $recipients = $shu->recipients()->where('jenis_penerima', $group)->orderBy('id')->get();
        $weights = $recipients->mapWithKeys(fn ($recipient) => [$recipient->id => (float) $recipient->bobot]);
        $shares = $this->allocate($pool, $weights);
        foreach ($recipients as $recipient) {
            $recipient->update([
                'nominal_hak' => $shares[$recipient->id],
                'formula_snapshot' => [
                    'metode' => 'bobot_keputusan_rat',
                    'bobot_penerima' => (float) $recipient->bobot,
                    'total_bobot_kelompok' => (float) $weights->sum(),
                    'pool_kelompok' => $pool,
                    'pembulatan' => 'rupiah_penuh_sisa_ke_penerima_terakhir',
                ],
            ]);
        }
    }

    private function assertRecipientTotals(ShuKoperasi $shu): void
    {
        $expected = [
            'anggota' => (float) $shu->nominal_shu_anggota,
            'pengurus' => (float) $shu->nominal_pengurus,
            'pengawas' => (float) $shu->nominal_pengawas,
            'pembina' => (float) $shu->nominal_pembina,
        ];
        foreach ($expected as $group => $pool) {
            $recipients = $shu->recipients()->where('jenis_penerima', $group);
            if ($pool > 0 && ! $recipients->exists()) {
                throw ValidationException::withMessages(['shu' => 'Pool ' . ucfirst($group) . ' belum mempunyai penerima.']);
            }
            if (abs((float) $recipients->sum('nominal_hak') - $pool) > 0.01) {
                throw ValidationException::withMessages(['shu' => 'Total penerima ' . ucfirst($group) . ' tidak sama dengan pool kelompok. Terapkan pembagian ulang.']);
            }
        }
    }

    private function refreshPaymentStatus(ShuKoperasi $shu): void
    {
        $total = (float) $shu->recipients()->sum('nominal_hak');
        $paid = (float) $shu->recipients()->where('status_pembayaran', ShuPenerima::DIBAYAR)->sum('nominal_hak');
        $pending = max(0, round($total - $paid, 2));
        $shu->update([
            'total_dibayar' => $paid,
            'total_belum_dibayar' => $pending,
            'status' => $pending <= 0 ? ShuKoperasi::STATUS_COMPLETED : ShuKoperasi::STATUS_READY_TO_PAY,
            'completed_at' => $pending <= 0 ? now() : null,
        ]);
    }

    private function allocate(float $total, Collection $weights): Collection
    {
        $result = $weights->map(fn () => 0.0);
        $eligible = $weights->filter(fn ($value) => (float) $value > 0);
        $sum = (float) $eligible->sum();
        if ($total <= 0 || $sum <= 0) {
            return $result;
        }

        $allocated = 0.0;
        $last = $eligible->keys()->last();
        foreach ($eligible as $key => $weight) {
            $share = $key === $last ? round($total - $allocated) : round($total * (float) $weight / $sum);
            if ($key !== $last) {
                $allocated += $share;
            }
            $result[$key] = $share;
        }

        return $result;
    }

    private function assertWallet(DompetKoperasi $wallet, string $method): void
    {
        $expected = $method === 'tunai' ? DompetKoperasi::JENIS_KAS : DompetKoperasi::JENIS_BANK;
        if ($wallet->jenis_dompet !== $expected || ! $wallet->akun) {
            throw ValidationException::withMessages(['dompet_id' => 'Dompet tidak sesuai metode pembayaran.']);
        }
    }

    private function detailRelations(): array
    {
        return ['recipients.anggota.karyawan', 'recipients.pengurus', 'periode', 'config'];
    }
}
