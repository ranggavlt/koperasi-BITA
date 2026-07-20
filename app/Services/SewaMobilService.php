<?php

namespace App\Services;

use App\Models\AsetKoperasi;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PembayaranSewaMobil;
use App\Models\PengurusKoperasi;
use App\Models\SewaMobil;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SewaMobilService
{
    public function __construct(
        private readonly AkuntansiService $akuntansiService
    ) {
    }

    public function createDraft(array $data, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($data, $financeUserId): SewaMobil {
            $karyawan = Karyawan::query()->lockForUpdate()->findOrFail((int) $data['karyawan_id']);
            $this->assertActiveKaryawan($karyawan);

            $aset = AsetKoperasi::query()
                ->with('mobil')
                ->lockForUpdate()
                ->findOrFail((int) $data['aset_koperasi_id']);
            $this->assertRentableAsset($aset);

            [$mulai, $selesai, $jumlahHari] = $this->normalizePeriod($data['tanggal_mulai'], $data['tanggal_selesai']);
            $tarifHarian = $this->rupiahInt($aset->mobil->tarif_sewa_harian ?? 0);
            $totalSewa = $jumlahHari * $tarifHarian;

            return SewaMobil::query()->create([
                'kode_sewa' => null,
                'aset_koperasi_id' => $aset->id,
                'karyawan_id' => $karyawan->id,
                'pemohon_user_id' => null,
                'recorded_by' => $financeUserId,
                'nama_perusahaan_snapshot' => config('koperasi.nama_perusahaan_penyewa', 'Bita Enarcon Engineering'),
                'nama_kegiatan' => $this->normalizeText($data['nama_kegiatan']),
                'lokasi_kegiatan' => $this->normalizeText($data['lokasi_kegiatan']),
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
                'jumlah_hari' => $jumlahHari,
                'tarif_harian_snapshot' => $tarifHarian,
                'total_sewa' => $totalSewa,
                'status' => SewaMobil::STATUS_DRAFT,
                'status_pembayaran' => SewaMobil::PEMBAYARAN_BELUM_BAYAR,
                'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                'created_by' => $financeUserId,
                'updated_by' => $financeUserId,
                'idempotency_key' => $data['idempotency_key'] ?? (string) Str::uuid(),
            ])->fresh(['aset.mobil', 'karyawan', 'recorder']);
        });
    }

    public function updateDraft(SewaMobil $sewaMobil, array $data, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $data, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            $this->assertStatus($locked, [SewaMobil::STATUS_DRAFT], 'Draft yang sudah diajukan tidak dapat diedit.');

            $karyawan = Karyawan::query()->lockForUpdate()->findOrFail((int) $data['karyawan_id']);
            $this->assertActiveKaryawan($karyawan);

            $aset = AsetKoperasi::query()
                ->with('mobil')
                ->lockForUpdate()
                ->findOrFail((int) $data['aset_koperasi_id']);
            $this->assertRentableAsset($aset);

            [$mulai, $selesai, $jumlahHari] = $this->normalizePeriod($data['tanggal_mulai'], $data['tanggal_selesai']);
            $tarifHarian = $this->rupiahInt($aset->mobil->tarif_sewa_harian ?? 0);
            $totalSewa = $jumlahHari * $tarifHarian;

            $locked->update([
                'aset_koperasi_id' => $aset->id,
                'karyawan_id' => $karyawan->id,
                'recorded_by' => $locked->recorded_by ?? $financeUserId,
                'nama_kegiatan' => $this->normalizeText($data['nama_kegiatan']),
                'lokasi_kegiatan' => $this->normalizeText($data['lokasi_kegiatan']),
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
                'jumlah_hari' => $jumlahHari,
                'tarif_harian_snapshot' => $tarifHarian,
                'total_sewa' => $totalSewa,
                'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['aset.mobil', 'karyawan', 'recorder']);
        });
    }

    public function submit(SewaMobil $sewaMobil, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->with(['karyawan', 'aset.mobil'])
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            $this->assertStatus($locked, [SewaMobil::STATUS_DRAFT], 'Hanya draft yang dapat diajukan.');
            $this->assertActiveKaryawan($locked->karyawan);
            $this->assertRentableAsset($locked->aset);

            if ($this->hasOverlap($locked)) {
                throw ValidationException::withMessages([
                    'jadwal' => 'Jadwal mobil bertabrakan dengan sewa yang sudah disetujui atau sedang berjalan.',
                ]);
            }

            $submittedAt = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));

            $locked->update([
                'kode_sewa' => $locked->kode_sewa ?: $this->nextKodeSewa($submittedAt),
                'status' => SewaMobil::STATUS_DIAJUKAN,
                'submitted_at' => $submittedAt->toDateTimeString(),
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['aset.mobil', 'karyawan', 'recorder']);
        });
    }

    public function reject(SewaMobil $sewaMobil, string $reason, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $reason, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            $this->assertStatus($locked, [SewaMobil::STATUS_DIAJUKAN], 'Hanya pengajuan yang dapat ditolak.');

            $locked->update([
                'status' => SewaMobil::STATUS_DITOLAK,
                'rejected_at' => now(),
                'alasan_penolakan' => $this->normalizeText($reason),
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['aset.mobil', 'karyawan', 'recorder']);
        });
    }

    public function approve(SewaMobil $sewaMobil, array $data, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $data, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->with(['karyawan', 'aset.mobil'])
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            $this->assertStatus($locked, [SewaMobil::STATUS_DIAJUKAN], 'Hanya pengajuan yang dapat disetujui.');
            $this->assertActiveKaryawan($locked->karyawan);
            $this->assertRentableAsset($locked->aset);

            if ($this->hasOverlap($locked)) {
                throw ValidationException::withMessages([
                    'jadwal' => 'Jadwal mobil bertabrakan dengan sewa yang sudah disetujui atau sedang berjalan.',
                ]);
            }

            $pengurus = PengurusKoperasi::query()
                ->with('anggota.karyawan')
                ->lockForUpdate()
                ->findOrFail((int) $data['pengurus_penyetuju_id']);
            $this->assertActivePengurus($pengurus);

            $locked->update([
                'status' => SewaMobil::STATUS_DISETUJUI,
                'pengurus_penyetuju_id' => $pengurus->id,
                'nama_pengurus_snapshot' => $pengurus->anggota->karyawan->nama,
                'jabatan_pengurus_snapshot' => $pengurus->jabatan,
                'approval_recorded_by' => $financeUserId,
                'approved_at' => now(),
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['aset.mobil', 'karyawan', 'recorder', 'pengurusPenyetuju.anggota.karyawan']);
        });
    }

    public function pay(SewaMobil $sewaMobil, array $data, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $data, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->with('pembayaran')
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            $this->assertStatus($locked, [SewaMobil::STATUS_DISETUJUI], 'Pembayaran hanya untuk sewa yang sudah disetujui.');

            if ($locked->status_pembayaran !== SewaMobil::PEMBAYARAN_BELUM_BAYAR || $locked->pembayaran) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Sewa ini sudah mempunyai pembayaran final.',
                ]);
            }

            $jumlah = $this->rupiahInt($data['jumlah_bayar']);
            $totalSewa = $this->rupiahInt($locked->total_sewa);

            if ($jumlah !== $totalSewa) {
                throw ValidationException::withMessages([
                    'jumlah_bayar' => 'Pembayaran Sewa Mobil wajib penuh sesuai total sewa. Pembayaran sebagian tidak diperbolehkan.',
                ]);
            }

            $dompet = DompetKoperasi::query()
                ->with('akun')
                ->lockForUpdate()
                ->findOrFail((int) $data['dompet_id']);
            $this->assertDompetForPayment($dompet, $data['metode_pembayaran']);

            $paidAt = isset($data['paid_at'])
                ? $this->normalizeDateTime($data['paid_at'])
                : CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));

            $pembayaran = PembayaranSewaMobil::query()->create([
                'sewa_mobil_id' => $locked->id,
                'dompet_id' => $dompet->id,
                'metode_pembayaran' => $data['metode_pembayaran'],
                'jumlah_bayar' => $jumlah,
                'status' => PembayaranSewaMobil::STATUS_PAID,
                'paid_at' => $paidAt->toDateTimeString(),
                'created_by' => $financeUserId,
                'idempotency_key' => 'sewa-mobil:pembayaran:' . $locked->id,
            ]);

            $this->increaseSaldoDompet($dompet, $jumlah);
            $this->recordPaymentMutasi($locked, $pembayaran, $dompet, $jumlah);
            $this->akuntansiService->recordPembayaranDimukaSewaMobil($locked, $pembayaran, $dompet->akun, $financeUserId);

            $locked->update([
                'status_pembayaran' => SewaMobil::PEMBAYARAN_PAID,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['aset.mobil', 'karyawan', 'recorder', 'pembayaran.dompet', 'jurnal.details']);
        });
    }

    public function start(SewaMobil $sewaMobil, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->with(['aset', 'pembayaran'])
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            $this->assertStatus($locked, [SewaMobil::STATUS_DISETUJUI], 'Hanya sewa disetujui yang dapat dimulai.');

            if ($locked->status_pembayaran !== SewaMobil::PEMBAYARAN_PAID || ! $locked->pembayaran) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Sewa wajib dibayar penuh sebelum kegiatan dimulai.',
                ]);
            }

            $aset = AsetKoperasi::query()
                ->lockForUpdate()
                ->findOrFail($locked->aset_koperasi_id);

            if ($aset->status !== AsetKoperasi::STATUS_TERSEDIA) {
                throw ValidationException::withMessages([
                    'aset_koperasi_id' => 'Mobil harus berstatus tersedia saat kegiatan dimulai.',
                ]);
            }

            if ($this->hasOtherRunning($locked)) {
                throw ValidationException::withMessages([
                    'jadwal' => 'Masih ada rental lain yang sedang berjalan untuk mobil ini.',
                ]);
            }

            $locked->update([
                'status' => SewaMobil::STATUS_BERJALAN,
                'started_at' => now(),
                'updated_by' => $financeUserId,
            ]);

            $aset->update([
                'status' => AsetKoperasi::STATUS_DIGUNAKAN_DISEWA,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['aset.mobil', 'karyawan', 'recorder', 'pembayaran.dompet']);
        });
    }

    public function complete(SewaMobil $sewaMobil, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->with(['aset', 'pembayaran'])
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            if ($locked->status === SewaMobil::STATUS_SELESAI) {
                return $locked->fresh(['aset.mobil', 'pembayaran.dompet', 'jurnal.details']);
            }

            $this->assertStatus($locked, [SewaMobil::STATUS_BERJALAN], 'Hanya sewa berjalan yang dapat diselesaikan.');

            if ($locked->status_pembayaran !== SewaMobil::PEMBAYARAN_PAID || ! $locked->pembayaran) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Sewa wajib paid sebelum diselesaikan.',
                ]);
            }

            $aset = AsetKoperasi::query()
                ->lockForUpdate()
                ->findOrFail($locked->aset_koperasi_id);

            $locked->update([
                'status' => SewaMobil::STATUS_SELESAI,
                'completed_at' => now(),
                'updated_by' => $financeUserId,
            ]);

            if (! $this->hasOtherRunning($locked->fresh())) {
                $aset->update([
                    'status' => AsetKoperasi::STATUS_TERSEDIA,
                    'updated_by' => $financeUserId,
                    'nonaktif_at' => null,
                    'nonaktif_by' => null,
                ]);
            }

            $this->akuntansiService->recordPengakuanPendapatanSewaMobil($locked->fresh(), $financeUserId);

            return $locked->fresh(['aset.mobil', 'karyawan', 'recorder', 'pembayaran.dompet', 'jurnal.details']);
        });
    }

    public function cancelByFinance(SewaMobil $sewaMobil, string $reason, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $reason, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->with(['pembayaran.dompet.akun'])
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            if (in_array($locked->status, [SewaMobil::STATUS_BERJALAN, SewaMobil::STATUS_SELESAI], true)) {
                throw ValidationException::withMessages([
                    'sewa' => 'Sewa berjalan atau selesai tidak dapat dibatalkan otomatis. Gunakan proses koreksi yang ditinjau Finance.',
                ]);
            }

            if ($locked->status === SewaMobil::STATUS_DIBATALKAN) {
                throw ValidationException::withMessages([
                    'sewa' => 'Sewa Mobil ini sudah dibatalkan/refund.',
                ]);
            }

            if ($locked->status_pembayaran === SewaMobil::PEMBAYARAN_PAID && $locked->pembayaran) {
                $this->refundPaidSewa($locked, $locked->pembayaran, $reason, $financeUserId);
            } else {
                $locked->update([
                    'status' => SewaMobil::STATUS_DIBATALKAN,
                    'cancelled_at' => now(),
                    'alasan_pembatalan' => $this->normalizeText($reason),
                    'updated_by' => $financeUserId,
                ]);
            }

            return $locked->fresh(['aset.mobil', 'karyawan', 'recorder', 'pembayaran.dompet']);
        });
    }

    private function refundPaidSewa(
        SewaMobil $sewaMobil,
        PembayaranSewaMobil $pembayaran,
        string $reason,
        int $financeUserId
    ): void {
        if ($pembayaran->status === PembayaranSewaMobil::STATUS_REFUNDED) {
            throw ValidationException::withMessages([
                'refund' => 'Pembayaran Sewa Mobil ini sudah pernah direfund.',
            ]);
        }

        $dompet = DompetKoperasi::query()
            ->with('akun')
            ->lockForUpdate()
            ->findOrFail($pembayaran->dompet_id);

        $jumlah = $this->rupiahInt($pembayaran->jumlah_bayar);
        if ($this->rupiahInt($dompet->saldo) < $jumlah) {
            throw ValidationException::withMessages([
                'dompet_id' => 'Saldo Dompet asal tidak cukup untuk refund penuh.',
            ]);
        }

        $this->decreaseSaldoDompet($dompet, $jumlah);

        $pembayaran->update([
            'status' => PembayaranSewaMobil::STATUS_REFUNDED,
            'refunded_at' => now(),
        ]);

        $this->recordRefundMutasi($sewaMobil, $pembayaran, $dompet, $jumlah);
        $this->akuntansiService->recordRefundSewaMobil($sewaMobil, $pembayaran->fresh(), $dompet->akun, $financeUserId);

        $sewaMobil->update([
            'status' => SewaMobil::STATUS_DIBATALKAN,
            'status_pembayaran' => SewaMobil::PEMBAYARAN_REFUNDED,
            'cancelled_at' => now(),
            'alasan_pembatalan' => $this->normalizeText($reason),
            'updated_by' => $financeUserId,
        ]);
    }

    private function hasOverlap(SewaMobil $sewaMobil): bool
    {
        return SewaMobil::query()
            ->where('aset_koperasi_id', $sewaMobil->aset_koperasi_id)
            ->whereKeyNot($sewaMobil->id)
            ->blockingSchedule()
            ->where('tanggal_mulai', '<=', $sewaMobil->tanggal_selesai->toDateString())
            ->where('tanggal_selesai', '>=', $sewaMobil->tanggal_mulai->toDateString())
            ->lockForUpdate()
            ->exists();
    }

    private function hasOtherRunning(SewaMobil $sewaMobil): bool
    {
        return SewaMobil::query()
            ->where('aset_koperasi_id', $sewaMobil->aset_koperasi_id)
            ->whereKeyNot($sewaMobil->id)
            ->where('status', SewaMobil::STATUS_BERJALAN)
            ->lockForUpdate()
            ->exists();
    }

    private function nextKodeSewa(CarbonImmutable $submittedAt): string
    {
        $periode = $submittedAt
            ->setTimezone(config('app.timezone', 'Asia/Jakarta'))
            ->format('Ym');

        DB::table('nomor_urut_transaksi')->insertOrIgnore([
            'jenis' => 'sewa_mobil',
            'periode' => $periode,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DB::table('nomor_urut_transaksi')
            ->where('jenis', 'sewa_mobil')
            ->where('periode', $periode)
            ->lockForUpdate()
            ->first();

        $next = ((int) $counter->last_number) + 1;

        DB::table('nomor_urut_transaksi')
            ->where('jenis', 'sewa_mobil')
            ->where('periode', $periode)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        return sprintf('SWM-%s-%06d', $periode, $next);
    }

    private function assertActiveKaryawan(?Karyawan $karyawan): void
    {
        if (! $karyawan || $karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'karyawan_id' => 'Sewa Mobil hanya untuk Karyawan aktif.',
            ]);
        }
    }

    private function assertRentableAsset(?AsetKoperasi $aset): void
    {
        if (! $aset || $aset->jenis_aset !== AsetKoperasi::JENIS_MOBIL || ! $aset->mobil) {
            throw ValidationException::withMessages([
                'aset_koperasi_id' => 'Aset yang dipilih harus Mobil Koperasi.',
            ]);
        }

        if (in_array($aset->status, [AsetKoperasi::STATUS_NONAKTIF, AsetKoperasi::STATUS_PERAWATAN], true)) {
            throw ValidationException::withMessages([
                'aset_koperasi_id' => 'Mobil nonaktif atau perawatan tidak dapat disewa.',
            ]);
        }

        if ($this->rupiahInt($aset->mobil->tarif_sewa_harian ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'aset_koperasi_id' => 'Mobil belum memiliki Tarif Sewa Harian yang valid.',
            ]);
        }
    }

    private function assertActivePengurus(PengurusKoperasi $pengurus): void
    {
        if ($pengurus->status !== PengurusKoperasi::STATUS_AKTIF
            || $pengurus->anggota?->status !== \App\Models\Anggota::STATUS_AKTIF
            || $pengurus->anggota?->karyawan?->status_kerja !== Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'pengurus_penyetuju_id' => 'Pengurus penyetuju harus aktif dan berasal dari Anggota/Karyawan aktif.',
            ]);
        }
    }

    private function assertStatus(SewaMobil $sewaMobil, array $allowed, string $message): void
    {
        if (! in_array($sewaMobil->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => $message,
            ]);
        }
    }

    private function assertDompetForPayment(DompetKoperasi $dompet, string $metode): void
    {
        $expected = match ($metode) {
            PembayaranSewaMobil::METODE_TUNAI => DompetKoperasi::JENIS_KAS,
            PembayaranSewaMobil::METODE_TRANSFER_BANK => DompetKoperasi::JENIS_BANK,
            default => throw ValidationException::withMessages(['metode_pembayaran' => 'Metode pembayaran Sewa Mobil tidak valid.']),
        };

        if ($dompet->jenis_dompet !== $expected) {
            throw ValidationException::withMessages([
                'dompet_id' => $metode === PembayaranSewaMobil::METODE_TUNAI
                    ? 'Pembayaran tunai harus masuk Dompet Kas.'
                    : 'Transfer Bank harus masuk Dompet Bank.',
            ]);
        }

        if (! $dompet->akun || ! $dompet->akun->is_aktif || $dompet->akun->kategori !== 'aset' || $dompet->akun->posisi_saldo !== 'debit') {
            throw ValidationException::withMessages([
                'dompet_id' => 'Dompet wajib memiliki mapping COA Aset aktif dengan saldo normal Debit.',
            ]);
        }
    }

    private function recordPaymentMutasi(
        SewaMobil $sewaMobil,
        PembayaranSewaMobil $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-mobil:pembayaran:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'masuk',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Pembayaran dimuka sewa mobil ' . $sewaMobil->kode_sewa,
                'referensi_tipe' => PembayaranSewaMobil::class,
                'referensi_id' => $pembayaran->id,
                'tanggal' => $pembayaran->paid_at->toDateString(),
            ]
        );
    }

    private function recordRefundMutasi(
        SewaMobil $sewaMobil,
        PembayaranSewaMobil $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-mobil:refund:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'keluar',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Refund penuh sewa mobil ' . $sewaMobil->kode_sewa,
                'referensi_tipe' => PembayaranSewaMobil::class,
                'referensi_id' => $pembayaran->id,
                'tanggal' => now()->toDateString(),
            ]
        );
    }

    private function increaseSaldoDompet(DompetKoperasi $dompet, int $jumlah): void
    {
        $dompet->update([
            'saldo' => $this->rupiahDecimal($this->rupiahInt($dompet->saldo) + $jumlah),
        ]);
    }

    private function decreaseSaldoDompet(DompetKoperasi $dompet, int $jumlah): void
    {
        $dompet->update([
            'saldo' => $this->rupiahDecimal($this->rupiahInt($dompet->saldo) - $jumlah),
        ]);
    }

    private function normalizePeriod(mixed $mulai, mixed $selesai): array
    {
        $start = $this->normalizeDate($mulai);
        $end = $this->normalizeDate($selesai);

        if ($start->greaterThan($end)) {
            throw ValidationException::withMessages([
                'tanggal_selesai' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.',
            ]);
        }

        return [$start, $end, $start->diffInDays($end) + 1];
    }

    private function normalizeDate(mixed $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, config('app.timezone', 'Asia/Jakarta'))
            ->setTimezone(config('app.timezone', 'Asia/Jakarta'))
            ->startOfDay();
    }

    private function normalizeDateTime(mixed $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, config('app.timezone', 'Asia/Jakarta'))
            ->setTimezone(config('app.timezone', 'Asia/Jakarta'));
    }

    private function normalizeText(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function nullableText(?string $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function rupiahInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $text = trim((string) $value);

        if (preg_match('/^-?\d+\.\d+$/', $text) === 1) {
            [$whole, $fraction] = explode('.', $text, 2);
            $rounded = (int) $whole;

            if ((int) str_pad(substr($fraction, 0, 2), 2, '0') >= 50) {
                $rounded += $rounded >= 0 ? 1 : -1;
            }

            return $rounded;
        }

        return (int) preg_replace('/[^\d-]/', '', $text);
    }

    private function rupiahDecimal(int $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
