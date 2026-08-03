<?php

namespace App\Services;

use App\Models\BatasKlaimDanaSosial;
use App\Models\DanaSosialSumber;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\KlaimDanaSosial;
use App\Models\MutasiDanaSosial;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DanaSosialService
{
    public function __construct(private readonly MutasiKasService $cash, private readonly AkuntansiService $journal, private readonly AkunResolver $accounts) {}

    public function createLimit(array $data, int $userId): BatasKlaimDanaSosial
    {
        return BatasKlaimDanaSosial::query()->create(['kategori' => $data['kategori'], 'nominal_maksimal' => $this->rupiah($data['nominal_maksimal']), 'berlaku_mulai' => $data['berlaku_mulai'], 'alasan' => trim((string) $data['alasan']), 'created_by' => $userId]);
    }

    public function createSource(array $data, int $userId): DanaSosialSumber
    {
        return DB::transaction(function () use ($data, $userId): DanaSosialSumber {
            if (($data['jenis_sumber'] ?? null) !== DanaSosialSumber::JENIS_DONASI) {
                throw ValidationException::withMessages(['jenis_sumber' => 'Sumber manual hanya boleh berupa Sumbangan/Donasi Resmi. Alokasi SHU dibentuk otomatis saat posting SHU.']);
            }
            $wallet = DompetKoperasi::query()->with('akun')->findOrFail((int) $data['dompet_id']);
            $this->assertWalletMethod($wallet, (string) $data['metode_penerimaan']);

            return DanaSosialSumber::query()->create([
                'kode_sumber' => $this->nextCode('dana_sosial_sumber', 'DSS', CarbonImmutable::parse($data['tanggal_diterima'])),
                'nama_sumber' => trim((string) $data['nama_sumber']),
                'jenis_sumber' => DanaSosialSumber::JENIS_DONASI,
                'dompet_id' => $wallet->id,
                'metode_penerimaan' => $data['metode_penerimaan'],
                'tanggal_diterima' => $data['tanggal_diterima'],
                'nomor_referensi' => trim((string) ($data['nomor_referensi'] ?? '')) ?: null,
                'bukti_penerimaan' => trim((string) ($data['bukti_penerimaan'] ?? '')) ?: null,
                'nominal_awal' => $this->rupiah($data['nominal_awal']),
                'saldo_tersedia' => 0,
                'status' => DanaSosialSumber::STATUS_DRAFT,
                'keterangan' => trim((string) $data['keterangan']),
                'created_by' => $userId,
                'idempotency_key' => (string) ($data['idempotency_key'] ?? 'dana-donation:'.sha1(json_encode($data).':'.$userId)),
            ]);
        });
    }

    public function approveSource(DanaSosialSumber $source, int $userId, string $reason): DanaSosialSumber
    {
        return DB::transaction(function () use ($source, $userId, $reason): DanaSosialSumber {
            $locked = DanaSosialSumber::query()->with('dompet.akun')->lockForUpdate()->findOrFail($source->id);
            if ($locked->status === DanaSosialSumber::STATUS_ACTIVE) return $locked;
            if ($locked->status !== DanaSosialSumber::STATUS_DRAFT || $locked->jenis_sumber !== DanaSosialSumber::JENIS_DONASI) throw ValidationException::withMessages(['status' => 'Hanya draft Donasi Resmi yang dapat disetujui manual.']);
            if ((int) $locked->created_by === $userId) throw ValidationException::withMessages(['status' => 'Maker tidak boleh menyetujui donasi buatannya sendiri. Gunakan akun Admin yang berbeda.']);
            $reason = trim($reason);
            if (mb_strlen($reason) < 5) throw ValidationException::withMessages(['approval_reason' => 'Alasan approval wajib diisi minimal 5 karakter.']);
            $this->assertWalletMethod($locked->dompet, (string) $locked->metode_penerimaan);
            $amount = (int) $locked->nominal_awal;
            $this->cash->record(['idempotency_key' => 'dana-donation:cash:'.$locked->id, 'dompet_id' => $locked->dompet_id, 'tipe' => 'masuk', 'jumlah' => $amount, 'keterangan' => 'Penerimaan Donasi Resmi '.$locked->kode_sumber, 'referensi_tipe' => DanaSosialSumber::class, 'referensi_id' => $locked->id, 'tanggal' => $locked->tanggal_diterima->toDateString()]);
            $this->journal->record(['idempotency_key' => 'dana-donation:journal:'.$locked->id, 'tanggal' => $locked->tanggal_diterima->toDateString(), 'nomor_bukti' => $locked->kode_sumber, 'keterangan' => 'Penerimaan Sumbangan/Donasi Resmi', 'referensi_tipe' => DanaSosialSumber::class, 'referensi_id' => $locked->id, 'created_by' => $userId], [$this->accounts->line($locked->dompet->akun, 'debit', $amount), $this->accounts->line($this->accounts->posting('dana_sosial.saldo'), 'kredit', $amount)]);
            MutasiDanaSosial::query()->create(['dana_sosial_sumber_id' => $locked->id, 'tipe' => 'masuk', 'nominal' => $amount, 'saldo_setelah' => $amount, 'keterangan' => 'Donasi Resmi disetujui', 'created_by' => $userId, 'idempotency_key' => 'dana-donation:fund-mutation:'.$locked->id]);
            $locked->update(['saldo_tersedia' => $amount, 'status' => DanaSosialSumber::STATUS_ACTIVE, 'approved_by' => $userId, 'approved_at' => now(), 'approval_reason' => $reason]);
            return $locked->fresh(['mutations', 'dompet', 'creator', 'approver']);
        });
    }

    public function reverseSource(DanaSosialSumber $source, string $reason, int $userId): DanaSosialSumber
    {
        return DB::transaction(function () use ($source, $reason, $userId): DanaSosialSumber {
            $locked = DanaSosialSumber::query()->with('dompet.akun')->lockForUpdate()->findOrFail($source->id);
            if ($locked->status === DanaSosialSumber::STATUS_REVERSED) return $locked;
            $hasUnresolvedClaims = $locked->claims()->whereNotIn('status', [KlaimDanaSosial::STATUS_REVERSED, KlaimDanaSosial::STATUS_DITOLAK])->exists();
            if ($locked->jenis_sumber !== DanaSosialSumber::JENIS_DONASI || $locked->status !== DanaSosialSumber::STATUS_ACTIVE || (int) $locked->saldo_tersedia !== (int) $locked->nominal_awal || $hasUnresolvedClaims) throw ValidationException::withMessages(['status' => 'Donasi hanya dapat direversal jika saldo sudah utuh dan seluruh klaim terkait telah ditolak atau direversal.']);
            $reason = trim($reason);
            if (mb_strlen($reason) < 5) throw ValidationException::withMessages(['reversal_reason' => 'Alasan reversal wajib diisi minimal 5 karakter.']);
            $amount = (int) $locked->nominal_awal;
            if ((int) $locked->dompet->saldo < $amount) throw ValidationException::withMessages(['dompet_id' => 'Saldo Dompet tidak cukup untuk reversal donasi.']);
            $date = now()->toDateString();
            $this->cash->record(['idempotency_key' => 'dana-donation:reversal:cash:'.$locked->id, 'dompet_id' => $locked->dompet_id, 'tipe' => 'keluar', 'jumlah' => $amount, 'keterangan' => 'Reversal Donasi Resmi '.$locked->kode_sumber, 'referensi_tipe' => DanaSosialSumber::class, 'referensi_id' => $locked->id, 'tanggal' => $date]);
            $journal = $this->journal->record(['idempotency_key' => 'dana-donation:reversal:journal:'.$locked->id, 'tanggal' => $date, 'nomor_bukti' => 'REV-'.$locked->kode_sumber, 'keterangan' => 'Reversal Donasi Resmi: '.$reason, 'referensi_tipe' => DanaSosialSumber::class, 'referensi_id' => $locked->id, 'created_by' => $userId], [$this->accounts->line($this->accounts->posting('dana_sosial.saldo'), 'debit', $amount), $this->accounts->line($locked->dompet->akun, 'kredit', $amount)]);
            MutasiDanaSosial::query()->create(['dana_sosial_sumber_id' => $locked->id, 'tipe' => 'keluar', 'nominal' => $amount, 'saldo_setelah' => 0, 'keterangan' => 'Reversal Donasi Resmi: '.$reason, 'created_by' => $userId, 'idempotency_key' => 'dana-donation:reversal:fund-mutation:'.$locked->id]);
            $locked->update(['saldo_tersedia' => 0, 'status' => DanaSosialSumber::STATUS_REVERSED, 'reversal_journal_id' => $journal->id, 'reversed_by' => $userId, 'reversed_at' => now(), 'reversal_reason' => $reason]);
            return $locked->fresh();
        });
    }

    public function createClaim(array $data, int $userId): KlaimDanaSosial
    {
        return DB::transaction(function () use ($data, $userId): KlaimDanaSosial {
            $employee = Karyawan::query()->with('anggota')->findOrFail((int) $data['karyawan_id']);
            $limit = BatasKlaimDanaSosial::effectiveFor((string) $data['kategori'], (string) $data['tanggal_pengajuan']);
            if (! $limit) throw ValidationException::withMessages(['kategori' => 'Belum ada batas klaim yang berlaku untuk kategori dan tanggal tersebut.']);
            $amount = $this->rupiah($data['nominal']);
            if ($amount > (int) $limit->nominal_maksimal) throw ValidationException::withMessages(['nominal' => 'Nominal klaim melebihi batas kategori yang berlaku, yaitu Rp '.number_format((float) $limit->nominal_maksimal, 0, ',', '.').'.']);
            return KlaimDanaSosial::query()->create(['kode_klaim' => $this->nextCode('klaim_dana_sosial', 'KDS', CarbonImmutable::parse($data['tanggal_pengajuan'])), 'anggota_id' => $employee->anggota?->id, 'karyawan_id' => $employee->id, 'nama_penerima_snapshot' => $employee->nama, 'kategori' => $data['kategori'], 'batas_klaim_id' => $limit->id, 'batas_nominal_snapshot' => $limit->nominal_maksimal, 'batas_berlaku_snapshot' => $limit->berlaku_mulai, 'nominal' => $amount, 'tanggal_pengajuan' => $data['tanggal_pengajuan'], 'keterangan' => trim((string) $data['keterangan']), 'status' => KlaimDanaSosial::STATUS_DRAFT, 'created_by' => $userId, 'idempotency_key' => (string) ($data['idempotency_key'] ?? 'dana-claim:'.sha1(json_encode($data).':'.$userId))]);
        });
    }

    public function submit(KlaimDanaSosial $claim): void { $this->transition($claim, KlaimDanaSosial::STATUS_DRAFT, ['status' => KlaimDanaSosial::STATUS_DIAJUKAN, 'submitted_at' => now()]); }

    public function approve(KlaimDanaSosial $claim, int $sourceId, int $userId, string $reason): void
    {
        DB::transaction(function () use ($claim, $sourceId, $userId, $reason): void {
            $locked = KlaimDanaSosial::query()->lockForUpdate()->findOrFail($claim->id);
            $source = DanaSosialSumber::query()->lockForUpdate()->findOrFail($sourceId);
            if ((int) $locked->created_by === $userId) throw ValidationException::withMessages(['status' => 'Maker tidak boleh menyetujui klaim buatannya sendiri. Gunakan akun Admin yang berbeda.']);
            if ($locked->status !== KlaimDanaSosial::STATUS_DIAJUKAN || $source->status !== DanaSosialSumber::STATUS_ACTIVE) throw ValidationException::withMessages(['status' => 'Klaim harus diajukan dan sumber harus aktif.']);
            if ((int) $locked->nominal > (int) $locked->batas_nominal_snapshot) throw ValidationException::withMessages(['nominal' => 'Nominal klaim melebihi snapshot batas kategori.']);
            $reason = trim($reason);
            if (mb_strlen($reason) < 5) throw ValidationException::withMessages(['approval_reason' => 'Alasan approval wajib diisi minimal 5 karakter.']);
            $reserved = (int) KlaimDanaSosial::query()->where('sumber_dana_sosial_id', $source->id)->where('status', KlaimDanaSosial::STATUS_DISETUJUI)->sum('nominal');
            if ($reserved + (int) $locked->nominal > (int) $source->saldo_tersedia) throw ValidationException::withMessages(['sumber_dana_sosial_id' => 'Saldo sumber setelah reservasi klaim approved lain tidak mencukupi.']);
            $locked->update(['status' => KlaimDanaSosial::STATUS_DISETUJUI, 'sumber_dana_sosial_id' => $source->id, 'approved_by' => $userId, 'approved_at' => now(), 'approval_reason' => $reason]);
        });
    }

    public function reject(KlaimDanaSosial $claim, string $reason, int $userId): void { $this->transition($claim, KlaimDanaSosial::STATUS_DIAJUKAN, ['status' => KlaimDanaSosial::STATUS_DITOLAK, 'approved_by' => $userId, 'rejected_at' => now(), 'alasan_penolakan' => trim($reason)]); }

    public function pay(KlaimDanaSosial $claim, array $data, int $userId): void
    {
        DB::transaction(function () use ($claim, $data, $userId): void {
            $locked = KlaimDanaSosial::query()->lockForUpdate()->findOrFail($claim->id);
            if ($locked->status === KlaimDanaSosial::STATUS_PAID) return;
            if ($locked->status !== KlaimDanaSosial::STATUS_DISETUJUI) throw ValidationException::withMessages(['status' => 'Hanya klaim disetujui yang dapat dibayar.']);
            $source = DanaSosialSumber::query()->lockForUpdate()->findOrFail($locked->sumber_dana_sosial_id);
            $wallet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail((int) $data['dompet_id']);
            $this->assertWalletMethod($wallet, (string) $data['metode_pembayaran']);
            $amount = (int) $locked->nominal;
            if ($amount > (int) $locked->batas_nominal_snapshot) throw ValidationException::withMessages(['nominal' => 'Nominal pembayaran melampaui snapshot batas kategori.']);
            if ((int) $wallet->saldo < $amount || (int) $source->saldo_tersedia < $amount) throw ValidationException::withMessages(['nominal' => 'Saldo kas/bank atau Dana Sosial tidak mencukupi.']);
            $balance = (int) $source->saldo_tersedia - $amount;
            if ($balance < 0) throw new RuntimeException('Proteksi saldo Dana Sosial negatif aktif.');
            $date = CarbonImmutable::parse($data['tanggal_bayar'])->toDateString();
            $this->cash->record(['idempotency_key' => 'dana-claim:cash:'.$locked->id, 'dompet_id' => $wallet->id, 'tipe' => 'keluar', 'jumlah' => $amount, 'keterangan' => 'Pembayaran Klaim Dana Sosial '.$locked->kode_klaim, 'referensi_tipe' => KlaimDanaSosial::class, 'referensi_id' => $locked->id, 'tanggal' => $date]);
            $this->journal->record(['idempotency_key' => 'dana-claim:journal:'.$locked->id, 'tanggal' => $date, 'nomor_bukti' => $locked->kode_klaim, 'keterangan' => 'Pembayaran Klaim Dana Sosial', 'referensi_tipe' => KlaimDanaSosial::class, 'referensi_id' => $locked->id, 'created_by' => $userId], [$this->accounts->line($this->accounts->posting('dana_sosial.saldo'), 'debit', $amount), $this->accounts->line($wallet->akun, 'kredit', $amount)]);
            MutasiDanaSosial::query()->create(['dana_sosial_sumber_id' => $source->id, 'klaim_dana_sosial_id' => $locked->id, 'tipe' => 'keluar', 'nominal' => $amount, 'saldo_setelah' => $balance, 'keterangan' => 'Pembayaran '.$locked->kode_klaim, 'created_by' => $userId, 'idempotency_key' => 'dana-claim:fund-mutation:'.$locked->id]);
            $source->update(['saldo_tersedia' => $balance, 'status' => $balance === 0 ? DanaSosialSumber::STATUS_CLOSED : DanaSosialSumber::STATUS_ACTIVE]);
            $locked->update(['status' => KlaimDanaSosial::STATUS_PAID, 'dompet_id' => $wallet->id, 'metode_pembayaran' => $data['metode_pembayaran'], 'paid_by' => $userId, 'paid_at' => now()]);
        });
    }

    public function reversePayment(KlaimDanaSosial $claim, string $reason, int $userId): void
    {
        DB::transaction(function () use ($claim, $reason, $userId): void {
            $locked = KlaimDanaSosial::query()->lockForUpdate()->findOrFail($claim->id);
            if ($locked->status === KlaimDanaSosial::STATUS_REVERSED) return;
            if ($locked->status !== KlaimDanaSosial::STATUS_PAID) throw ValidationException::withMessages(['status' => 'Hanya pembayaran final yang dapat direversal.']);
            $reason = trim($reason);
            if (mb_strlen($reason) < 5) throw ValidationException::withMessages(['reversal_reason' => 'Alasan reversal wajib diisi minimal 5 karakter.']);
            $source = DanaSosialSumber::query()->lockForUpdate()->findOrFail($locked->sumber_dana_sosial_id);
            $wallet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail($locked->dompet_id);
            $amount = (int) $locked->nominal; $balance = (int) $source->saldo_tersedia + $amount; $date = now()->toDateString();
            $this->cash->record(['idempotency_key' => 'dana-claim:reversal:cash:'.$locked->id, 'dompet_id' => $wallet->id, 'tipe' => 'masuk', 'jumlah' => $amount, 'keterangan' => 'Reversal Klaim '.$locked->kode_klaim, 'referensi_tipe' => KlaimDanaSosial::class, 'referensi_id' => $locked->id, 'tanggal' => $date]);
            $this->journal->record(['idempotency_key' => 'dana-claim:reversal:journal:'.$locked->id, 'tanggal' => $date, 'nomor_bukti' => 'REV-'.$locked->kode_klaim, 'keterangan' => 'Reversal pembayaran Klaim: '.$reason, 'referensi_tipe' => KlaimDanaSosial::class, 'referensi_id' => $locked->id, 'created_by' => $userId], [$this->accounts->line($wallet->akun, 'debit', $amount), $this->accounts->line($this->accounts->posting('dana_sosial.saldo'), 'kredit', $amount)]);
            MutasiDanaSosial::query()->create(['dana_sosial_sumber_id' => $source->id, 'klaim_dana_sosial_id' => $locked->id, 'tipe' => 'masuk', 'nominal' => $amount, 'saldo_setelah' => $balance, 'keterangan' => 'Reversal pembayaran '.$locked->kode_klaim.': '.$reason, 'created_by' => $userId, 'idempotency_key' => 'dana-claim:reversal:fund-mutation:'.$locked->id]);
            $source->update(['saldo_tersedia' => $balance, 'status' => DanaSosialSumber::STATUS_ACTIVE]);
            $locked->update(['status' => KlaimDanaSosial::STATUS_REVERSED, 'reversed_by' => $userId, 'reversed_at' => now(), 'reversal_reason' => $reason]);
        });
    }

    private function assertWalletMethod(DompetKoperasi $wallet, string $method): void
    {
        $expected = $method === 'tunai' ? DompetKoperasi::JENIS_KAS : DompetKoperasi::JENIS_BANK;
        if (! in_array($method, ['tunai', 'transfer_bank'], true) || $wallet->jenis_dompet !== $expected || ! $wallet->akun || ! $wallet->akun->is_aktif || $wallet->akun->kategori !== 'aset' || $wallet->akun->posisi_saldo !== 'debit') throw ValidationException::withMessages(['dompet_id' => 'Kas/Bank harus sesuai metode dan mempunyai COA Aset aktif bersaldo normal Debit.']);
    }

    private function transition(KlaimDanaSosial $claim, string $from, array $update): void { DB::transaction(function () use ($claim, $from, $update): void { $locked = KlaimDanaSosial::query()->lockForUpdate()->findOrFail($claim->id); if ($locked->status !== $from) throw ValidationException::withMessages(['status' => 'Transisi status Klaim Dana Sosial tidak valid.']); $locked->update($update); }); }
    private function rupiah(mixed $value): int { return (int) preg_replace('/[^\d]/', '', explode('.', trim((string) $value))[0] ?? '0'); }
    private function nextCode(string $type, string $prefix, CarbonImmutable $date): string { $period = $date->format('Ym'); try { DB::table('nomor_urut_transaksi')->insert(['jenis' => $type, 'periode' => $period, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]); } catch (QueryException) {} $row = DB::table('nomor_urut_transaksi')->where('jenis', $type)->where('periode', $period)->lockForUpdate()->first(); if (! $row) throw new RuntimeException('Counter Dana Sosial tidak tersedia.'); $next = (int) $row->last_number + 1; DB::table('nomor_urut_transaksi')->where('id', $row->id)->update(['last_number' => $next, 'updated_at' => now()]); return sprintf('%s-%s-%06d', $prefix, $period, $next); }
}
