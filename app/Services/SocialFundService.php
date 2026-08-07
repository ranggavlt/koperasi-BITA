<?php

namespace App\Services;

use App\Models\DanaSosialSumber;
use App\Models\DompetKoperasi;
use App\Models\JenisManfaatDanaSosial;
use App\Models\KebijakanManfaatDanaSosial;
use App\Models\KlaimDanaSosial;
use App\Models\MutasiDanaSosial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SocialFundService
{
    public function __construct(
        private readonly MutasiKasService $cash,
        private readonly AkuntansiService $accounting,
    ) {}

    public function createDonation(array $data, int $userId): DanaSosialSumber
    {
        throw ValidationException::withMessages(['sumber' => 'Sumber Dana Sosial aktif hanya berasal dari alokasi SHU yang disetujui.']);
    }

    public function approveDonation(DanaSosialSumber $source, int $userId): DanaSosialSumber
    {
        throw ValidationException::withMessages(['sumber' => 'Donasi lama hanya dapat dibaca dan tidak dapat disetujui sebagai sumber aktif.']);
    }

    public function saveBenefitPolicy(array $data, int $userId): KebijakanManfaatDanaSosial
    {
        $benefit = isset($data['jenis_manfaat_id'])
            ? JenisManfaatDanaSosial::query()->findOrFail((int) $data['jenis_manfaat_id'])
            : JenisManfaatDanaSosial::query()->where('kode', $data['kode'])->firstOrFail();
        if (! in_array($benefit->kode, JenisManfaatDanaSosial::KODE, true)) {
            throw ValidationException::withMessages(['jenis_manfaat_id' => 'Jenis manfaat tidak termasuk lima manfaat final.']);
        }

        return DB::transaction(function () use ($benefit, $data, $userId): KebijakanManfaatDanaSosial {
            $existing = KebijakanManfaatDanaSosial::query()
                ->where('jenis_manfaat_id', $benefit->id)
                ->whereDate('berlaku_mulai', $data['berlaku_mulai'])
                ->first();
            if ($existing) return $existing;

            return KebijakanManfaatDanaSosial::query()->create([
                'jenis_manfaat_id' => $benefit->id,
                'berlaku_mulai' => $data['berlaku_mulai'],
                'batas_maksimal' => (int) $data['batas_maksimal'],
                'dasar_keputusan' => trim($data['dasar_keputusan']),
                'dokumen_diperlukan' => trim((string) ($data['dokumen_diperlukan'] ?? '')) ?: null,
                'deskripsi' => trim((string) ($data['deskripsi'] ?? '')) ?: null,
                'is_active' => true, 'created_by' => $userId,
                'idempotency_key' => $data['idempotency_key'] ?? 'dana-sosial:kebijakan:' . $benefit->id . ':' . $data['berlaku_mulai'],
            ]);
        });
    }

    public function createClaim(array $data, int $userId): KlaimDanaSosial
    {
        return DB::transaction(function () use ($data, $userId): KlaimDanaSosial {
            if (! empty($data['idempotency_key'])) {
                $existing = KlaimDanaSosial::query()
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();
                if ($existing) {
                    return $existing->load(['kebijakan.jenisManfaat', 'anggota.karyawan']);
                }
            }
            $policy = $this->resolvePolicy($data);
            $amount = (int) $data['nominal_diajukan'];
            if ($amount <= 0 || $amount > (int) $policy->batas_maksimal) {
                throw ValidationException::withMessages(['nominal_diajukan' => 'Nominal pengajuan wajib positif dan tidak melebihi batas kebijakan efektif.']);
            }
            $policy->loadMissing('jenisManfaat');
            $status = ($data['status'] ?? KlaimDanaSosial::STATUS_SUBMITTED) === KlaimDanaSosial::STATUS_DRAFT
                ? KlaimDanaSosial::STATUS_DRAFT : KlaimDanaSosial::STATUS_SUBMITTED;
            $code = 'KDS-' . now()->format('Ym') . '-' . strtoupper(Str::random(6));
            $claim = KlaimDanaSosial::query()->create([
                'kode_klaim' => $code, 'anggota_id' => $data['anggota_id'] ?? null,
                'karyawan_id' => $data['karyawan_id'] ?? null,
                'penerima_manfaat' => trim($data['penerima_manfaat']),
                'nama_penerima_snapshot' => trim($data['penerima_manfaat']),
                'hubungan_penerima' => trim($data['hubungan_penerima']),
                'kategori' => $policy->jenisManfaat->kode, 'kebijakan_manfaat_id' => $policy->id,
                'tanggal_kejadian' => $data['tanggal_kejadian'], 'tanggal_pengajuan' => now()->toDateString(),
                'nominal_diajukan' => $amount, 'nominal' => $amount,
                'kode_manfaat_snapshot' => $policy->jenisManfaat->kode,
                'nama_manfaat_snapshot' => $policy->jenisManfaat->nama,
                'batas_nominal_snapshot' => $policy->batas_maksimal,
                'batas_berlaku_snapshot' => $policy->berlaku_mulai,
                'dasar_keputusan_snapshot' => $policy->dasar_keputusan,
                'dokumen_diperlukan_snapshot' => $policy->dokumen_diperlukan,
                'catatan' => trim((string) ($data['catatan'] ?? '')) ?: null,
                'keterangan' => trim((string) ($data['catatan'] ?? '')) ?: 'Pengajuan manfaat Dana Sosial',
                'dokumen_path' => $data['dokumen_path'] ?? null,
                'status' => $status, 'submitted_at' => $status === KlaimDanaSosial::STATUS_SUBMITTED ? now() : null,
                'created_by' => $userId,
                'idempotency_key' => $data['idempotency_key'] ?? 'dana-sosial:klaim:' . Str::uuid(),
            ]);
            return $claim->fresh(['kebijakan.jenisManfaat', 'anggota.karyawan']);
        });
    }

    public function submitClaim(KlaimDanaSosial $claim): KlaimDanaSosial
    {
        if ($claim->status !== KlaimDanaSosial::STATUS_DRAFT) return $claim;
        $claim->update(['status' => KlaimDanaSosial::STATUS_SUBMITTED, 'submitted_at' => now()]);
        return $claim->fresh();
    }

    public function approveClaim(KlaimDanaSosial $claim, int $amount, string $note, int $userId): KlaimDanaSosial
    {
        return DB::transaction(function () use ($claim, $amount, $note, $userId): KlaimDanaSosial {
            $locked = KlaimDanaSosial::query()->with('kebijakan')->lockForUpdate()->findOrFail($claim->id);
            if (in_array($locked->status, [KlaimDanaSosial::STATUS_APPROVED, KlaimDanaSosial::STATUS_WAITING_FUNDS, KlaimDanaSosial::STATUS_PAID], true)) return $locked;
            if ($locked->status !== KlaimDanaSosial::STATUS_SUBMITTED) {
                throw ValidationException::withMessages(['klaim' => 'Klaim tidak sedang diajukan.']);
            }
            if ((int) $locked->created_by === $userId) {
                throw ValidationException::withMessages(['klaim' => 'Pembuat klaim tidak boleh menyetujui klaim yang sama.']);
            }
            $limit = (int) ($locked->batas_nominal_snapshot ?? $locked->kebijakan?->batas_maksimal);
            if ($amount <= 0 || $amount > $limit || $amount > (int) $locked->nominal_diajukan) {
                throw ValidationException::withMessages(['nominal_disetujui' => 'Nominal persetujuan wajib positif, tidak melebihi pengajuan, dan tidak melebihi batas kebijakan.']);
            }
            DanaSosialSumber::query()->where('jenis', DanaSosialSumber::JENIS_SHU)->where('is_legacy', false)->lockForUpdate()->get();
            if ($this->availableBalance() < $amount) {
                throw ValidationException::withMessages(['nominal_disetujui' => 'Saldo Dana Sosial bebas reservasi tidak mencukupi.']);
            }
            $locked->update([
                'status' => KlaimDanaSosial::STATUS_APPROVED, 'nominal_disetujui' => $amount,
                'approved_by' => $userId, 'approved_at' => now(),
                'approval_reason' => trim($note), 'catatan_persetujuan' => trim($note),
            ]);
            return $locked->fresh(['approver', 'kebijakan.jenisManfaat']);
        });
    }

    public function rejectClaim(KlaimDanaSosial $claim, string $reason, int $userId): KlaimDanaSosial
    {
        return DB::transaction(function () use ($claim, $reason, $userId): KlaimDanaSosial {
            $locked = KlaimDanaSosial::query()->lockForUpdate()->findOrFail($claim->id);
            if ($locked->status === KlaimDanaSosial::STATUS_REJECTED) return $locked;
            if ($locked->status !== KlaimDanaSosial::STATUS_SUBMITTED || (int) $locked->created_by === $userId || mb_strlen(trim($reason)) < 5) {
                throw ValidationException::withMessages(['alasan_penolakan' => 'Penolakan memerlukan checker berbeda dan alasan minimal 5 karakter.']);
            }
            $locked->update(['status' => KlaimDanaSosial::STATUS_REJECTED, 'alasan_penolakan' => trim($reason), 'rejected_at' => now()]);
            return $locked->fresh();
        });
    }

    public function payClaim(KlaimDanaSosial $claim, array $data, int $userId): KlaimDanaSosial
    {
        return DB::transaction(function () use ($claim, $data, $userId): KlaimDanaSosial {
            $locked = KlaimDanaSosial::query()->with('allocations')->lockForUpdate()->findOrFail($claim->id);
            if ($locked->status === KlaimDanaSosial::STATUS_PAID) return $locked;
            if (! in_array($locked->status, [KlaimDanaSosial::STATUS_APPROVED, KlaimDanaSosial::STATUS_WAITING_FUNDS], true)) {
                throw ValidationException::withMessages(['klaim' => 'Klaim belum disetujui untuk pencairan.']);
            }
            if ($locked->allocations->isNotEmpty()) {
                throw ValidationException::withMessages(['klaim' => 'Klaim telah memiliki histori alokasi pencairan.']);
            }
            $wallet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail((int) $data['dompet_id']);
            $this->assertWallet($wallet, $data['metode_pembayaran']);
            $amount = (int) $locked->nominal_disetujui;
            if ((int) $wallet->saldo < $amount) {
                $locked->update(['status' => KlaimDanaSosial::STATUS_WAITING_FUNDS]);
                return $locked->fresh();
            }
            $remaining = $amount;
            $sources = DanaSosialSumber::query()->where('jenis', DanaSosialSumber::JENIS_SHU)
                ->where('is_legacy', false)->where('status', DanaSosialSumber::STATUS_ACTIVE)
                ->where('saldo_tersedia', '>', 0)->orderBy('tanggal')->orderBy('id')->lockForUpdate()->get();
            foreach ($sources as $source) {
                $allocated = min((int) $source->saldo_tersedia, $remaining);
                if ($allocated <= 0) continue;
                $locked->allocations()->create(['dana_sosial_sumber_id' => $source->id, 'jumlah' => $allocated]);
                $newBalance = (int) $source->saldo_tersedia - $allocated;
                $source->update(['saldo_tersedia' => $newBalance]);
                MutasiDanaSosial::query()->create([
                    'dana_sosial_sumber_id' => $source->id, 'klaim_dana_sosial_id' => $locked->id,
                    'tipe' => 'keluar', 'nominal' => $allocated, 'saldo_setelah' => $newBalance,
                    'keterangan' => 'Pencairan ' . $locked->kode_klaim, 'created_by' => $userId,
                    'idempotency_key' => 'dana-sosial:klaim:mutasi-dana:' . $locked->id . ':' . $source->id,
                ]);
                $remaining -= $allocated;
                if ($remaining === 0) break;
            }
            if ($remaining > 0) {
                throw ValidationException::withMessages(['klaim' => 'Saldo sumber Dana Sosial tidak mencukupi setelah reservasi.']);
            }
            $locked->update([
                'status' => KlaimDanaSosial::STATUS_PAID, 'dompet_id' => $wallet->id,
                'metode_pembayaran' => $data['metode_pembayaran'], 'tanggal_bayar' => $data['tanggal_bayar'],
                'nomor_referensi' => $data['nomor_referensi'] ?? null,
                'catatan_pencairan' => trim((string) ($data['catatan_pencairan'] ?? '')) ?: null,
                'paid_by' => $userId, 'paid_at' => now(),
                'payout_idempotency_key' => $data['idempotency_key'] ?? 'dana-sosial:klaim:pencairan:' . $locked->id,
            ]);
            $this->cash->record([
                'idempotency_key' => 'dana-sosial:klaim:mutasi-kas:' . $locked->id,
                'dompet_id' => $wallet->id, 'tipe' => 'keluar', 'jumlah' => $amount,
                'keterangan' => 'Pembayaran klaim Dana Sosial ' . $locked->kode_klaim,
                'referensi_tipe' => KlaimDanaSosial::class, 'referensi_id' => $locked->id, 'tanggal' => $data['tanggal_bayar'],
            ]);
            $this->accounting->recordSocialClaim($locked->fresh(), $userId);
            return $locked->fresh(['allocations.source', 'dompet']);
        });
    }

    public function reversePayment(KlaimDanaSosial $claim, string $date, string $reason, int $userId): KlaimDanaSosial
    {
        return DB::transaction(function () use ($claim, $date, $reason, $userId): KlaimDanaSosial {
            $locked = KlaimDanaSosial::query()->with(['allocations.source', 'dompet.akun'])->lockForUpdate()->findOrFail($claim->id);
            if ($locked->status === KlaimDanaSosial::STATUS_CORRECTED) return $locked;
            if ($locked->status !== KlaimDanaSosial::STATUS_PAID || mb_strlen(trim($reason)) < 5) {
                throw ValidationException::withMessages(['reversal_reason' => 'Hanya klaim Dibayar yang dapat direversal dengan alasan minimal 5 karakter.']);
            }
            foreach ($locked->allocations as $allocation) {
                $source = DanaSosialSumber::query()->lockForUpdate()->findOrFail($allocation->dana_sosial_sumber_id);
                $newBalance = (int) $source->saldo_tersedia + (int) $allocation->jumlah;
                $source->update(['saldo_tersedia' => $newBalance]);
                MutasiDanaSosial::query()->create([
                    'dana_sosial_sumber_id' => $source->id, 'klaim_dana_sosial_id' => $locked->id,
                    'tipe' => 'masuk', 'nominal' => $allocation->jumlah, 'saldo_setelah' => $newBalance,
                    'keterangan' => 'Reversal ' . $locked->kode_klaim . ': ' . trim($reason), 'created_by' => $userId,
                    'idempotency_key' => 'dana-sosial:klaim:reversal:mutasi-dana:' . $locked->id . ':' . $source->id,
                ]);
            }
            $this->cash->record([
                'idempotency_key' => 'dana-sosial:klaim:reversal:mutasi-kas:' . $locked->id,
                'dompet_id' => $locked->dompet_id, 'tipe' => 'masuk', 'jumlah' => $locked->nominal_disetujui,
                'keterangan' => 'Reversal klaim Dana Sosial ' . $locked->kode_klaim,
                'referensi_tipe' => KlaimDanaSosial::class, 'referensi_id' => $locked->id, 'tanggal' => $date,
            ]);
            $journal = $this->accounting->recordSocialClaimReversal($locked, $date, trim($reason), $userId);
            $locked->update([
                'status' => KlaimDanaSosial::STATUS_CORRECTED, 'reversed_by' => $userId,
                'reversed_at' => now(), 'reversal_reason' => trim($reason), 'reversal_journal_id' => $journal->id,
            ]);
            return $locked->fresh(['allocations.source']);
        });
    }

    public function availableBalance(): int
    {
        $sourceBalance = (int) DanaSosialSumber::query()->where('jenis', DanaSosialSumber::JENIS_SHU)
            ->where('is_legacy', false)->where('status', DanaSosialSumber::STATUS_ACTIVE)->sum('saldo_tersedia');
        $reserved = (int) KlaimDanaSosial::query()->whereIn('status', [
            KlaimDanaSosial::STATUS_APPROVED, KlaimDanaSosial::STATUS_WAITING_FUNDS,
        ])->sum('nominal_disetujui');
        return max(0, $sourceBalance - $reserved);
    }

    private function resolvePolicy(array $data): KebijakanManfaatDanaSosial
    {
        if (! empty($data['kebijakan_manfaat_id'])) {
            $policy = KebijakanManfaatDanaSosial::query()->with('jenisManfaat')->findOrFail((int) $data['kebijakan_manfaat_id']);
            if ($policy->berlaku_mulai->toDateString() > $data['tanggal_kejadian']) {
                throw ValidationException::withMessages(['tanggal_kejadian' => 'Kebijakan belum berlaku pada tanggal kejadian.']);
            }
            return $policy;
        }
        $benefitId = (int) ($data['jenis_manfaat_id'] ?? 0);
        $policy = KebijakanManfaatDanaSosial::effectiveFor($benefitId, $data['tanggal_kejadian']);
        if (! $policy) throw ValidationException::withMessages(['jenis_manfaat_id' => 'Belum ada kebijakan manfaat yang berlaku pada tanggal kejadian.']);
        return $policy;
    }

    private function assertWallet(DompetKoperasi $wallet, string $method): void
    {
        $expected = $method === 'tunai' ? DompetKoperasi::JENIS_KAS : DompetKoperasi::JENIS_BANK;
        if ($wallet->jenis_dompet !== $expected || ! $wallet->akun) {
            throw ValidationException::withMessages(['dompet_id' => 'Dompet tidak sesuai metode pembayaran.']);
        }
    }
}
