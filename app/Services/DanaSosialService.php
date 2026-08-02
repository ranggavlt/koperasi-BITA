<?php

namespace App\Services;

use App\Models\DanaSosialSumber;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\KlaimDanaSosial;
use App\Models\MutasiDanaSosial;
use App\Models\ShuKoperasi;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DanaSosialService
{
    public function __construct(
        private readonly MutasiKasService $kas,
        private readonly AkuntansiService $jurnal,
        private readonly AkunResolver $akun
    ) {}

    public function createSource(array $data, int $userId): DanaSosialSumber
    {
        return DB::transaction(function () use ($data, $userId) {
            $jenis = (string) $data['jenis_sumber'];
            $nominal = $this->rupiah($data['nominal_awal']);
            if ($jenis === DanaSosialSumber::JENIS_SHU) {
                $shu = ShuKoperasi::query()->lockForUpdate()->findOrFail((int) ($data['shu_koperasi_id'] ?? 0));
                if ($shu->status !== 'closed' || (int) $shu->nominal_dana_sosial !== $nominal) {
                    throw ValidationException::withMessages(['shu_koperasi_id' => 'Sumber SHU wajib berasal dari periode closed dan sama dengan snapshot alokasi Dana Sosial.']);
                }
            }

            return DanaSosialSumber::query()->create([
                'kode_sumber' => $this->nextCode('dana_sosial_sumber', 'DSS', now()->toImmutable()),
                'nama_sumber' => trim((string) $data['nama_sumber']),
                'jenis_sumber' => $jenis,
                'shu_koperasi_id' => $data['shu_koperasi_id'] ?? null,
                'nominal_awal' => $nominal,
                'saldo_tersedia' => 0,
                'status' => DanaSosialSumber::STATUS_DRAFT,
                'keterangan' => trim((string) ($data['keterangan'] ?? '')) ?: null,
                'created_by' => $userId,
                'idempotency_key' => (string) ($data['idempotency_key'] ?? 'dana-source:'.sha1(json_encode($data).':'.$userId)),
            ]);
        });
    }

    public function approveSource(DanaSosialSumber $source, int $userId): DanaSosialSumber
    {
        return DB::transaction(function () use ($source, $userId) {
            $locked = DanaSosialSumber::query()->lockForUpdate()->findOrFail($source->id);
            if ($locked->status === DanaSosialSumber::STATUS_ACTIVE) return $locked;
            if ($locked->status !== DanaSosialSumber::STATUS_DRAFT) {
                throw ValidationException::withMessages(['status' => 'Hanya sumber draft yang dapat disetujui.']);
            }
            $amount = (int) $locked->nominal_awal;
            $locked->update(['saldo_tersedia' => $amount, 'status' => DanaSosialSumber::STATUS_ACTIVE, 'approved_by' => $userId, 'approved_at' => now()]);
            MutasiDanaSosial::query()->create([
                'dana_sosial_sumber_id' => $locked->id, 'tipe' => 'masuk', 'nominal' => $amount,
                'saldo_setelah' => $amount, 'keterangan' => 'Sumber Dana Sosial disetujui',
                'created_by' => $userId, 'idempotency_key' => 'dana-source:mutation:'.$locked->id,
            ]);
            $this->jurnal->record([
                'idempotency_key' => 'dana-source:jurnal:'.$locked->id, 'tanggal' => now()->toDateString(),
                'nomor_bukti' => $locked->kode_sumber, 'keterangan' => 'Alokasi sumber Dana Sosial',
                'referensi_tipe' => DanaSosialSumber::class, 'referensi_id' => $locked->id, 'created_by' => $userId,
            ], [
                $this->akun->line($this->akun->posting('dana_sosial.sumber_alokasi'), 'debit', $amount),
                $this->akun->line($this->akun->posting('dana_sosial.saldo'), 'kredit', $amount),
            ]);
            return $locked->fresh('mutations');
        });
    }

    public function createClaim(array $data, int $userId): KlaimDanaSosial
    {
        return DB::transaction(function () use ($data, $userId) {
            $employee = Karyawan::query()->with('anggota')->findOrFail((int) $data['karyawan_id']);
            return KlaimDanaSosial::query()->create([
                'kode_klaim' => $this->nextCode('klaim_dana_sosial', 'KDS', CarbonImmutable::parse($data['tanggal_pengajuan'])),
                'anggota_id' => $employee->anggota?->id, 'karyawan_id' => $employee->id,
                'nama_penerima_snapshot' => $employee->nama, 'kategori' => $data['kategori'],
                'nominal' => $this->rupiah($data['nominal']), 'tanggal_pengajuan' => $data['tanggal_pengajuan'],
                'keterangan' => trim((string) $data['keterangan']), 'status' => KlaimDanaSosial::STATUS_DRAFT,
                'created_by' => $userId,
                'idempotency_key' => (string) ($data['idempotency_key'] ?? 'dana-claim:'.sha1(json_encode($data).':'.$userId)),
            ]);
        });
    }

    public function submit(KlaimDanaSosial $claim): void
    {
        $this->transition($claim, KlaimDanaSosial::STATUS_DRAFT, ['status' => KlaimDanaSosial::STATUS_DIAJUKAN, 'submitted_at' => now()]);
    }

    public function approve(KlaimDanaSosial $claim, int $sourceId, int $userId): void
    {
        DB::transaction(function () use ($claim, $sourceId, $userId) {
            $locked = KlaimDanaSosial::query()->lockForUpdate()->findOrFail($claim->id);
            $source = DanaSosialSumber::query()->lockForUpdate()->findOrFail($sourceId);
            if ($locked->status !== KlaimDanaSosial::STATUS_DIAJUKAN || $source->status !== DanaSosialSumber::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['status' => 'Klaim harus diajukan dan sumber harus aktif.']);
            }
            $reserved = (int) KlaimDanaSosial::query()->where('sumber_dana_sosial_id', $source->id)
                ->where('status', KlaimDanaSosial::STATUS_DISETUJUI)->sum('nominal');
            if ($reserved + (int) $locked->nominal > (int) $source->saldo_tersedia) {
                throw ValidationException::withMessages(['sumber_dana_sosial_id' => 'Saldo sumber setelah memperhitungkan klaim approved lain tidak mencukupi.']);
            }
            $locked->update(['status' => KlaimDanaSosial::STATUS_DISETUJUI, 'sumber_dana_sosial_id' => $source->id, 'approved_by' => $userId, 'approved_at' => now()]);
        });
    }

    public function reject(KlaimDanaSosial $claim, string $reason, int $userId): void
    {
        $this->transition($claim, KlaimDanaSosial::STATUS_DIAJUKAN, ['status' => KlaimDanaSosial::STATUS_DITOLAK, 'approved_by' => $userId, 'rejected_at' => now(), 'alasan_penolakan' => trim($reason)]);
    }

    public function pay(KlaimDanaSosial $claim, array $data, int $userId): void
    {
        DB::transaction(function () use ($claim, $data, $userId) {
            $locked = KlaimDanaSosial::query()->lockForUpdate()->findOrFail($claim->id);
            if ($locked->status === KlaimDanaSosial::STATUS_PAID) return;
            if ($locked->status !== KlaimDanaSosial::STATUS_DISETUJUI) throw ValidationException::withMessages(['status' => 'Hanya klaim disetujui yang dapat dibayar.']);
            $source = DanaSosialSumber::query()->lockForUpdate()->findOrFail($locked->sumber_dana_sosial_id);
            $wallet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail((int) $data['dompet_id']);
            $method = (string) $data['metode_pembayaran'];
            $expected = $method === 'tunai' ? DompetKoperasi::JENIS_KAS : DompetKoperasi::JENIS_BANK;
            $amount = (int) $locked->nominal;
            if ($wallet->jenis_dompet !== $expected || ! $wallet->akun || ! $wallet->akun->is_aktif) throw ValidationException::withMessages(['dompet_id' => 'Kas/Bank harus sesuai metode dan memiliki akun aktif.']);
            if ((int) $wallet->saldo < $amount || (int) $source->saldo_tersedia < $amount) throw ValidationException::withMessages(['nominal' => 'Saldo kas atau saldo sumber Dana Sosial tidak mencukupi.']);
            $balance = (int) $source->saldo_tersedia - $amount;
            $source->update(['saldo_tersedia' => $balance, 'status' => $balance === 0 ? DanaSosialSumber::STATUS_CLOSED : DanaSosialSumber::STATUS_ACTIVE]);
            $locked->update(['status' => KlaimDanaSosial::STATUS_PAID, 'dompet_id' => $wallet->id, 'metode_pembayaran' => $method, 'paid_by' => $userId, 'paid_at' => now()]);
            MutasiDanaSosial::query()->create(['dana_sosial_sumber_id' => $source->id, 'klaim_dana_sosial_id' => $locked->id, 'tipe' => 'keluar', 'nominal' => $amount, 'saldo_setelah' => $balance, 'keterangan' => 'Pembayaran '.$locked->kode_klaim, 'created_by' => $userId, 'idempotency_key' => 'dana-claim:mutation:'.$locked->id]);
            $date = CarbonImmutable::parse($data['tanggal_bayar'])->toDateString();
            $this->kas->record(['idempotency_key' => 'dana-claim:kas:'.$locked->id, 'dompet_id' => $wallet->id, 'tipe' => 'keluar', 'jumlah' => $amount, 'keterangan' => 'Pembayaran Klaim Dana Sosial '.$locked->kode_klaim, 'referensi_tipe' => KlaimDanaSosial::class, 'referensi_id' => $locked->id, 'tanggal' => $date]);
            $this->jurnal->record(['idempotency_key' => 'dana-claim:jurnal:'.$locked->id, 'tanggal' => $date, 'nomor_bukti' => $locked->kode_klaim, 'keterangan' => 'Pembayaran Klaim Dana Sosial', 'referensi_tipe' => KlaimDanaSosial::class, 'referensi_id' => $locked->id, 'created_by' => $userId], [$this->akun->line($this->akun->posting('dana_sosial.saldo'), 'debit', $amount), $this->akun->line($wallet->akun, 'kredit', $amount)]);
        });
    }

    private function transition(KlaimDanaSosial $claim, string $from, array $update): void
    {
        DB::transaction(function () use ($claim, $from, $update) {
            $locked = KlaimDanaSosial::query()->lockForUpdate()->findOrFail($claim->id);
            if ($locked->status !== $from) throw ValidationException::withMessages(['status' => 'Transisi status Klaim Dana Sosial tidak valid.']);
            $locked->update($update);
        });
    }

    private function rupiah(mixed $value): int { return (int) preg_replace('/[^\d]/', '', explode('.', trim((string) $value))[0] ?? '0'); }

    private function nextCode(string $type, string $prefix, CarbonImmutable $date): string
    {
        $period = $date->format('Ym');
        try { DB::table('nomor_urut_transaksi')->insert(['jenis' => $type, 'periode' => $period, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]); } catch (QueryException) {}
        $row = DB::table('nomor_urut_transaksi')->where('jenis', $type)->where('periode', $period)->lockForUpdate()->first();
        if (! $row) throw new RuntimeException('Counter Dana Sosial tidak tersedia.');
        $next = (int) $row->last_number + 1;
        DB::table('nomor_urut_transaksi')->where('id', $row->id)->update(['last_number' => $next, 'updated_at' => now()]);
        return sprintf('%s-%s-%06d', $prefix, $period, $next);
    }
}
