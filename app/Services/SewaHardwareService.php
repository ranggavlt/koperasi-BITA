<?php

namespace App\Services;

use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PembayaranSewaHardware;
use App\Models\ReversalTransaksi;
use App\Models\SewaHardware;
use App\Models\SewaHardwareDetail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SewaHardwareService
{
    public function __construct(
        private readonly AkuntansiService $akuntansiService,
        private readonly RentalEligibilityService $eligibility,
    ) {
    }

    public function createDraft(array $data, int $financeUserId): SewaHardware
    {
        return DB::transaction(function () use ($data, $financeUserId): SewaHardware {
            [$mulai, $selesai] = $this->normalizePeriod($data['mulai_tanggal'], $data['selesai_tanggal']);
            $karyawan = Karyawan::query()->with('perusahaan')->lockForUpdate()->findOrFail((int) $data['karyawan_id']);
            $this->assertActiveKaryawan($karyawan);
            $this->assertOfficialCompany($karyawan);

            $detailRows = $this->buildDetailRows($data['details'] ?? []);
            $totals = $this->calculateTotals($detailRows);
            $createdAt = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));

            $sewa = SewaHardware::query()->create([
                'kode_sewa' => $this->nextKodeSewa($createdAt),
                'perusahaan_id' => $karyawan->perusahaan->id,
                'kode_perusahaan_snapshot' => $karyawan->perusahaan->kode,
                'nama_perusahaan_snapshot' => $karyawan->perusahaan->nama,
                'model_sumber' => 'vendor',
                'karyawan_id' => $karyawan->id,
                'mulai_tanggal' => $mulai->toDateString(),
                'selesai_tanggal' => $selesai->toDateString(),
                'kebutuhan' => $this->nullableText($data['kebutuhan'] ?? null),
                'vendor_nama' => $this->normalizeText($data['vendor_nama']),
                'vendor_kontak' => $this->normalizeText($data['vendor_kontak']),
                'vendor_alamat' => $this->normalizeText($data['vendor_alamat']),
                'total_harga_vendor' => $totals['harga_vendor'],
                'total_margin' => $totals['margin'],
                'total_tagihan_perusahaan' => $totals['tagihan'],
                'status' => SewaHardware::STATUS_DRAFT,
                'status_pembayaran' => SewaHardware::PEMBAYARAN_BELUM_BAYAR,
                'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                'recorded_by' => $financeUserId,
                'created_by' => $financeUserId,
                'updated_by' => $financeUserId,
                'idempotency_key' => $data['idempotency_key'] ?? (string) Str::uuid(),
            ]);

            $sewa->details()->createMany($detailRows);

            return $sewa->fresh(['details', 'karyawan', 'recorder']);
        });
    }

    public function updateDraft(SewaHardware $sewaHardware, array $data, int $financeUserId): SewaHardware
    {
        return DB::transaction(function () use ($sewaHardware, $data, $financeUserId): SewaHardware {
            $locked = SewaHardware::query()
                ->with('details')
                ->lockForUpdate()
                ->findOrFail($sewaHardware->id);

            $this->assertStatus($locked, [SewaHardware::STATUS_DRAFT], 'Kontrak yang sudah dikonfirmasi tidak dapat diedit.');

            [$mulai, $selesai] = $this->normalizePeriod($data['mulai_tanggal'], $data['selesai_tanggal']);
            $karyawan = Karyawan::query()->with('perusahaan')->lockForUpdate()->findOrFail((int) $data['karyawan_id']);
            $this->assertActiveKaryawan($karyawan);
            $this->assertOfficialCompany($karyawan);

            $detailRows = $this->buildDetailRows($data['details'] ?? []);
            $totals = $this->calculateTotals($detailRows);

            $locked->details->each->delete();
            $locked->details()->createMany($detailRows);

            $locked->update([
                'karyawan_id' => $karyawan->id,
                'perusahaan_id' => $karyawan->perusahaan->id,
                'kode_perusahaan_snapshot' => $karyawan->perusahaan->kode,
                'nama_perusahaan_snapshot' => $karyawan->perusahaan->nama,
                'model_sumber' => 'vendor',
                'mulai_tanggal' => $mulai->toDateString(),
                'selesai_tanggal' => $selesai->toDateString(),
                'kebutuhan' => $this->nullableText($data['kebutuhan'] ?? null),
                'vendor_nama' => $this->normalizeText($data['vendor_nama']),
                'vendor_kontak' => $this->normalizeText($data['vendor_kontak']),
                'vendor_alamat' => $this->normalizeText($data['vendor_alamat']),
                'total_harga_vendor' => $totals['harga_vendor'],
                'total_margin' => $totals['margin'],
                'total_tagihan_perusahaan' => $totals['tagihan'],
                'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details', 'karyawan', 'recorder']);
        });
    }

    public function confirm(SewaHardware $sewaHardware, int $financeUserId): SewaHardware
    {
        return DB::transaction(function () use ($sewaHardware, $financeUserId): SewaHardware {
            $locked = SewaHardware::query()
                ->with(['details', 'karyawan'])
                ->lockForUpdate()
                ->findOrFail($sewaHardware->id);

            $this->assertStatus($locked, [SewaHardware::STATUS_DRAFT], 'Hanya draft Sewa Hardware yang dapat dikonfirmasi.');
            $this->assertActiveKaryawan($locked->karyawan);

            if ($locked->details->isEmpty()) {
                throw ValidationException::withMessages([
                    'details' => 'Kontrak Sewa Hardware wajib mempunyai minimal satu detail hardware.',
                ]);
            }

            $totals = $this->calculateTotals($locked->details->map(fn (SewaHardwareDetail $detail): array => [
                'subtotal_harga_vendor' => $this->rupiahInt($detail->subtotal_harga_vendor),
                'subtotal_margin' => $this->rupiahInt($detail->subtotal_margin),
                'subtotal_tagihan' => $this->rupiahInt($detail->subtotal_tagihan),
            ])->all());

            $locked->update([
                'total_harga_vendor' => $totals['harga_vendor'],
                'total_margin' => $totals['margin'],
                'total_tagihan_perusahaan' => $totals['tagihan'],
                'status' => SewaHardware::STATUS_DIKONFIRMASI,
                'confirmed_at' => now(),
                'confirmed_by' => $financeUserId,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details', 'karyawan', 'confirmer']);
        });
    }

    public function pay(SewaHardware $sewaHardware, array $data, int $financeUserId): SewaHardware
    {
        return DB::transaction(function () use ($sewaHardware, $data, $financeUserId): SewaHardware {
            $locked = SewaHardware::query()
                ->with('pembayaran')
                ->lockForUpdate()
                ->findOrFail($sewaHardware->id);

            $this->assertStatus($locked, [SewaHardware::STATUS_DIKONFIRMASI], 'Pembayaran hanya untuk kontrak yang sudah dikonfirmasi.');

            if ($locked->status_pembayaran !== SewaHardware::PEMBAYARAN_BELUM_BAYAR || $locked->pembayaran) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Kontrak ini sudah mempunyai pembayaran final.',
                ]);
            }

            $jumlahDiterima = $this->rupiahInt($data['jumlah_diterima']);
            $jumlahBayarVendor = $this->rupiahInt($data['jumlah_bayar_vendor']);
            $totalTagihan = $this->rupiahInt($locked->total_tagihan_perusahaan);
            $hargaVendor = $this->rupiahInt($locked->total_harga_vendor);

            if ($jumlahDiterima !== $totalTagihan) {
                throw ValidationException::withMessages([
                    'jumlah_diterima' => 'Penerimaan perusahaan wajib penuh sesuai total tagihan. Nominal tidak dapat diubah bebas.',
                ]);
            }

            if ($jumlahBayarVendor !== $hargaVendor) {
                throw ValidationException::withMessages([
                    'jumlah_bayar_vendor' => 'Pembayaran vendor wajib penuh sesuai harga vendor. Nominal tidak dapat diubah bebas.',
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
                + ((int) $dompetVendor->id === (int) $dompetPenerimaan->id ? $totalTagihan : 0);

            if ($availableVendorSaldo < $hargaVendor) {
                throw ValidationException::withMessages([
                    'dompet_vendor_id' => 'Saldo Dompet pembayaran vendor tidak cukup setelah memperhitungkan penerimaan perusahaan.',
                ]);
            }

            $paidAt = isset($data['paid_at'])
                ? $this->normalizeDateTime($data['paid_at'])
                : CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));

            $pembayaran = PembayaranSewaHardware::query()->create([
                'sewa_hardware_id' => $locked->id,
                'dompet_penerimaan_id' => $dompetPenerimaan->id,
                'dompet_vendor_id' => $dompetVendor->id,
                'metode_penerimaan' => $data['metode_penerimaan'],
                'metode_pembayaran_vendor' => $data['metode_pembayaran_vendor'],
                'jumlah_diterima' => $jumlahDiterima,
                'jumlah_bayar_vendor' => $jumlahBayarVendor,
                'status' => PembayaranSewaHardware::STATUS_PAID,
                'paid_at' => $paidAt->toDateTimeString(),
                'created_by' => $financeUserId,
                'idempotency_key' => 'sewa-hardware:pembayaran:' . $locked->id,
            ]);

            if ((int) $dompetPenerimaan->id === (int) $dompetVendor->id) {
                $this->setSaldoDompet($dompetPenerimaan, $this->rupiahInt($dompetPenerimaan->saldo) + $jumlahDiterima - $jumlahBayarVendor);
            } else {
                $this->setSaldoDompet($dompetPenerimaan, $this->rupiahInt($dompetPenerimaan->saldo) + $jumlahDiterima);
                $this->setSaldoDompet($dompetVendor, $this->rupiahInt($dompetVendor->saldo) - $jumlahBayarVendor);
            }

            $this->recordCompanyReceiptMutasi($locked, $pembayaran, $dompetPenerimaan, $jumlahDiterima);
            $this->recordVendorPaymentMutasi($locked, $pembayaran, $dompetVendor, $jumlahBayarVendor);
            $this->akuntansiService->recordPembayaranDimukaSewaHardware($locked, $pembayaran, $dompetPenerimaan->akun, $financeUserId);
            $this->akuntansiService->recordPembayaranVendorSewaHardware($locked, $pembayaran, $dompetVendor->akun, $financeUserId);

            $locked->update([
                'status_pembayaran' => SewaHardware::PEMBAYARAN_PAID,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details', 'karyawan', 'pembayaran.dompetPenerimaan.akun', 'pembayaran.dompetVendor.akun']);
        });
    }

    public function start(SewaHardware $sewaHardware, int $financeUserId): SewaHardware
    {
        return DB::transaction(function () use ($sewaHardware, $financeUserId): SewaHardware {
            $locked = SewaHardware::query()
                ->with(['karyawan', 'pembayaran', 'pembayaranVendorBaru', 'invoiceDetail.allocations', 'invoiceDetail.pengembalian'])
                ->lockForUpdate()
                ->findOrFail($sewaHardware->id);

            $this->assertStatus($locked, [SewaHardware::STATUS_DIKONFIRMASI], 'Hanya kontrak dikonfirmasi yang dapat dimulai.');
            $this->assertActiveKaryawan($locked->karyawan);

            if (! $this->eligibility->hardware($locked)['can_start']) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Kontrak dapat dimulai setelah vendor dibayar dan tidak sedang menunggu pengembalian dana.',
                ]);
            }

            $locked->update([
                'status' => SewaHardware::STATUS_BERJALAN,
                'started_at' => now(),
                'started_by' => $financeUserId,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details', 'karyawan', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor']);
        });
    }

    public function complete(SewaHardware $sewaHardware, int $financeUserId): SewaHardware
    {
        return DB::transaction(function () use ($sewaHardware, $financeUserId): SewaHardware {
            $locked = SewaHardware::query()
                ->with(['details', 'pembayaran', 'pembayaranVendor', 'invoiceDetail.invoice'])
                ->lockForUpdate()
                ->findOrFail($sewaHardware->id);

            if ($locked->status === SewaHardware::STATUS_SELESAI) {
                return $locked->fresh(['details', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor', 'jurnal.details']);
            }

            $this->assertStatus($locked, [SewaHardware::STATUS_BERJALAN], 'Hanya kontrak berjalan yang dapat diselesaikan.');

            if (! $locked->pembayaranVendor
                && ($locked->status_pembayaran !== SewaHardware::PEMBAYARAN_PAID || ! $locked->pembayaran)) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Pembayaran vendor wajib tersedia sebelum kontrak diselesaikan.',
                ]);
            }

            $locked->update([
                'status' => SewaHardware::STATUS_SELESAI,
                'completed_at' => now(),
                'completed_by' => $financeUserId,
                'updated_by' => $financeUserId,
            ]);

            if ($locked->pembayaranVendor) {
                app(B2BRentalService::class)->recognizeRentalMargin($locked->fresh(), $financeUserId);
            } else {
                $this->akuntansiService->recordPengakuanPendapatanSewaHardware($locked->fresh(), $financeUserId);
            }

            return $locked->fresh(['details', 'karyawan', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor', 'jurnal.details']);
        });
    }

    public function cancelByFinance(SewaHardware $sewaHardware, string $reason, int $financeUserId): SewaHardware
    {
        return DB::transaction(function () use ($sewaHardware, $reason, $financeUserId): SewaHardware {
            $locked = SewaHardware::query()
                ->with('pembayaran')
                ->lockForUpdate()
                ->findOrFail($sewaHardware->id);

            if ($locked->status === SewaHardware::STATUS_DIBATALKAN) {
                throw ValidationException::withMessages([
                    'sewa_hardware' => 'Kontrak Sewa Hardware ini sudah dibatalkan.',
                ]);
            }

            if ($locked->status_pembayaran === SewaHardware::PEMBAYARAN_PAID || $locked->pembayaran) {
                throw ValidationException::withMessages([
                    'sewa_hardware' => 'Kontrak yang sudah paid tidak dapat dibatalkan/refund otomatis. Gunakan proses koreksi Finance manual.',
                ]);
            }

            if (in_array($locked->status, [SewaHardware::STATUS_BERJALAN, SewaHardware::STATUS_SELESAI], true)) {
                throw ValidationException::withMessages([
                    'sewa_hardware' => 'Kontrak berjalan atau selesai bersifat immutable dan tidak dapat dibatalkan otomatis.',
                ]);
            }

            $this->assertStatus($locked, [SewaHardware::STATUS_DRAFT, SewaHardware::STATUS_DIKONFIRMASI], 'Hanya draft atau kontrak confirmed yang belum dibayar dapat dibatalkan.');

            $locked->update([
                'status' => SewaHardware::STATUS_DIBATALKAN,
                'cancelled_at' => now(),
                'alasan_pembatalan' => $this->normalizeText($reason),
                'cancelled_by' => $financeUserId,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details', 'karyawan', 'pembayaran']);
        });
    }

    public function refundByFinance(SewaHardware $sewaHardware, string $reason, int $financeUserId): SewaHardware
    {
        return DB::transaction(function () use ($sewaHardware, $reason, $financeUserId): SewaHardware {
            $locked = SewaHardware::query()
                ->with(['pembayaran', 'details'])
                ->lockForUpdate()
                ->findOrFail($sewaHardware->id);

            if ($locked->status === SewaHardware::STATUS_REFUNDED || $locked->status_pembayaran === SewaHardware::PEMBAYARAN_REFUNDED) {
                return $locked->fresh(['details', 'karyawan', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor', 'reversal']);
            }

            $this->assertStatus($locked, [SewaHardware::STATUS_DIKONFIRMASI], 'Refund otomatis hanya untuk kontrak paid yang belum berjalan.');

            $pembayaran = PembayaranSewaHardware::query()
                ->where('sewa_hardware_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if (! $pembayaran || $locked->status_pembayaran !== SewaHardware::PEMBAYARAN_PAID || $pembayaran->status !== PembayaranSewaHardware::STATUS_PAID) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Refund hanya dapat diproses untuk kontrak yang sudah paid penuh.',
                ]);
            }

            if ($pembayaran->reversal_transaksi_id || ReversalTransaksi::query()->where('source_type', SewaHardware::class)->where('source_id', $locked->id)->exists()) {
                return $locked->fresh(['details', 'karyawan', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor', 'reversal']);
            }

            $jumlahDiterima = $this->rupiahInt($pembayaran->jumlah_diterima);
            $jumlahBayarVendor = $this->rupiahInt($pembayaran->jumlah_bayar_vendor);

            $dompets = $this->lockDompets([
                (int) $pembayaran->dompet_penerimaan_id,
                (int) $pembayaran->dompet_vendor_id,
            ]);
            $dompetPenerimaan = $dompets->get((int) $pembayaran->dompet_penerimaan_id);
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
            $pembayaran->forceFill(['refunded_at' => $refundedAt]);

            $reversal = ReversalTransaksi::query()->create([
                'kode_reversal' => $this->nextCode('reversal', 'REV', $refundedAt),
                'source_type' => SewaHardware::class,
                'source_id' => $locked->id,
                'jenis_reversal' => ReversalTransaksi::JENIS_SEWA_HARDWARE_REFUND,
                'nominal' => $this->rupiahDecimal($jumlahDiterima),
                'alasan' => $normalizedReason,
                'status' => ReversalTransaksi::STATUS_PROCESSED,
                'dompet_refund_id' => $dompetPenerimaan->id,
                'created_by' => $financeUserId,
                'processed_by' => $financeUserId,
                'processed_at' => $refundedAt->toDateTimeString(),
                'idempotency_key' => 'sewa-hardware:refund:reversal:' . $locked->id,
            ]);

            if ((int) $dompetPenerimaan->id === (int) $dompetVendor->id) {
                $this->setSaldoDompet($dompetPenerimaan, $this->rupiahInt($dompetPenerimaan->saldo) + $jumlahBayarVendor - $jumlahDiterima);
            } else {
                $this->setSaldoDompet($dompetVendor, $this->rupiahInt($dompetVendor->saldo) + $jumlahBayarVendor);
                $this->setSaldoDompet($dompetPenerimaan, $this->rupiahInt($dompetPenerimaan->saldo) - $jumlahDiterima);
            }

            $this->recordVendorRefundMutasi($locked, $pembayaran, $dompetVendor, $jumlahBayarVendor, $refundedAt);
            $this->recordCompanyRefundMutasi($locked, $pembayaran, $dompetPenerimaan, $jumlahDiterima, $refundedAt);
            $this->akuntansiService->recordRefundVendorSewaHardware($locked, $pembayaran, $dompetVendor->akun, $reversal, $financeUserId);
            $this->akuntansiService->recordRefundPerusahaanSewaHardware($locked, $pembayaran, $dompetPenerimaan->akun, $reversal, $financeUserId);

            $pembayaran->update([
                'status' => PembayaranSewaHardware::STATUS_REFUNDED,
                'refunded_at' => $refundedAt->toDateTimeString(),
                'refunded_by' => $financeUserId,
                'refund_reason' => $normalizedReason,
                'reversal_transaksi_id' => $reversal->id,
            ]);

            $locked->update([
                'status' => SewaHardware::STATUS_REFUNDED,
                'status_pembayaran' => SewaHardware::PEMBAYARAN_REFUNDED,
                'refunded_at' => $refundedAt->toDateTimeString(),
                'refunded_by' => $financeUserId,
                'refund_reason' => $normalizedReason,
                'reversal_transaksi_id' => $reversal->id,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details', 'karyawan', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor', 'reversal']);
        });
    }

    private function buildDetailRows(array $details): array
    {
        if ($details === []) {
            throw ValidationException::withMessages([
                'details' => 'Kontrak Sewa Hardware wajib mempunyai minimal satu detail kebutuhan hardware.',
            ]);
        }

        return collect($details)
            ->map(function (array $detail): array {
                $jenisHardware = $this->normalizeText($detail['jenis_hardware'] ?? '');
                $namaModel = $this->normalizeText($detail['nama_model_hardware'] ?? '');
                $kuantitas = (int) ($detail['kuantitas'] ?? 0);
                $hargaVendorPerUnit = $this->rupiahInt($detail['harga_vendor_per_unit'] ?? 0);

                if (! array_key_exists($jenisHardware, SewaHardwareDetail::jenisOptions())) {
                    throw ValidationException::withMessages([
                        'details' => 'Jenis hardware wajib dipilih pada setiap baris.',
                    ]);
                }

                if ($namaModel === '') {
                    throw ValidationException::withMessages([
                        'details' => 'Nama/model hardware wajib diisi pada setiap baris.',
                    ]);
                }

                if ($kuantitas <= 0) {
                    throw ValidationException::withMessages([
                        'details' => 'Kuantitas hardware wajib lebih besar dari nol.',
                    ]);
                }

                if ($hargaVendorPerUnit <= 0) {
                    throw ValidationException::withMessages([
                        'details' => 'Harga vendor per unit wajib lebih besar dari nol.',
                    ]);
                }

                $marginPerUnit = $this->calculateMargin($hargaVendorPerUnit);
                $hargaTagihanPerUnit = $hargaVendorPerUnit + $marginPerUnit;

                return [
                    'jenis_hardware' => $jenisHardware,
                    'nama_model_hardware' => $namaModel,
                    'spesifikasi_kebutuhan' => $this->nullableText($detail['spesifikasi_kebutuhan'] ?? null),
                    'kuantitas' => $kuantitas,
                    'harga_vendor_per_unit' => $hargaVendorPerUnit,
                    'margin_persen_snapshot' => SewaHardwareDetail::MARGIN_PERSEN,
                    'margin_per_unit' => $marginPerUnit,
                    'harga_tagihan_per_unit' => $hargaTagihanPerUnit,
                    'subtotal_harga_vendor' => $hargaVendorPerUnit * $kuantitas,
                    'subtotal_margin' => $marginPerUnit * $kuantitas,
                    'subtotal_tagihan' => $hargaTagihanPerUnit * $kuantitas,
                ];
            })
            ->values()
            ->all();
    }

    private function calculateTotals(array $detailRows): array
    {
        $hargaVendor = 0;
        $margin = 0;
        $tagihan = 0;

        foreach ($detailRows as $row) {
            $hargaVendor += $this->rupiahInt($row['subtotal_harga_vendor'] ?? 0);
            $margin += $this->rupiahInt($row['subtotal_margin'] ?? 0);
            $tagihan += $this->rupiahInt($row['subtotal_tagihan'] ?? 0);
        }

        return [
            'harga_vendor' => $hargaVendor,
            'margin' => $margin,
            'tagihan' => $tagihan,
        ];
    }

    private function calculateMargin(int $hargaVendorPerUnit): int
    {
        return intdiv(($hargaVendorPerUnit * SewaHardwareDetail::MARGIN_PERSEN) + 50, 100);
    }

    private function nextKodeSewa(CarbonImmutable $createdAt): string
    {
        $periode = $createdAt
            ->setTimezone(config('app.timezone', 'Asia/Jakarta'))
            ->format('Ym');

        DB::table('nomor_urut_transaksi')->insertOrIgnore([
            'jenis' => 'sewa_hardware',
            'periode' => $periode,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DB::table('nomor_urut_transaksi')
            ->where('jenis', 'sewa_hardware')
            ->where('periode', $periode)
            ->lockForUpdate()
            ->first();

        $next = ((int) $counter->last_number) + 1;

        DB::table('nomor_urut_transaksi')
            ->where('jenis', 'sewa_hardware')
            ->where('periode', $periode)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        return sprintf('SWH-%s-%06d', $periode, $next);
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
                'karyawan_id' => 'Sewa Hardware hanya untuk Karyawan aktif.',
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

    private function assertStatus(SewaHardware $sewaHardware, array $allowed, string $message): void
    {
        if (! in_array($sewaHardware->status, $allowed, true)) {
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
            PembayaranSewaHardware::METODE_TUNAI => DompetKoperasi::JENIS_KAS,
            PembayaranSewaHardware::METODE_TRANSFER_BANK => DompetKoperasi::JENIS_BANK,
            default => throw ValidationException::withMessages([$field => 'Metode pembayaran Sewa Hardware tidak valid.']),
        };

        if ($dompet->jenis_dompet !== $expected) {
            throw ValidationException::withMessages([
                $field => $metode === PembayaranSewaHardware::METODE_TUNAI
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
        SewaHardware $sewaHardware,
        PembayaranSewaHardware $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-hardware:penerimaan:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'masuk',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Penerimaan perusahaan atas sewa hardware ' . $sewaHardware->kode_sewa,
                'referensi_tipe' => PembayaranSewaHardware::class,
                'referensi_id' => $pembayaran->id,
                'tanggal' => $pembayaran->paid_at->toDateString(),
            ]
        );
    }

    private function recordVendorPaymentMutasi(
        SewaHardware $sewaHardware,
        PembayaranSewaHardware $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-hardware:pembayaran-vendor:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'keluar',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Pembayaran vendor sewa hardware ' . $sewaHardware->kode_sewa,
                'referensi_tipe' => PembayaranSewaHardware::class,
                'referensi_id' => $pembayaran->id,
                'tanggal' => $pembayaran->paid_at->toDateString(),
            ]
        );
    }

    private function recordVendorRefundMutasi(
        SewaHardware $sewaHardware,
        PembayaranSewaHardware $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah,
        CarbonImmutable $tanggal
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-hardware:refund-vendor:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'masuk',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Refund vendor atas sewa hardware ' . $sewaHardware->kode_sewa,
                'referensi_tipe' => PembayaranSewaHardware::class,
                'referensi_id' => $pembayaran->id,
                'tanggal' => $tanggal->toDateString(),
            ]
        );
    }

    private function recordCompanyRefundMutasi(
        SewaHardware $sewaHardware,
        PembayaranSewaHardware $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah,
        CarbonImmutable $tanggal
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-hardware:refund-perusahaan:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'keluar',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Refund perusahaan atas sewa hardware ' . $sewaHardware->kode_sewa,
                'referensi_tipe' => PembayaranSewaHardware::class,
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
                'selesai_tanggal' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.',
            ]);
        }

        return [$start, $end];
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
