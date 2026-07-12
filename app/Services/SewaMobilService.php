<?php

namespace App\Services;

use App\Models\AsetKoperasi;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PembayaranSewaMobil;
use App\Models\PengurusKoperasi;
use App\Models\SewaMobil;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SewaMobilService
{
    public function __construct(
        private readonly AkuntansiService $akuntansiService
    ) {
    }

    public function createDraft(array $data, User $user): SewaMobil
    {
        return DB::transaction(function () use ($data, $user): SewaMobil {
            $this->assertEmployeeUser($user);

            $karyawan = Karyawan::query()->lockForUpdate()->findOrFail($user->karyawan_id);
            $this->assertActiveKaryawan($karyawan);

            $aset = AsetKoperasi::query()
                ->with('mobil')
                ->lockForUpdate()
                ->findOrFail((int) $data['aset_koperasi_id']);

            $this->assertDraftAsset($aset);
            [$mulai, $selesai] = $this->normalizePeriod($data['mulai_at'], $data['selesai_at']);

            return SewaMobil::query()->create([
                'kode_sewa' => null,
                'aset_koperasi_id' => $aset->id,
                'karyawan_id' => $karyawan->id,
                'pemohon_user_id' => $user->id,
                'nama_perusahaan_snapshot' => config('koperasi.nama_perusahaan_penyewa', 'Bita Enarcon Engineering'),
                'nama_kegiatan' => $this->normalizeText($data['nama_kegiatan']),
                'lokasi_kegiatan' => $this->normalizeText($data['lokasi_kegiatan']),
                'mulai_at' => $mulai->toDateTimeString(),
                'selesai_at' => $selesai->toDateTimeString(),
                'tarif_total' => '0.00',
                'status' => SewaMobil::STATUS_DRAFT,
                'status_pembayaran' => SewaMobil::PEMBAYARAN_BELUM_BAYAR,
                'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'idempotency_key' => $data['idempotency_key'] ?? (string) Str::uuid(),
            ])->fresh(['aset.mobil', 'karyawan', 'pemohon']);
        });
    }

    public function updateDraft(SewaMobil $sewaMobil, array $data, User $user): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $data, $user): SewaMobil {
            $locked = SewaMobil::query()
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            $this->assertOwner($locked, $user);
            $this->assertStatus($locked, [SewaMobil::STATUS_DRAFT], 'Draft yang sudah diajukan tidak dapat diedit.');
            $this->assertActiveKaryawan($locked->karyawan);

            $aset = AsetKoperasi::query()
                ->with('mobil')
                ->lockForUpdate()
                ->findOrFail((int) $data['aset_koperasi_id']);
            $this->assertDraftAsset($aset);
            [$mulai, $selesai] = $this->normalizePeriod($data['mulai_at'], $data['selesai_at']);

            $locked->update([
                'aset_koperasi_id' => $aset->id,
                'nama_kegiatan' => $this->normalizeText($data['nama_kegiatan']),
                'lokasi_kegiatan' => $this->normalizeText($data['lokasi_kegiatan']),
                'mulai_at' => $mulai->toDateTimeString(),
                'selesai_at' => $selesai->toDateTimeString(),
                'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                'updated_by' => $user->id,
            ]);

            return $locked->fresh(['aset.mobil', 'karyawan', 'pemohon']);
        });
    }

    public function submit(SewaMobil $sewaMobil, User $user): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $user): SewaMobil {
            $locked = SewaMobil::query()
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            $this->assertOwner($locked, $user);
            $this->assertStatus($locked, [SewaMobil::STATUS_DRAFT], 'Hanya draft yang dapat diajukan.');
            $this->assertActiveKaryawan($locked->karyawan);

            $submittedAt = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));

            $locked->update([
                'kode_sewa' => $locked->kode_sewa ?: $this->nextKodeSewa($submittedAt),
                'status' => SewaMobil::STATUS_DIAJUKAN,
                'submitted_at' => $submittedAt->toDateTimeString(),
                'updated_by' => $user->id,
            ]);

            return $locked->fresh(['aset.mobil', 'karyawan', 'pemohon']);
        });
    }

    public function cancelByEmployee(SewaMobil $sewaMobil, User $user, string $reason): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $user, $reason): SewaMobil {
            $locked = SewaMobil::query()
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            $this->assertOwner($locked, $user);
            $this->assertStatus($locked, [SewaMobil::STATUS_DRAFT, SewaMobil::STATUS_DIAJUKAN], 'Karyawan hanya dapat membatalkan draft atau pengajuan.');

            $locked->update([
                'status' => SewaMobil::STATUS_DIBATALKAN,
                'cancelled_at' => now(),
                'alasan_pembatalan' => $this->normalizeText($reason),
                'updated_by' => $user->id,
            ]);

            return $locked->fresh(['aset.mobil', 'karyawan', 'pemohon']);
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

            return $locked->fresh(['aset.mobil', 'karyawan', 'pemohon']);
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

            $aset = AsetKoperasi::query()
                ->with('mobil')
                ->lockForUpdate()
                ->findOrFail($locked->aset_koperasi_id);
            $this->assertApprovableAsset($aset);

            $tarif = $this->rupiahInt($data['tarif_total']);
            if ($tarif <= 0) {
                throw ValidationException::withMessages([
                    'tarif_total' => 'Tarif sewa wajib lebih besar dari nol.',
                ]);
            }

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
                'tarif_total' => $this->rupiahDecimal($tarif),
                'status' => SewaMobil::STATUS_DISETUJUI,
                'pengurus_penyetuju_id' => $pengurus->id,
                'nama_pengurus_snapshot' => $pengurus->anggota->karyawan->nama,
                'jabatan_pengurus_snapshot' => $pengurus->jabatan,
                'approval_recorded_by' => $financeUserId,
                'approved_at' => now(),
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['aset.mobil', 'karyawan', 'pemohon', 'pengurusPenyetuju.anggota.karyawan']);
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
            $tarif = $this->rupiahInt($locked->tarif_total);

            if ($jumlah !== $tarif) {
                throw ValidationException::withMessages([
                    'jumlah_bayar' => 'Pembayaran Sewa Mobil wajib penuh sesuai tarif. Pembayaran sebagian tidak diperbolehkan.',
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
                'jumlah_bayar' => $this->rupiahDecimal($jumlah),
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

            return $locked->fresh(['aset.mobil', 'karyawan', 'pemohon', 'pembayaran.dompet', 'jurnal.details']);
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

            return $locked->fresh(['aset.mobil', 'karyawan', 'pemohon', 'pembayaran.dompet']);
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

            return $locked->fresh(['aset.mobil', 'karyawan', 'pemohon', 'pembayaran.dompet', 'jurnal.details']);
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

            return $locked->fresh(['aset.mobil', 'karyawan', 'pemohon', 'pembayaran.dompet']);
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
            ->where('mulai_at', '<', $sewaMobil->selesai_at)
            ->where('selesai_at', '>', $sewaMobil->mulai_at)
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

    private function assertEmployeeUser(User $user): void
    {
        if ($user->role !== 'karyawan' || ! $user->karyawan_id) {
            throw ValidationException::withMessages([
                'user' => 'Pengajuan Sewa Mobil hanya dapat dibuat oleh akun Karyawan.',
            ]);
        }

        if (! ($user->is_active ?? true)) {
            throw ValidationException::withMessages([
                'user' => 'Akun Karyawan sedang nonaktif.',
            ]);
        }
    }

    private function assertOwner(SewaMobil $sewaMobil, User $user): void
    {
        $this->assertEmployeeUser($user);

        if ((int) $sewaMobil->pemohon_user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'sewa' => 'Anda hanya dapat mengelola pengajuan milik sendiri.',
            ]);
        }
    }

    private function assertActiveKaryawan(?Karyawan $karyawan): void
    {
        if (! $karyawan || $karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'karyawan' => 'Sewa Mobil hanya untuk Karyawan aktif.',
            ]);
        }
    }

    private function assertDraftAsset(AsetKoperasi $aset): void
    {
        if ($aset->jenis_aset !== AsetKoperasi::JENIS_MOBIL || ! $aset->mobil) {
            throw ValidationException::withMessages([
                'aset_koperasi_id' => 'Aset yang dipilih harus Mobil Koperasi.',
            ]);
        }

        if (in_array($aset->status, [AsetKoperasi::STATUS_NONAKTIF, AsetKoperasi::STATUS_PERAWATAN], true)) {
            throw ValidationException::withMessages([
                'aset_koperasi_id' => 'Mobil nonaktif atau perawatan tidak dapat diajukan untuk sewa.',
            ]);
        }
    }

    private function assertApprovableAsset(AsetKoperasi $aset): void
    {
        $this->assertDraftAsset($aset);
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
        $start = $this->normalizeDateTime($mulai);
        $end = $this->normalizeDateTime($selesai);

        if ($start->greaterThanOrEqualTo($end)) {
            throw ValidationException::withMessages([
                'selesai_at' => 'Waktu selesai harus setelah waktu mulai.',
            ]);
        }

        return [$start, $end];
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
        return (int) round((float) $value);
    }

    private function rupiahDecimal(int $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
