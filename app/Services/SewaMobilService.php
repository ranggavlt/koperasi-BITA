<?php

namespace App\Services;

use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PembayaranSewaMobil;
use App\Models\PengurusKoperasi;
use App\Models\ReversalTransaksi;
use App\Models\SewaMobil;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SewaMobilService
{
    public function __construct(
        private readonly AkuntansiService $akuntansiService,
        private readonly RentalEligibilityService $eligibility,
    ) {
    }

    public static function normalizePlatNomor(mixed $value): ?string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    public function createDraft(array $data, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($data, $financeUserId): SewaMobil {
            $karyawan = Karyawan::query()->with('perusahaan')->lockForUpdate()->findOrFail((int) $data['karyawan_id']);
            $this->assertActiveKaryawan($karyawan);
            $this->assertOfficialCompany($karyawan);

            [$mulai, $selesai, $jumlahHari] = $this->normalizePeriod($data['tanggal_mulai'], $data['tanggal_selesai']);
            $totals = $this->calculateTotals($data);

            return SewaMobil::query()->create([
                'kode_sewa' => null,
                'aset_koperasi_id' => null,
                'perusahaan_id' => $karyawan->perusahaan->id,
                'kode_perusahaan_snapshot' => $karyawan->perusahaan->kode,
                'vendor_nama_snapshot' => $this->normalizeText($data['vendor_nama']),
                'vendor_kontak_snapshot' => $this->nullableText($data['vendor_kontak'] ?? null),
                'vendor_alamat_snapshot' => $this->nullableText($data['vendor_alamat'] ?? null),
                'kendaraan_jenis_snapshot' => $this->normalizeText($data['jenis_kendaraan']),
                'kendaraan_merk_tipe_snapshot' => trim($this->normalizeText($data['merek_kendaraan']).' '.$this->normalizeText($data['model_kendaraan'])),
                'nomor_polisi_snapshot' => $this->nullableText($data['plat_nomor_snapshot'] ?? null),
                'harga_vendor_total' => $totals['vendor'],
                'markup_total' => $totals['markup'],
                'model_sumber' => 'vendor',
                'karyawan_id' => $karyawan->id,
                'pemohon_user_id' => null,
                'recorded_by' => $financeUserId,
                'nama_perusahaan_snapshot' => $karyawan->perusahaan->nama,
                'nama_kegiatan' => $this->normalizeText($data['nama_kegiatan']),
                'lokasi_kegiatan' => $this->normalizeText($data['lokasi_kegiatan']),
                'vendor_nama' => $this->nullableText($data['vendor_nama'] ?? null),
                'vendor_kontak' => $this->nullableText($data['vendor_kontak'] ?? null),
                'vendor_alamat' => $this->nullableText($data['vendor_alamat'] ?? null),
                'jenis_kendaraan' => $this->nullableText($data['jenis_kendaraan'] ?? null),
                'merek_kendaraan' => $this->nullableText($data['merek_kendaraan'] ?? null),
                'model_kendaraan' => $this->nullableText($data['model_kendaraan'] ?? null),
                'plat_nomor_snapshot' => $this->nullableText($data['plat_nomor_snapshot'] ?? null),
                'plat_nomor_normalized' => $this->normalizePlate($data['plat_nomor_snapshot'] ?? null),
                'tahun_kendaraan' => isset($data['tahun_kendaraan']) ? (int) $data['tahun_kendaraan'] : null,
                'warna_kendaraan' => $this->nullableText($data['warna_kendaraan'] ?? null),
                'keterangan_kendaraan' => $this->nullableText($data['keterangan_kendaraan'] ?? null),
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
                'jumlah_hari' => $jumlahHari,
                'tarif_harian_snapshot' => 0,
                'total_sewa' => $totals['tagihan'],
                'total_harga_vendor' => $totals['vendor'],
                'total_markup' => $totals['markup'],
                'total_tagihan_perusahaan' => $totals['tagihan'],
                'status' => SewaMobil::STATUS_DRAFT,
                'status_pembayaran' => SewaMobil::PEMBAYARAN_BELUM_BAYAR,
                'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                'created_by' => $financeUserId,
                'updated_by' => $financeUserId,
                'idempotency_key' => $data['idempotency_key'] ?? (string) Str::uuid(),
            ])->fresh(['karyawan', 'recorder']);
        });
    }

    public function updateDraft(SewaMobil $sewaMobil, array $data, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $data, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            $this->assertStatus($locked, [SewaMobil::STATUS_DRAFT], 'Draft yang sudah diajukan tidak dapat diedit.');

            $karyawan = Karyawan::query()->with('perusahaan')->lockForUpdate()->findOrFail((int) $data['karyawan_id']);
            $this->assertActiveKaryawan($karyawan);
            $this->assertOfficialCompany($karyawan);

            [$mulai, $selesai, $jumlahHari] = $this->normalizePeriod($data['tanggal_mulai'], $data['tanggal_selesai']);
            $totals = $this->calculateTotals($data);

            $locked->update([
                'aset_koperasi_id' => null,
                'perusahaan_id' => $karyawan->perusahaan->id,
                'kode_perusahaan_snapshot' => $karyawan->perusahaan->kode,
                'nama_perusahaan_snapshot' => $karyawan->perusahaan->nama,
                'vendor_nama_snapshot' => $this->normalizeText($data['vendor_nama']),
                'vendor_kontak_snapshot' => $this->nullableText($data['vendor_kontak'] ?? null),
                'vendor_alamat_snapshot' => $this->nullableText($data['vendor_alamat'] ?? null),
                'kendaraan_jenis_snapshot' => $this->normalizeText($data['jenis_kendaraan']),
                'kendaraan_merk_tipe_snapshot' => trim($this->normalizeText($data['merek_kendaraan']).' '.$this->normalizeText($data['model_kendaraan'])),
                'nomor_polisi_snapshot' => $this->nullableText($data['plat_nomor_snapshot'] ?? null),
                'harga_vendor_total' => $totals['vendor'],
                'markup_total' => $totals['markup'],
                'model_sumber' => 'vendor',
                'karyawan_id' => $karyawan->id,
                'recorded_by' => $locked->recorded_by ?? $financeUserId,
                'nama_kegiatan' => $this->normalizeText($data['nama_kegiatan']),
                'lokasi_kegiatan' => $this->normalizeText($data['lokasi_kegiatan']),
                'vendor_nama' => $this->nullableText($data['vendor_nama'] ?? null),
                'vendor_kontak' => $this->nullableText($data['vendor_kontak'] ?? null),
                'vendor_alamat' => $this->nullableText($data['vendor_alamat'] ?? null),
                'jenis_kendaraan' => $this->nullableText($data['jenis_kendaraan'] ?? null),
                'merek_kendaraan' => $this->nullableText($data['merek_kendaraan'] ?? null),
                'model_kendaraan' => $this->nullableText($data['model_kendaraan'] ?? null),
                'plat_nomor_snapshot' => $this->nullableText($data['plat_nomor_snapshot'] ?? null),
                'plat_nomor_normalized' => $this->normalizePlate($data['plat_nomor_snapshot'] ?? null),
                'tahun_kendaraan' => isset($data['tahun_kendaraan']) ? (int) $data['tahun_kendaraan'] : null,
                'warna_kendaraan' => $this->nullableText($data['warna_kendaraan'] ?? null),
                'keterangan_kendaraan' => $this->nullableText($data['keterangan_kendaraan'] ?? null),
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
                'jumlah_hari' => $jumlahHari,
                'tarif_harian_snapshot' => 0,
                'total_sewa' => $totals['tagihan'],
                'total_harga_vendor' => $totals['vendor'],
                'total_markup' => $totals['markup'],
                'total_tagihan_perusahaan' => $totals['tagihan'],
                'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['karyawan', 'recorder']);
        });
    }

    public function submit(SewaMobil $sewaMobil, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->with('karyawan')
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            $this->assertStatus($locked, [SewaMobil::STATUS_DRAFT], 'Hanya draft yang dapat diajukan.');
            $this->assertActiveKaryawan($locked->karyawan);
            $this->assertTotalsPositive($locked);

            $submittedAt = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));

            $locked->update([
                'kode_sewa' => $locked->kode_sewa ?: $this->nextKodeSewa($submittedAt),
                'status' => SewaMobil::STATUS_DIAJUKAN,
                'submitted_at' => $submittedAt->toDateTimeString(),
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['karyawan', 'recorder']);
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

            return $locked->fresh(['karyawan', 'recorder']);
        });
    }

    public function approve(SewaMobil $sewaMobil, array $data, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $data, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->with('karyawan')
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            $this->assertStatus($locked, [SewaMobil::STATUS_DIAJUKAN], 'Hanya pengajuan yang dapat disetujui.');
            $this->assertActiveKaryawan($locked->karyawan);
            $this->assertReadyForApproval($locked);

            if ($this->hasOverlap($locked)) {
                throw ValidationException::withMessages([
                    'plat_nomor_snapshot' => 'Jadwal kendaraan dengan plat nomor yang sama bertabrakan dengan kontrak Sewa Mobil lain.',
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

            return $locked->fresh(['karyawan', 'recorder', 'pengurusPenyetuju.anggota.karyawan']);
        });
    }

    public function pay(SewaMobil $sewaMobil, array $data, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $data, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->with('pembayaran')
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            if ($locked->model_sumber === 'vendor') {
                throw ValidationException::withMessages(['pembayaran' => 'Pembayaran perusahaan Sewa Mobil vendor dilakukan melalui Invoice Perusahaan dan boleh dicicil.']);
            }

            $this->assertStatus($locked, [SewaMobil::STATUS_DISETUJUI], 'Pembayaran hanya untuk sewa yang sudah disetujui.');

            if ($locked->status_pembayaran !== SewaMobil::PEMBAYARAN_BELUM_BAYAR || $locked->pembayaran) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Sewa ini sudah mempunyai pembayaran final.',
                ]);
            }

            $jumlahDiterima = $this->rupiahInt($data['jumlah_diterima']);
            $jumlahBayarVendor = $this->rupiahInt($data['jumlah_bayar_vendor']);
            $totalTagihan = $this->rupiahInt($locked->total_tagihan_perusahaan);
            $hargaVendor = $this->rupiahInt($locked->total_harga_vendor);

            if ($jumlahDiterima !== $totalTagihan) {
                throw ValidationException::withMessages([
                    'jumlah_diterima' => 'Penerimaan perusahaan wajib penuh sesuai Total Tagihan Perusahaan.',
                ]);
            }

            if ($jumlahBayarVendor !== $hargaVendor) {
                throw ValidationException::withMessages([
                    'jumlah_bayar_vendor' => 'Pembayaran vendor wajib penuh sesuai Total Biaya Vendor.',
                ]);
            }

            $dompets = $this->lockDompets([
                (int) $data['dompet_penerimaan_id'],
                (int) $data['dompet_vendor_id'],
            ]);
            $dompetPenerimaan = $dompets->get((int) $data['dompet_penerimaan_id']);
            $dompetVendor = $dompets->get((int) $data['dompet_vendor_id']);

            $this->assertDompetForPayment($dompetPenerimaan, $data['metode_penerimaan'], 'dompet_penerimaan_id');
            $this->assertDompetForPayment($dompetVendor, $data['metode_pembayaran_vendor'], 'dompet_vendor_id');

            $availableVendorSaldo = $this->rupiahInt($dompetVendor->saldo)
                + ((int) $dompetVendor->id === (int) $dompetPenerimaan->id ? $jumlahDiterima : 0);

            if ($availableVendorSaldo < $hargaVendor) {
                throw ValidationException::withMessages([
                    'dompet_vendor_id' => 'Saldo Dompet pembayaran vendor tidak cukup setelah memperhitungkan penerimaan perusahaan.',
                ]);
            }

            $paidAt = isset($data['paid_at'])
                ? $this->normalizeDateTime($data['paid_at'])
                : CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));

            $pembayaran = PembayaranSewaMobil::query()->create([
                'sewa_mobil_id' => $locked->id,
                'dompet_id' => $dompetPenerimaan->id,
                'dompet_penerimaan_id' => $dompetPenerimaan->id,
                'dompet_vendor_id' => $dompetVendor->id,
                'metode_pembayaran' => $data['metode_penerimaan'],
                'metode_penerimaan' => $data['metode_penerimaan'],
                'metode_pembayaran_vendor' => $data['metode_pembayaran_vendor'],
                'jumlah_bayar' => $jumlahDiterima,
                'jumlah_diterima' => $jumlahDiterima,
                'jumlah_bayar_vendor' => $jumlahBayarVendor,
                'status' => PembayaranSewaMobil::STATUS_PAID,
                'paid_at' => $paidAt->toDateTimeString(),
                'received_at' => $paidAt->toDateTimeString(),
                'vendor_paid_at' => $paidAt->toDateTimeString(),
                'created_by' => $financeUserId,
                'idempotency_key' => 'sewa-mobil:pembayaran:' . $locked->id,
            ]);

            if ((int) $dompetPenerimaan->id === (int) $dompetVendor->id) {
                $this->setSaldoDompet($dompetPenerimaan, $this->rupiahInt($dompetPenerimaan->saldo) + $jumlahDiterima - $jumlahBayarVendor);
            } else {
                $this->setSaldoDompet($dompetPenerimaan, $this->rupiahInt($dompetPenerimaan->saldo) + $jumlahDiterima);
                $this->setSaldoDompet($dompetVendor, $this->rupiahInt($dompetVendor->saldo) - $jumlahBayarVendor);
            }

            $this->recordCompanyReceiptMutasi($locked, $pembayaran, $dompetPenerimaan, $jumlahDiterima);
            $this->recordVendorPaymentMutasi($locked, $pembayaran, $dompetVendor, $jumlahBayarVendor);
            $this->akuntansiService->recordPembayaranDimukaSewaMobil($locked, $pembayaran, $dompetPenerimaan->akun, $financeUserId);
            $this->akuntansiService->recordPembayaranVendorSewaMobil($locked, $pembayaran, $dompetVendor->akun, $financeUserId);

            $locked->update([
                'status_pembayaran' => SewaMobil::PEMBAYARAN_PAID,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['karyawan', 'recorder', 'pembayaran.dompetPenerimaan.akun', 'pembayaran.dompetVendor.akun', 'jurnal.details']);
        });
    }

    public function start(SewaMobil $sewaMobil, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->with(['karyawan', 'pembayaran', 'pembayaranVendorBaru', 'invoiceDetail.allocations', 'invoiceDetail.pengembalian'])
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            $this->assertStatus($locked, [SewaMobil::STATUS_DISETUJUI], 'Hanya sewa disetujui yang dapat dimulai.');
            $this->assertActiveKaryawan($locked->karyawan);

            if (! $this->eligibility->mobil($locked)['can_start']) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Sewa dapat dimulai setelah vendor dibayar dan tidak sedang menunggu pengembalian dana.',
                ]);
            }

            $locked->update([
                'status' => SewaMobil::STATUS_BERJALAN,
                'started_at' => now(),
                'started_by' => $financeUserId,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['karyawan', 'recorder', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor']);
        });
    }

    public function complete(SewaMobil $sewaMobil, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->with(['pembayaran', 'pembayaranVendor', 'invoiceDetail.invoice'])
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            if ($locked->status === SewaMobil::STATUS_SELESAI) {
                return $locked->fresh(['pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor', 'jurnal.details']);
            }

            $this->assertStatus($locked, [SewaMobil::STATUS_BERJALAN], 'Hanya sewa berjalan yang dapat diselesaikan.');

            if ($locked->model_sumber === 'vendor' && ! $locked->pembayaranVendor) {
                throw ValidationException::withMessages(['pembayaran' => 'Pembayaran vendor wajib tersedia sebelum sewa diselesaikan.']);
            }
            if ($locked->model_sumber !== 'vendor' && ($locked->status_pembayaran !== SewaMobil::PEMBAYARAN_PAID || ! $locked->pembayaran)) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Sewa wajib paid sebelum diselesaikan.',
                ]);
            }

            $locked->update([
                'status' => SewaMobil::STATUS_SELESAI,
                'completed_at' => now(),
                'completed_by' => $financeUserId,
                'updated_by' => $financeUserId,
            ]);

            if ($locked->model_sumber === 'vendor') {
                app(B2BRentalService::class)->recognizeRentalMargin($locked->fresh(), $financeUserId);
            } else {
                $this->akuntansiService->recordPengakuanPendapatanSewaMobil($locked->fresh(), $financeUserId);
            }

            return $locked->fresh(['karyawan', 'recorder', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor', 'jurnal.details']);
        });
    }

    public function cancelByFinance(SewaMobil $sewaMobil, string $reason, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $reason, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->with('pembayaran')
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            if (in_array($locked->status, [SewaMobil::STATUS_BERJALAN, SewaMobil::STATUS_SELESAI], true)) {
                throw ValidationException::withMessages([
                    'sewa' => 'Sewa berjalan atau selesai tidak dapat dibatalkan/refund otomatis.',
                ]);
            }

            if ($locked->status === SewaMobil::STATUS_REFUNDED || $locked->status_pembayaran === SewaMobil::PEMBAYARAN_REFUNDED) {
                return $locked->fresh(['karyawan', 'recorder', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor', 'reversal']);
            }

            if ($locked->status === SewaMobil::STATUS_DIBATALKAN) {
                throw ValidationException::withMessages([
                    'sewa' => 'Sewa Mobil ini sudah dibatalkan.',
                ]);
            }

            if ($locked->pembayaranVendor) {
                throw ValidationException::withMessages(['sewa' => 'Sewa dengan pembayaran vendor final tidak dapat dibatalkan langsung. Gunakan reversal penuh pembayaran vendor.']);
            }

            if ($locked->status_pembayaran === SewaMobil::PEMBAYARAN_PAID && $locked->pembayaran) {
                return $this->refundLocked($locked, $reason, $financeUserId);
            }

            $this->assertStatus($locked, [
                SewaMobil::STATUS_DRAFT,
                SewaMobil::STATUS_DIAJUKAN,
                SewaMobil::STATUS_DISETUJUI,
            ], 'Hanya draft, pengajuan, atau sewa approved yang belum dibayar dapat dibatalkan.');

            $locked->update([
                'status' => SewaMobil::STATUS_DIBATALKAN,
                'cancelled_at' => now(),
                'cancelled_by' => $financeUserId,
                'alasan_pembatalan' => $this->normalizeText($reason),
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['karyawan', 'recorder', 'pembayaran']);
        });
    }

    public function refundByFinance(SewaMobil $sewaMobil, string $reason, int $financeUserId): SewaMobil
    {
        return DB::transaction(function () use ($sewaMobil, $reason, $financeUserId): SewaMobil {
            $locked = SewaMobil::query()
                ->with('pembayaran')
                ->lockForUpdate()
                ->findOrFail($sewaMobil->id);

            if ($locked->status === SewaMobil::STATUS_REFUNDED || $locked->status_pembayaran === SewaMobil::PEMBAYARAN_REFUNDED) {
                return $locked->fresh(['karyawan', 'recorder', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor', 'reversal']);
            }

            $this->assertStatus($locked, [SewaMobil::STATUS_DISETUJUI], 'Refund otomatis hanya untuk sewa paid yang belum berjalan.');

            if ($locked->status_pembayaran !== SewaMobil::PEMBAYARAN_PAID || ! $locked->pembayaran) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Refund hanya dapat diproses untuk sewa yang sudah paid penuh.',
                ]);
            }

            return $this->refundLocked($locked, $reason, $financeUserId);
        });
    }

    private function refundLocked(SewaMobil $locked, string $reason, int $financeUserId): SewaMobil
    {
        if (! $locked->pembayaran) {
            throw ValidationException::withMessages([
                'pembayaran' => 'Pembayaran Sewa Mobil tidak ditemukan.',
            ]);
        }

        $pembayaran = PembayaranSewaMobil::query()
            ->where('sewa_mobil_id', $locked->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($pembayaran->status === PembayaranSewaMobil::STATUS_REFUNDED || $pembayaran->reversal_transaksi_id) {
            return $locked->fresh(['karyawan', 'recorder', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor', 'reversal']);
        }

        $jumlahDiterima = $this->rupiahInt($pembayaran->jumlah_diterima ?: $pembayaran->jumlah_bayar);
        $jumlahBayarVendor = $this->rupiahInt($pembayaran->jumlah_bayar_vendor ?: $locked->total_harga_vendor);

        $dompets = $this->lockDompets([
            (int) ($pembayaran->dompet_penerimaan_id ?: $pembayaran->dompet_id),
            (int) $pembayaran->dompet_vendor_id,
        ]);
        $dompetPenerimaan = $dompets->get((int) ($pembayaran->dompet_penerimaan_id ?: $pembayaran->dompet_id));
        $dompetVendor = $dompets->get((int) $pembayaran->dompet_vendor_id);

        $availableRefundSaldo = $this->rupiahInt($dompetPenerimaan->saldo)
            + ((int) $dompetPenerimaan->id === (int) $dompetVendor->id ? $jumlahBayarVendor : 0);

        if ($availableRefundSaldo < $jumlahDiterima) {
            throw ValidationException::withMessages([
                'dompet_penerimaan_id' => 'Saldo Dompet penerimaan tidak cukup untuk refund penuh kepada perusahaan.',
            ]);
        }

        $refundedAt = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));
        $normalizedReason = $this->normalizeText($reason);

        $reversal = ReversalTransaksi::query()->create([
            'kode_reversal' => $this->nextCode('reversal', 'REV', $refundedAt),
            'source_type' => SewaMobil::class,
            'source_id' => $locked->id,
            'jenis_reversal' => ReversalTransaksi::JENIS_SEWA_MOBIL_REFUND,
            'nominal' => $this->rupiahDecimal($jumlahDiterima),
            'alasan' => $normalizedReason,
            'status' => ReversalTransaksi::STATUS_PROCESSED,
            'dompet_refund_id' => $dompetPenerimaan->id,
            'created_by' => $financeUserId,
            'processed_by' => $financeUserId,
            'processed_at' => $refundedAt->toDateTimeString(),
            'idempotency_key' => 'sewa-mobil:refund:reversal:' . $locked->id,
        ]);

        if ((int) $dompetPenerimaan->id === (int) $dompetVendor->id) {
            $this->setSaldoDompet($dompetPenerimaan, $this->rupiahInt($dompetPenerimaan->saldo) + $jumlahBayarVendor - $jumlahDiterima);
        } else {
            $this->setSaldoDompet($dompetVendor, $this->rupiahInt($dompetVendor->saldo) + $jumlahBayarVendor);
            $this->setSaldoDompet($dompetPenerimaan, $this->rupiahInt($dompetPenerimaan->saldo) - $jumlahDiterima);
        }

        $this->recordVendorRefundMutasi($locked, $pembayaran, $dompetVendor, $jumlahBayarVendor, $refundedAt);
        $this->recordCompanyRefundMutasi($locked, $pembayaran, $dompetPenerimaan, $jumlahDiterima, $refundedAt);
        $this->akuntansiService->recordRefundVendorSewaMobil($locked, $pembayaran, $dompetVendor->akun, $reversal, $financeUserId);
        $this->akuntansiService->recordRefundPerusahaanSewaMobil($locked, $pembayaran, $dompetPenerimaan->akun, $reversal, $financeUserId);

        $pembayaran->update([
            'status' => PembayaranSewaMobil::STATUS_REFUNDED,
            'refunded_at' => $refundedAt->toDateTimeString(),
            'refunded_by' => $financeUserId,
            'refund_reason' => $normalizedReason,
            'reversal_transaksi_id' => $reversal->id,
        ]);

        $locked->update([
            'status' => SewaMobil::STATUS_REFUNDED,
            'status_pembayaran' => SewaMobil::PEMBAYARAN_REFUNDED,
            'refunded_at' => $refundedAt->toDateTimeString(),
            'refunded_by' => $financeUserId,
            'refund_reason' => $normalizedReason,
            'reversal_transaksi_id' => $reversal->id,
            'cancelled_at' => $refundedAt->toDateTimeString(),
            'cancelled_by' => $financeUserId,
            'alasan_pembatalan' => $normalizedReason,
            'updated_by' => $financeUserId,
        ]);

        return $locked->fresh(['karyawan', 'recorder', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor', 'reversal']);
    }

    private function calculateTotals(array $data): array
    {
        $vendor = $this->rupiahInt($data['total_harga_vendor'] ?? 0);
        $markup = $this->rupiahInt($data['total_markup'] ?? 0);

        if ($vendor <= 0) {
            throw ValidationException::withMessages([
                'total_harga_vendor' => 'Total Biaya Vendor wajib lebih dari nol.',
            ]);
        }

        if ($markup <= 0) {
            throw ValidationException::withMessages([
                'total_markup' => 'Margin Koperasi wajib lebih dari nol.',
            ]);
        }

        return [
            'vendor' => $vendor,
            'markup' => $markup,
            'tagihan' => $vendor + $markup,
        ];
    }

    private function hasOverlap(SewaMobil $sewaMobil): bool
    {
        $normalized = $this->normalizePlate($sewaMobil->plat_nomor_snapshot);
        if (! $normalized) {
            return false;
        }

        return SewaMobil::query()
            ->where('plat_nomor_normalized', $normalized)
            ->whereKeyNot($sewaMobil->id)
            ->blockingSchedule()
            ->where('tanggal_mulai', '<=', $sewaMobil->tanggal_selesai->toDateString())
            ->where('tanggal_selesai', '>=', $sewaMobil->tanggal_mulai->toDateString())
            ->lockForUpdate()
            ->exists();
    }

    private function nextKodeSewa(CarbonImmutable $submittedAt): string
    {
        return $this->nextCode('sewa_mobil', 'SWM', $submittedAt);
    }

    private function nextCode(string $jenis, string $prefix, CarbonImmutable $tanggal): string
    {
        $periode = $tanggal
            ->setTimezone(config('app.timezone', 'Asia/Jakarta'))
            ->format('Ym');

        DB::table('nomor_urut_transaksi')->insertOrIgnore([
            'jenis' => $jenis,
            'periode' => $periode,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DB::table('nomor_urut_transaksi')
            ->where('jenis', $jenis)
            ->where('periode', $periode)
            ->lockForUpdate()
            ->first();

        $next = ((int) $counter->last_number) + 1;

        DB::table('nomor_urut_transaksi')
            ->where('jenis', $jenis)
            ->where('periode', $periode)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        return sprintf('%s-%s-%06d', $prefix, $periode, $next);
    }

    private function assertActiveKaryawan(?Karyawan $karyawan): void
    {
        if (! $karyawan || $karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'karyawan_id' => 'Sewa Mobil hanya untuk Karyawan aktif.',
            ]);
        }
    }

    private function assertOfficialCompany(Karyawan $karyawan): void
    {
        if (! $karyawan->perusahaan || ! in_array($karyawan->perusahaan->kode, ['BEE', 'BBS', 'BKM'], true)) {
            throw ValidationException::withMessages([
                'karyawan_id' => 'Karyawan harus terhubung ke perusahaan resmi BEE, BBS, atau BKM.',
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

    private function assertReadyForApproval(SewaMobil $sewaMobil): void
    {
        $required = [
            'vendor_nama' => 'Nama vendor wajib lengkap sebelum approval.',
            'vendor_kontak' => 'Kontak vendor wajib lengkap sebelum approval.',
            'vendor_alamat' => 'Alamat vendor wajib lengkap sebelum approval.',
            'jenis_kendaraan' => 'Jenis kendaraan wajib lengkap sebelum approval.',
            'merek_kendaraan' => 'Merek kendaraan wajib lengkap sebelum approval.',
            'model_kendaraan' => 'Model/tipe kendaraan wajib lengkap sebelum approval.',
            'plat_nomor_snapshot' => 'Plat nomor wajib diisi sebelum approval.',
            'warna_kendaraan' => 'Warna kendaraan wajib lengkap sebelum approval.',
        ];

        foreach ($required as $field => $message) {
            if (trim((string) $sewaMobil->{$field}) === '') {
                throw ValidationException::withMessages([$field => $message]);
            }
        }

        if ((int) $sewaMobil->tahun_kendaraan <= 0) {
            throw ValidationException::withMessages([
                'tahun_kendaraan' => 'Tahun kendaraan wajib lengkap sebelum approval.',
            ]);
        }

        $this->assertTotalsPositive($sewaMobil);
    }

    private function assertTotalsPositive(SewaMobil $sewaMobil): void
    {
        if ($this->rupiahInt($sewaMobil->total_harga_vendor) <= 0) {
            throw ValidationException::withMessages([
                'total_harga_vendor' => 'Total Biaya Vendor wajib lebih dari nol.',
            ]);
        }

        if ($this->rupiahInt($sewaMobil->total_markup) <= 0) {
            throw ValidationException::withMessages([
                'total_markup' => 'Margin Koperasi wajib lebih dari nol.',
            ]);
        }

        if ($this->rupiahInt($sewaMobil->total_tagihan_perusahaan) !== $this->rupiahInt($sewaMobil->total_harga_vendor) + $this->rupiahInt($sewaMobil->total_markup)) {
            throw ValidationException::withMessages([
                'total_tagihan_perusahaan' => 'Total Tagihan Perusahaan tidak sesuai dengan Total Biaya Vendor + Margin Koperasi.',
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

    private function lockDompets(array $ids): Collection
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->unique()->sort()->values();

        $dompets = DompetKoperasi::query()
            ->with('akun')
            ->whereIn('id', $ids->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($dompets->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'dompet' => 'Salah satu Dompet tidak ditemukan.',
            ]);
        }

        return $dompets;
    }

    private function assertDompetForPayment(DompetKoperasi $dompet, string $metode, string $field): void
    {
        $expected = match ($metode) {
            PembayaranSewaMobil::METODE_TUNAI => DompetKoperasi::JENIS_KAS,
            PembayaranSewaMobil::METODE_TRANSFER_BANK => DompetKoperasi::JENIS_BANK,
            default => throw ValidationException::withMessages([$field => 'Metode pembayaran Sewa Mobil tidak valid.']),
        };

        if ($dompet->jenis_dompet !== $expected) {
            throw ValidationException::withMessages([
                $field => $metode === PembayaranSewaMobil::METODE_TUNAI
                    ? 'Metode tunai harus memakai Dompet Kas.'
                    : 'Transfer Bank harus memakai Dompet Bank.',
            ]);
        }

        if (! $dompet->akun || ! $dompet->akun->is_aktif || $dompet->akun->kategori !== 'aset' || $dompet->akun->posisi_saldo !== 'debit') {
            throw ValidationException::withMessages([
                $field => 'Dompet wajib memiliki mapping COA Aset aktif dengan saldo normal Debit.',
            ]);
        }
    }

    private function recordCompanyReceiptMutasi(
        SewaMobil $sewaMobil,
        PembayaranSewaMobil $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-mobil:penerimaan:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'masuk',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Penerimaan perusahaan atas sewa mobil ' . $sewaMobil->kode_sewa,
                'referensi_tipe' => PembayaranSewaMobil::class,
                'referensi_id' => $pembayaran->id,
                'tanggal' => $pembayaran->paid_at->toDateString(),
            ]
        );
    }

    private function recordVendorPaymentMutasi(
        SewaMobil $sewaMobil,
        PembayaranSewaMobil $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-mobil:pembayaran-vendor:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'keluar',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Pembayaran vendor sewa mobil ' . $sewaMobil->kode_sewa,
                'referensi_tipe' => PembayaranSewaMobil::class,
                'referensi_id' => $pembayaran->id,
                'tanggal' => $pembayaran->paid_at->toDateString(),
            ]
        );
    }

    private function recordVendorRefundMutasi(
        SewaMobil $sewaMobil,
        PembayaranSewaMobil $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah,
        CarbonImmutable $tanggal
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-mobil:refund-vendor:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'masuk',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Refund vendor atas sewa mobil ' . $sewaMobil->kode_sewa,
                'referensi_tipe' => PembayaranSewaMobil::class,
                'referensi_id' => $pembayaran->id,
                'tanggal' => $tanggal->toDateString(),
            ]
        );
    }

    private function recordCompanyRefundMutasi(
        SewaMobil $sewaMobil,
        PembayaranSewaMobil $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah,
        CarbonImmutable $tanggal
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-mobil:refund-perusahaan:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'keluar',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Refund perusahaan atas sewa mobil ' . $sewaMobil->kode_sewa,
                'referensi_tipe' => PembayaranSewaMobil::class,
                'referensi_id' => $pembayaran->id,
                'tanggal' => $tanggal->toDateString(),
            ]
        );
    }

    private function setSaldoDompet(DompetKoperasi $dompet, int $saldo): void
    {
        $dompet->update([
            'saldo' => $this->rupiahDecimal($saldo),
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

    private function normalizeText(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
    }

    private function nullableText(mixed $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizePlate(mixed $value): ?string
    {
        return self::normalizePlatNomor($value);
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
