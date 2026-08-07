<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\DanaSosialSumber;
use App\Models\DompetKoperasi;
use App\Models\JenisSimpanan;
use App\Models\Pembayaran;
use App\Models\PembayaranShu;
use App\Models\Penjualan;
use App\Models\PeriodeAkuntansi;
use App\Models\ShuAlokasi;
use App\Models\ShuConfig;
use App\Models\ShuKoperasi;
use App\Models\ShuPenerima;
use App\Models\Simpanan;
use App\Models\StrukturKoperasi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnualShuService
{
    private const OFFICER_GROUPS = ['pengurus', 'pengawas', 'pembina'];
    private const SETTLED_SAVING_STATUSES = [
        Simpanan::STATUS_SETTLED, Simpanan::STATUS_SETTLED_CASH, Simpanan::STATUS_SETTLED_OFFSET,
    ];
    private const FINAL_PAYMENT_STATUSES = [
        Pembayaran::STATUS_PAID, Pembayaran::STATUS_SETTLED_CASH, Pembayaran::STATUS_SETTLED_OFFSET,
    ];

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
            if ($existing) return $existing;

            $config = ShuConfig::query()->whereDate('berlaku_mulai', '<=', $locked->tanggal_selesai)
                ->latest('berlaku_mulai')->latest('versi')->first();
            if (! $config) {
                throw ValidationException::withMessages(['periode' => 'Belum ada Pengaturan SHU yang berlaku untuk periode ini.']);
            }
            $this->assertFinalConfig($config);
            if ((int) $locked->laba_bersih <= 0) {
                throw ValidationException::withMessages(['periode' => 'Periode tidak memiliki laba positif yang dapat dibagikan.']);
            }
            $snapshot = $config->snapshot();

            return ShuKoperasi::query()->create([
                'periode_akuntansi_id' => $locked->id, 'shu_config_id' => $config->id,
                'config_snapshot' => $snapshot, 'judul' => 'SHU ' . $locked->nama,
                'tanggal_mulai' => $locked->tanggal_mulai, 'tanggal_selesai' => $locked->tanggal_selesai,
                'status' => ShuKoperasi::STATUS_DRAFT, 'total_pendapatan' => $locked->total_pendapatan,
                'total_biaya' => $locked->total_beban, 'shu_total' => $locked->laba_bersih,
                ...collect($snapshot)->only([
                    'persen_dana_cadangan', 'persen_shu_anggota', 'persen_pengurus',
                    'persen_pengawas', 'persen_pembina', 'persen_dana_sosial',
                    'persen_dana_pendidikan', 'persen_jasa_modal', 'persen_jasa_usaha',
                ])->all(),
                'created_by' => $userId, 'idempotency_key' => 'shu:tahunan:' . $locked->id,
            ]);
        });
    }

    public function calculate(ShuKoperasi $shu, int $userId): ShuKoperasi
    {
        return DB::transaction(function () use ($shu, $userId): ShuKoperasi {
            $locked = ShuKoperasi::query()->with(['periode', 'config'])->lockForUpdate()->findOrFail($shu->id);
            if ($locked->status === ShuKoperasi::STATUS_APPROVED) {
                throw ValidationException::withMessages(['shu' => 'SHU yang sudah disetujui tidak dapat dihitung ulang.']);
            }
            $this->assertFinalConfig($locked->config);
            $profit = (int) $locked->periode->laba_bersih;
            $mainPools = $this->allocate($profit, collect([
                'cadangan' => (float) $locked->persen_dana_cadangan,
                'anggota' => (float) $locked->persen_shu_anggota,
                'pengurus' => (float) $locked->persen_pengurus,
                'pengawas' => (float) $locked->persen_pengawas,
                'pembina' => (float) $locked->persen_pembina,
                'sosial' => (float) $locked->persen_dana_sosial,
            ]));
            $memberPools = $this->allocate((int) $mainPools['anggota'], collect([
                'modal' => (float) $locked->persen_jasa_modal,
                'usaha' => (float) $locked->persen_jasa_usaha,
            ]));

            $this->syncMemberRecipients($locked, (int) $memberPools['modal'], (int) $memberPools['usaha']);
            foreach (self::OFFICER_GROUPS as $group) {
                $this->syncOfficerRecipients($locked, $group, (int) $mainPools[$group]);
            }
            $personal = (int) $mainPools['anggota'] + (int) $mainPools['pengurus']
                + (int) $mainPools['pengawas'] + (int) $mainPools['pembina'];
            $memberRecipients = $locked->recipients()->where('jenis_penerima', 'anggota')->where('diikutkan', true);

            $locked->update([
                'nominal_dana_cadangan' => $mainPools['cadangan'],
                'nominal_shu_anggota' => $mainPools['anggota'],
                'nominal_pengurus' => $mainPools['pengurus'],
                'nominal_pengawas' => $mainPools['pengawas'],
                'nominal_pembina' => $mainPools['pembina'],
                'nominal_dana_sosial' => $mainPools['sosial'],
                'nominal_dana_pendidikan' => 0,
                'nominal_jasa_modal' => $memberPools['modal'],
                'nominal_jasa_usaha' => $memberPools['usaha'],
                'total_bobot_modal' => $memberRecipients->sum('basis_jasa_modal'),
                'total_bobot_usaha' => $memberRecipients->sum('basis_jasa_usaha'),
                'status' => ShuKoperasi::STATUS_DRAFT,
                'calculated_by' => $userId, 'dihitung_pada' => now(), 'calculated_at' => now(),
                'submitted_by' => null, 'submitted_at' => null,
                'total_dibayar' => 0, 'total_belum_dibayar' => $personal,
                'source_snapshot' => [
                    'periode_checksum' => $locked->periode->checksum,
                    'membership_cutoff' => $locked->tanggal_selesai->toDateString(),
                    'saving_statuses' => self::SETTLED_SAVING_STATUSES,
                    'sale_status' => Penjualan::STATUS_COMPLETED,
                    'payment_statuses' => self::FINAL_PAYMENT_STATUSES,
                    'rounding' => 'largest_remainder_rupiah_stable_id',
                ],
            ]);

            return $locked->fresh($this->detailRelations());
        });
    }

    public function setEligibility(ShuPenerima $recipient, bool $included, string $reason, int $userId): ShuKoperasi
    {
        $shu = DB::transaction(function () use ($recipient, $included, $reason, $userId): ShuKoperasi {
            $locked = ShuPenerima::query()->with('shu')->lockForUpdate()->findOrFail($recipient->id);
            if ($locked->jenis_penerima !== 'anggota' || $locked->shu->status === ShuKoperasi::STATUS_APPROVED) {
                throw ValidationException::withMessages(['anggota' => 'Keikutsertaan hanya dapat diubah untuk anggota sebelum persetujuan.']);
            }
            if (mb_strlen(trim($reason)) < 5) {
                throw ValidationException::withMessages(['alasan_eligibility' => 'Alasan perubahan keikutsertaan wajib diisi minimal 5 karakter.']);
            }
            $locked->update([
                'diikutkan' => $included, 'alasan_eligibility' => trim($reason),
                'eligibility_set_by' => $userId, 'eligibility_set_at' => now(),
            ]);
            return $locked->shu;
        });

        return $this->calculate($shu, $userId);
    }

    public function setFinalRight(ShuPenerima $recipient, int $amount, string $reason, ?string $detail, int $userId): ShuPenerima
    {
        return DB::transaction(function () use ($recipient, $amount, $reason, $detail, $userId): ShuPenerima {
            $locked = ShuPenerima::query()->with('shu')->lockForUpdate()->findOrFail($recipient->id);
            if ($locked->shu->status === ShuKoperasi::STATUS_APPROVED || ! $locked->diikutkan) {
                throw ValidationException::withMessages(['hak_final' => 'Hak final tidak dapat diubah untuk penerima ini.']);
            }
            if ($amount < 0 || ! in_array($reason, ShuPenerima::ALASAN_HAK_FINAL, true)) {
                throw ValidationException::withMessages(['hak_final' => 'Nominal atau alasan Hak Final tidak valid.']);
            }
            if ($reason === 'lainnya' && mb_strlen(trim((string) $detail)) < 5) {
                throw ValidationException::withMessages(['detail_alasan_hak_final' => 'Detail alasan lainnya wajib diisi minimal 5 karakter.']);
            }
            $locked->update([
                'hak_final' => $amount, 'nominal_hak' => $amount,
                'alasan_hak_final' => $reason, 'detail_alasan_hak_final' => trim((string) $detail) ?: null,
                'hak_final_ditetapkan_by' => $userId, 'hak_final_ditetapkan_at' => now(),
            ]);
            $locked->shu->update([
                'status' => ShuKoperasi::STATUS_DRAFT, 'calculated_by' => $userId,
                'calculated_at' => now(), 'submitted_by' => null, 'submitted_at' => null,
            ]);
            return $locked->fresh();
        });
    }

    public function prepareApproval(ShuKoperasi $shu, int $userId): ShuKoperasi
    {
        return DB::transaction(function () use ($shu, $userId): ShuKoperasi {
            $locked = ShuKoperasi::query()->lockForUpdate()->findOrFail($shu->id);
            if ($locked->status === ShuKoperasi::STATUS_APPROVED) return $locked;
            $this->assertRecipientTotals($locked);
            $locked->update([
                'status' => ShuKoperasi::STATUS_READY,
                'submitted_by' => $userId, 'submitted_at' => now(),
            ]);
            return $locked->fresh($this->detailRelations());
        });
    }

    public function submit(ShuKoperasi $shu, int $userId): ShuKoperasi
    {
        return $this->prepareApproval($shu, $userId);
    }

    public function approve(ShuKoperasi $shu, int $userId): ShuKoperasi
    {
        return DB::transaction(function () use ($shu, $userId): ShuKoperasi {
            $locked = ShuKoperasi::query()->with(['periode', 'recipients'])->lockForUpdate()->findOrFail($shu->id);
            if ($locked->status === ShuKoperasi::STATUS_APPROVED) return $locked;
            if ($locked->status !== ShuKoperasi::STATUS_READY) {
                throw ValidationException::withMessages(['shu' => 'SHU belum berstatus Siap Disetujui.']);
            }
            if (in_array($userId, array_map('intval', array_filter([
                $locked->created_by, $locked->calculated_by, $locked->submitted_by,
            ])), true)) {
                throw ValidationException::withMessages(['shu' => 'Pembuat, penghitung, atau penyunting SHU tidak boleh menjadi penyetuju record yang sama.']);
            }
            $this->assertRecipientTotals($locked);
            $locked->update(['status' => ShuKoperasi::STATUS_APPROVED, 'approved_by' => $userId, 'approved_at' => now()]);
            $journal = $this->accounting->recordShuApproval($locked->fresh('recipients'), $userId);
            $locked->updateQuietly(['allocation_journal_id' => $journal?->id, 'posted_by' => $userId, 'posted_at' => now()]);

            foreach ([ShuAlokasi::DANA_CADANGAN => (int) $locked->nominal_dana_cadangan, ShuAlokasi::DANA_SOSIAL => (int) $locked->nominal_dana_sosial] as $type => $amount) {
                if ($amount > 0 && $journal) {
                    ShuAlokasi::query()->firstOrCreate(['shu_koperasi_id' => $locked->id, 'jenis' => $type], [
                        'nominal' => $amount, 'jurnal_id' => $journal->id, 'created_by' => $userId,
                        'idempotency_key' => 'shu:alokasi:' . $type . ':' . $locked->id,
                    ]);
                }
            }
            if ((int) $locked->nominal_dana_sosial > 0) {
                DanaSosialSumber::query()->firstOrCreate(['shu_koperasi_id' => $locked->id], [
                    'kode_sumber' => 'DS-SHU-' . str_pad((string) $locked->id, 6, '0', STR_PAD_LEFT),
                    'jenis' => DanaSosialSumber::JENIS_SHU, 'periode_akuntansi_id' => $locked->periode_akuntansi_id,
                    'shu_config_id' => $locked->shu_config_id, 'allocation_journal_id' => $journal?->id,
                    'jumlah' => $locked->nominal_dana_sosial, 'saldo_tersedia' => $locked->nominal_dana_sosial,
                    'tanggal' => $locked->approved_at->toDateString(), 'keterangan' => 'Alokasi Dana Sosial dari ' . $locked->judul,
                    'created_by' => $locked->created_by, 'approved_by' => $userId, 'approved_at' => now(),
                    'status' => DanaSosialSumber::STATUS_ACTIVE, 'is_legacy' => false,
                    'idempotency_key' => 'dana-sosial:shu:' . $locked->id,
                ]);
            }
            return $locked->fresh(['socialFund', 'allocations', ...$this->detailRelations()]);
        });
    }

    public function pay(ShuPenerima $recipient, array $data, int $userId): PembayaranShu
    {
        return DB::transaction(function () use ($recipient, $data, $userId): PembayaranShu {
            $locked = ShuPenerima::query()->with(['shu', 'pembayaran'])->lockForUpdate()->findOrFail($recipient->id);
            if ($locked->pembayaran?->status === PembayaranShu::STATUS_PAID) return $locked->pembayaran;
            if ($locked->shu->status !== ShuKoperasi::STATUS_APPROVED || ! $locked->diikutkan) {
                throw ValidationException::withMessages(['shu' => 'SHU belum disetujui atau penerima tidak diikutkan.']);
            }
            if ($locked->pembayaran()->exists()) {
                throw ValidationException::withMessages(['shu' => 'Penerima telah memiliki histori pembayaran.']);
            }
            $wallet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail((int) $data['dompet_id']);
            $this->assertWallet($wallet, $data['metode']);
            $amount = $locked->finalRight();
            if ($amount <= 0 || (int) $wallet->saldo < $amount) {
                throw ValidationException::withMessages(['dompet_id' => 'Hak Final atau saldo Dompet tidak mencukupi.']);
            }
            $payment = PembayaranShu::query()->create([
                'shu_penerima_id' => $locked->id, 'dompet_id' => $wallet->id,
                'metode' => $data['metode'], 'jumlah' => $amount, 'tanggal_bayar' => $data['tanggal_bayar'],
                'nomor_referensi' => $data['nomor_referensi'] ?? null, 'catatan' => $data['catatan'] ?? null,
                'status' => PembayaranShu::STATUS_PAID, 'created_by' => $userId,
                'idempotency_key' => $data['idempotency_key'] ?? 'shu:pembayaran:' . $locked->id,
            ]);
            $mutation = $this->cash->record([
                'idempotency_key' => 'shu:pembayaran:mutasi:' . $payment->id, 'dompet_id' => $wallet->id,
                'tipe' => 'keluar', 'jumlah' => $amount, 'keterangan' => 'Pembayaran SHU ' . $locked->nama_snapshot,
                'referensi_tipe' => PembayaranShu::class, 'referensi_id' => $payment->id, 'tanggal' => $data['tanggal_bayar'],
            ]);
            $journal = $this->accounting->recordShuPayment($payment, $userId);
            $payment->updateQuietly(['mutasi_kas_id' => $mutation->id, 'jurnal_id' => $journal->id]);
            $locked->update(['status_pembayaran' => ShuPenerima::DIBAYAR]);
            $this->refreshPaymentTotals($locked->shu);
            return $payment->fresh(['penerima', 'dompet', 'mutasi', 'jurnalPembayaran']);
        });
    }

    public function reversePayment(PembayaranShu $payment, string $date, string $reason, int $userId): PembayaranShu
    {
        return DB::transaction(function () use ($payment, $date, $reason, $userId): PembayaranShu {
            $locked = PembayaranShu::query()->with(['penerima.shu', 'dompet.akun'])->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === PembayaranShu::STATUS_REVERSED) return $locked;
            if (mb_strlen(trim($reason)) < 5) {
                throw ValidationException::withMessages(['reversal_reason' => 'Alasan reversal wajib diisi minimal 5 karakter.']);
            }
            $mutation = $this->cash->record([
                'idempotency_key' => 'shu:pembayaran:reversal:mutasi:' . $locked->id,
                'dompet_id' => $locked->dompet_id, 'tipe' => 'masuk', 'jumlah' => $locked->jumlah,
                'keterangan' => 'Reversal pembayaran SHU: ' . trim($reason),
                'referensi_tipe' => PembayaranShu::class, 'referensi_id' => $locked->id, 'tanggal' => $date,
            ]);
            $journal = $this->accounting->recordShuPaymentReversal($locked, $date, trim($reason), $userId);
            $locked->update([
                'status' => PembayaranShu::STATUS_REVERSED, 'reversal_mutasi_kas_id' => $mutation->id,
                'reversal_jurnal_id' => $journal->id, 'reversed_by' => $userId,
                'reversed_at' => now(), 'reversal_reason' => trim($reason),
            ]);
            $locked->penerima->update(['status_pembayaran' => ShuPenerima::DIREVERSAL]);
            $this->refreshPaymentTotals($locked->penerima->shu);
            return $locked->fresh();
        });
    }

    public function changePeriod(ShuKoperasi $shu, PeriodeAkuntansi $period, int $userId): ShuKoperasi
    {
        throw ValidationException::withMessages(['periode' => 'Periode sumber SHU tidak dapat diganti. Buat SHU dari periode tertutup yang benar.']);
    }

    public function applyWeights(ShuKoperasi $shu, array $weights, int $userId): ShuKoperasi
    {
        throw ValidationException::withMessages(['bobot' => 'Bobot manual telah diganti oleh Hak Final dengan alasan audit.']);
    }

    public function resetWeights(ShuKoperasi $shu, int $userId): ShuKoperasi
    {
        return $this->calculate($shu, $userId);
    }

    private function syncMemberRecipients(ShuKoperasi $shu, int $capitalPool, int $businessPool): void
    {
        $end = $shu->tanggal_selesai->toDateString();
        $start = $shu->tanggal_mulai->toDateString();
        $members = Anggota::query()->with(['karyawan', 'siklusKeanggotaan'])->whereDate('tanggal_bergabung', '<=', $end)
            ->orderBy('id')->get()->filter(function (Anggota $member) use ($end): bool {
                if ($member->siklusKeanggotaan->isNotEmpty()) {
                    return $member->siklusKeanggotaan->contains(fn ($cycle) => $cycle->tanggal_mulai->toDateString() <= $end
                        && ($cycle->tanggal_selesai === null || $cycle->tanggal_selesai->toDateString() >= $end));
                }
                return $member->tanggal_nonaktif === null || $member->tanggal_nonaktif->toDateString() > $end;
            })->values();
        $old = $shu->recipients()->where('jenis_penerima', 'anggota')->get()->keyBy('anggota_id');
        $memberIds = $members->pluck('id');
        $shu->recipients()->where('jenis_penerima', 'anggota')->when($memberIds->isNotEmpty(), fn ($q) => $q->whereNotIn('anggota_id', $memberIds))->delete();

        $rows = collect();
        foreach ($members as $member) {
            $cycleIds = $member->siklusKeanggotaan->filter(fn ($cycle) => $cycle->tanggal_mulai->toDateString() <= $end
                && ($cycle->tanggal_selesai === null || $cycle->tanggal_selesai->toDateString() >= $end))->pluck('id');
            $savings = Simpanan::query()->where(fn ($q) => $q->where('anggota_id', $member->id)->orWhere('karyawan_id', $member->karyawan_id))
                ->whereDate('tanggal', '<=', $end)->whereIn('status', self::SETTLED_SAVING_STATUSES)
                ->when($cycleIds->isNotEmpty(), fn ($q) => $q->whereIn('siklus_keanggotaan_id', $cycleIds))
                ->whereIn('kode_jenis_snapshot', [JenisSimpanan::KODE_SIMPANAN_WAJIB, JenisSimpanan::KODE_SIMPANAN_MANASUKA])
                ->get();
            $wajib = (int) $savings->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
                ->where('jenis_transaksi', Simpanan::JENIS_SETORAN)->sum('jumlah');
            $manasuka = (int) $savings->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
                ->sum(fn ($saving) => $saving->jenis_transaksi === Simpanan::JENIS_PENARIKAN ? -(int) $saving->jumlah : (int) $saving->jumlah);
            $sales = (int) Penjualan::query()->where(fn ($q) => $q->where('anggota_id', $member->id)->orWhere('karyawan_id', $member->karyawan_id))
                ->where('tipe_pelanggan', Penjualan::TIPE_ANGGOTA)->where('status', Penjualan::STATUS_COMPLETED)
                ->whereBetween('tanggal_transaksi', [$start . ' 00:00:00', $end . ' 23:59:59'])
                ->whereHas('pembayaran', fn ($q) => $q->whereIn('status', self::FINAL_PAYMENT_STATUSES))->sum('grand_total');
            $previous = $old->get($member->id);
            $rows->put($member->id, [
                'member' => $member, 'wajib' => max(0, $wajib), 'manasuka' => max(0, $manasuka),
                'modal' => max(0, $wajib + $manasuka), 'usaha' => max(0, $sales),
                'included' => $previous ? (bool) $previous->diikutkan : true,
                'reason' => $previous?->alasan_eligibility,
                'set_by' => $previous?->eligibility_set_by, 'set_at' => $previous?->eligibility_set_at,
            ]);
        }
        $capitalWeights = $rows->map(fn ($row) => $row['included'] ? $row['modal'] : 0);
        $businessWeights = $rows->map(fn ($row) => $row['included'] ? $row['usaha'] : 0);
        if ($capitalPool > 0 && $capitalWeights->sum() <= 0) {
            throw ValidationException::withMessages(['shu' => 'Jasa Modal tidak dapat dibagikan karena tidak ada Wajib/Manasuka settled dari anggota eligible.']);
        }
        if ($businessPool > 0 && $businessWeights->sum() <= 0) {
            throw ValidationException::withMessages(['shu' => 'Jasa Usaha tidak dapat dibagikan karena tidak ada penjualan anggota final pada periode.']);
        }
        $capitalShares = $this->allocate($capitalPool, $capitalWeights);
        $businessShares = $this->allocate($businessPool, $businessWeights);
        foreach ($rows as $memberId => $row) {
            $system = (int) ($capitalShares[$memberId] ?? 0) + (int) ($businessShares[$memberId] ?? 0);
            ShuPenerima::query()->updateOrCreate(
                ['shu_koperasi_id' => $shu->id, 'jenis_penerima' => 'anggota', 'anggota_id' => $memberId],
                [
                    'pengurus_koperasi_id' => null, 'struktur_koperasi_id' => null,
                    'nama_snapshot' => $row['member']->karyawan?->nama ?? 'Anggota ' . $memberId,
                    'jabatan_snapshot' => 'Anggota', 'kelompok_snapshot' => 'anggota',
                    'nomor_anggota_snapshot' => $row['member']->nomor_anggota, 'bobot' => 1,
                    'simpanan_wajib_dihitung' => $row['wajib'], 'simpanan_manasuka_dihitung' => $row['manasuka'],
                    'basis_jasa_modal' => $row['modal'], 'basis_jasa_usaha' => $row['usaha'],
                    'nominal_jasa_modal' => $capitalShares[$memberId] ?? 0,
                    'nominal_jasa_usaha' => $businessShares[$memberId] ?? 0,
                    'hitungan_sistem' => $system, 'hak_final' => $system, 'nominal_hak' => $system,
                    'alasan_hak_final' => null, 'detail_alasan_hak_final' => null,
                    'eligible' => true, 'diikutkan' => $row['included'], 'alasan_eligibility' => $row['reason'],
                    'eligibility_set_by' => $row['set_by'], 'eligibility_set_at' => $row['set_at'],
                    'status_pembayaran' => ShuPenerima::BELUM_DIBAYAR,
                    'formula_snapshot' => ['modal' => 'Wajib settled + saldo Manasuka eligible', 'usaha' => 'penjualan anggota completed dan pembayaran final', 'rounding' => 'largest_remainder_rupiah_stable_id'],
                    'idempotency_key' => 'shu:penerima:anggota:' . $shu->id . ':' . $memberId,
                ]
            );
        }
    }

    private function syncOfficerRecipients(ShuKoperasi $shu, string $group, int $pool): void
    {
        $structures = StrukturKoperasi::query()->with('anggota.karyawan')->effectiveOn($shu->tanggal_selesai->toDateString())
            ->where('kelompok', $group)->orderBy('id')->get();
        if ($pool > 0 && $structures->isEmpty()) {
            throw ValidationException::withMessages(['shu' => 'Pool ' . ucfirst($group) . ' belum mempunyai penerima efektif pada akhir periode.']);
        }
        $ids = $structures->pluck('id');
        $shu->recipients()->where('jenis_penerima', $group)
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereNotIn('struktur_koperasi_id', $ids))->delete();
        $shares = $this->allocate($pool, $structures->mapWithKeys(fn ($row) => [$row->id => 1]));
        foreach ($structures as $structure) {
            $amount = (int) ($shares[$structure->id] ?? 0);
            ShuPenerima::query()->updateOrCreate(
                ['shu_koperasi_id' => $shu->id, 'jenis_penerima' => $group, 'struktur_koperasi_id' => $structure->id],
                [
                    'anggota_id' => $structure->anggota_id, 'pengurus_koperasi_id' => null,
                    'nama_snapshot' => $structure->nama, 'jabatan_snapshot' => $structure->jabatan,
                    'kelompok_snapshot' => $group, 'nomor_anggota_snapshot' => $structure->anggota?->nomor_anggota,
                    'bobot' => 1, 'basis_jasa_modal' => 0, 'basis_jasa_usaha' => 0,
                    'nominal_jasa_modal' => 0, 'nominal_jasa_usaha' => 0,
                    'hitungan_sistem' => $amount, 'hak_final' => $amount, 'nominal_hak' => $amount,
                    'alasan_hak_final' => null, 'detail_alasan_hak_final' => null,
                    'eligible' => true, 'diikutkan' => true, 'status_pembayaran' => ShuPenerima::BELUM_DIBAYAR,
                    'formula_snapshot' => ['metode' => 'rata_struktur_efektif', 'rounding' => 'largest_remainder_rupiah_stable_id'],
                    'idempotency_key' => 'shu:penerima:' . $group . ':' . $shu->id . ':' . $structure->id,
                ]
            );
        }
    }

    private function assertRecipientTotals(ShuKoperasi $shu): void
    {
        foreach ([
            'anggota' => (int) $shu->nominal_shu_anggota, 'pengurus' => (int) $shu->nominal_pengurus,
            'pengawas' => (int) $shu->nominal_pengawas, 'pembina' => (int) $shu->nominal_pembina,
        ] as $group => $pool) {
            $query = $shu->recipients()->where('jenis_penerima', $group)->where('diikutkan', true);
            if ($pool > 0 && ! $query->exists()) {
                throw ValidationException::withMessages(['shu' => 'Pool ' . ucfirst($group) . ' belum mempunyai penerima.']);
            }
            $sum = (int) $query->sum(DB::raw('COALESCE(hak_final, hitungan_sistem, nominal_hak)'));
            if ($sum !== $pool) {
                throw ValidationException::withMessages(['shu' => 'Total Hak Final ' . ucfirst($group) . ' sebesar Rp ' . number_format($sum, 0, ',', '.') . ' belum sama dengan pool Rp ' . number_format($pool, 0, ',', '.') . '.']);
            }
        }
    }

    private function refreshPaymentTotals(ShuKoperasi $shu): void
    {
        $total = (int) $shu->recipients()->where('diikutkan', true)->sum(DB::raw('COALESCE(hak_final, hitungan_sistem, nominal_hak)'));
        $paid = (int) $shu->recipients()->where('diikutkan', true)->where('status_pembayaran', ShuPenerima::DIBAYAR)
            ->sum(DB::raw('COALESCE(hak_final, hitungan_sistem, nominal_hak)'));
        $shu->update(['total_dibayar' => $paid, 'total_belum_dibayar' => max(0, $total - $paid)]);
    }

    private function allocate(int $total, Collection $weights): Collection
    {
        $result = $weights->map(fn () => 0);
        $eligible = $weights->filter(fn ($value) => (float) $value > 0);
        $sum = (float) $eligible->sum();
        if ($total <= 0 || $sum <= 0) return $result;
        $remainders = [];
        $allocated = 0;
        foreach ($eligible as $key => $weight) {
            $exact = $total * (float) $weight / $sum;
            $floor = (int) floor($exact);
            $result[$key] = $floor; $allocated += $floor;
            $remainders[] = ['key' => $key, 'remainder' => $exact - $floor];
        }
        usort($remainders, fn ($a, $b) => $b['remainder'] <=> $a['remainder'] ?: ((string) $a['key'] <=> (string) $b['key']));
        for ($i = 0; $i < $total - $allocated; $i++) {
            $key = $remainders[$i % count($remainders)]['key'];
            $result->put($key, (int) $result->get($key) + 1);
        }
        return $result;
    }

    private function assertFinalConfig(ShuConfig $config): void
    {
        $actual = array_map('floatval', [
            $config->persen_dana_cadangan, $config->persen_shu_anggota, $config->persen_pengurus,
            $config->persen_pengawas, $config->persen_pembina, $config->persen_dana_sosial,
        ]);
        $expected = [30.0, 40.0, 10.0, 5.0, 5.0, 10.0];
        if ($actual !== $expected || (float) $config->persen_dana_pendidikan !== 0.0) {
            throw ValidationException::withMessages(['config' => 'Konfigurasi final wajib 30% Cadangan, 40% Anggota, 10% Pengurus, 5% Pengawas, 5% Pembina, 10% Sosial, dan Pendidikan 0%.']);
        }
        if (abs((float) $config->persen_jasa_modal + (float) $config->persen_jasa_usaha - 100) > 0.0001) {
            throw ValidationException::withMessages(['config' => 'Pembagian Jasa Modal dan Jasa Usaha wajib berjumlah 100%.']);
        }
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
        return ['recipients.anggota.karyawan', 'recipients.struktur.anggota.karyawan', 'recipients.pembayaran', 'periode', 'config'];
    }
}
