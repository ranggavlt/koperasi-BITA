<?php

namespace App\Services;

use App\Models\AsetKoperasi;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PembayaranSewaPrinter;
use App\Models\SewaPrinter;
use App\Models\SewaPrinterDetail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SewaPrinterService
{
    public function __construct(
        private readonly AkuntansiService $akuntansiService
    ) {
    }

    public function createDraft(array $data, int $financeUserId): SewaPrinter
    {
        return DB::transaction(function () use ($data, $financeUserId): SewaPrinter {
            [$mulai, $selesai] = $this->normalizePeriod($data['mulai_tanggal'], $data['selesai_tanggal']);
            $pic = Karyawan::query()->lockForUpdate()->findOrFail((int) $data['karyawan_pic_id']);
            $this->assertActiveKaryawan($pic);

            $assets = $this->lockAssetsForDetails($data['details'] ?? []);
            $detailRows = $this->buildDetailRows($assets, $data['details'] ?? []);
            $totals = $this->calculateTotals($detailRows);
            $createdAt = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));

            $sewa = SewaPrinter::query()->create([
                'kode_sewa' => $this->nextKodeSewa($createdAt),
                'nama_perusahaan_snapshot' => config('koperasi.nama_perusahaan_penyewa', 'Bita Enarcon Engineering'),
                'karyawan_pic_id' => $pic->id,
                'mulai_tanggal' => $mulai->toDateString(),
                'selesai_tanggal' => $selesai->toDateString(),
                'total_harga_dasar' => $this->rupiahDecimal($totals['harga_dasar']),
                'total_margin' => $this->rupiahDecimal($totals['margin']),
                'grand_total' => $this->rupiahDecimal($totals['grand_total']),
                'status' => SewaPrinter::STATUS_DRAFT,
                'status_pembayaran' => SewaPrinter::PEMBAYARAN_BELUM_BAYAR,
                'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                'created_by' => $financeUserId,
                'updated_by' => $financeUserId,
                'idempotency_key' => $data['idempotency_key'] ?? (string) Str::uuid(),
            ]);

            $sewa->details()->createMany($detailRows);

            return $sewa->fresh(['details.aset.printer', 'karyawanPic']);
        });
    }

    public function updateDraft(SewaPrinter $sewaPrinter, array $data, int $financeUserId): SewaPrinter
    {
        return DB::transaction(function () use ($sewaPrinter, $data, $financeUserId): SewaPrinter {
            $locked = SewaPrinter::query()
                ->with('details')
                ->lockForUpdate()
                ->findOrFail($sewaPrinter->id);

            $this->assertStatus($locked, [SewaPrinter::STATUS_DRAFT], 'Kontrak yang sudah dikonfirmasi tidak dapat diedit.');

            [$mulai, $selesai] = $this->normalizePeriod($data['mulai_tanggal'], $data['selesai_tanggal']);
            $pic = Karyawan::query()->lockForUpdate()->findOrFail((int) $data['karyawan_pic_id']);
            $this->assertActiveKaryawan($pic);

            $assets = $this->lockAssetsForDetails($data['details'] ?? []);
            $detailRows = $this->buildDetailRows($assets, $data['details'] ?? []);
            $totals = $this->calculateTotals($detailRows);

            $locked->details->each->delete();
            $locked->details()->createMany($detailRows);

            $locked->update([
                'karyawan_pic_id' => $pic->id,
                'mulai_tanggal' => $mulai->toDateString(),
                'selesai_tanggal' => $selesai->toDateString(),
                'total_harga_dasar' => $this->rupiahDecimal($totals['harga_dasar']),
                'total_margin' => $this->rupiahDecimal($totals['margin']),
                'grand_total' => $this->rupiahDecimal($totals['grand_total']),
                'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details.aset.printer', 'karyawanPic']);
        });
    }

    public function confirm(SewaPrinter $sewaPrinter, int $financeUserId): SewaPrinter
    {
        return DB::transaction(function () use ($sewaPrinter, $financeUserId): SewaPrinter {
            $locked = SewaPrinter::query()
                ->with('details.aset.printer', 'karyawanPic')
                ->lockForUpdate()
                ->findOrFail($sewaPrinter->id);

            $this->assertStatus($locked, [SewaPrinter::STATUS_DRAFT], 'Hanya draft Sewa Printer yang dapat dikonfirmasi.');
            $this->assertActiveKaryawan($locked->karyawanPic);

            if ($locked->details->isEmpty()) {
                throw ValidationException::withMessages([
                    'details' => 'Kontrak Sewa Printer wajib mempunyai minimal satu Printer.',
                ]);
            }

            $assets = $this->lockAssetsByIds($locked->details->pluck('aset_koperasi_id')->all());
            $detailRows = $this->buildDetailRowsFromExisting($assets, $locked->details);
            $totals = $this->calculateTotals($detailRows);

            $conflict = $this->firstOverlapConflict($locked);
            if ($conflict) {
                throw ValidationException::withMessages([
                    'details' => "Printer {$conflict['kode_aset']} sudah dipakai kontrak {$conflict['kode_sewa']} pada periode beririsan.",
                ]);
            }

            foreach ($detailRows as $row) {
                SewaPrinterDetail::query()
                    ->where('sewa_printer_id', $locked->id)
                    ->where('aset_koperasi_id', $row['aset_koperasi_id'])
                    ->update($row);
            }

            $locked->update([
                'total_harga_dasar' => $this->rupiahDecimal($totals['harga_dasar']),
                'total_margin' => $this->rupiahDecimal($totals['margin']),
                'grand_total' => $this->rupiahDecimal($totals['grand_total']),
                'status' => SewaPrinter::STATUS_DIKONFIRMASI,
                'confirmed_at' => now(),
                'confirmed_by' => $financeUserId,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details.aset.printer', 'karyawanPic', 'confirmer']);
        });
    }

    public function pay(SewaPrinter $sewaPrinter, array $data, int $financeUserId): SewaPrinter
    {
        return DB::transaction(function () use ($sewaPrinter, $data, $financeUserId): SewaPrinter {
            $locked = SewaPrinter::query()
                ->with('pembayaran')
                ->lockForUpdate()
                ->findOrFail($sewaPrinter->id);

            $this->assertStatus($locked, [SewaPrinter::STATUS_DIKONFIRMASI], 'Pembayaran hanya untuk kontrak yang sudah dikonfirmasi.');

            if ($locked->status_pembayaran !== SewaPrinter::PEMBAYARAN_BELUM_BAYAR || $locked->pembayaran) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Kontrak ini sudah mempunyai pembayaran final.',
                ]);
            }

            $jumlah = $this->rupiahInt($data['jumlah_bayar']);
            $grandTotal = $this->rupiahInt($locked->grand_total);

            if ($jumlah !== $grandTotal) {
                throw ValidationException::withMessages([
                    'jumlah_bayar' => 'Pembayaran Sewa Printer wajib penuh sesuai grand total. Pembayaran sebagian tidak diperbolehkan.',
                ]);
            }

            $paidAt = isset($data['paid_at'])
                ? $this->normalizeDateTime($data['paid_at'])
                : CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));

            if ($paidAt->toDateString() >= $locked->mulai_tanggal->toDateString()) {
                throw ValidationException::withMessages([
                    'paid_at' => 'Pembayaran Sewa Printer wajib diterima sebelum tanggal mulai periode sewa.',
                ]);
            }

            $dompet = DompetKoperasi::query()
                ->with('akun')
                ->lockForUpdate()
                ->findOrFail((int) $data['dompet_id']);
            $this->assertDompetForPayment($dompet, $data['metode_pembayaran']);

            $pembayaran = PembayaranSewaPrinter::query()->create([
                'sewa_printer_id' => $locked->id,
                'dompet_id' => $dompet->id,
                'metode_pembayaran' => $data['metode_pembayaran'],
                'jumlah_bayar' => $this->rupiahDecimal($jumlah),
                'status' => PembayaranSewaPrinter::STATUS_PAID,
                'paid_at' => $paidAt->toDateTimeString(),
                'created_by' => $financeUserId,
                'idempotency_key' => 'sewa-printer:pembayaran:' . $locked->id,
            ]);

            $this->increaseSaldoDompet($dompet, $jumlah);
            $this->recordPaymentMutasi($locked, $pembayaran, $dompet, $jumlah);
            $this->akuntansiService->recordPembayaranDimukaSewaPrinter($locked, $pembayaran, $dompet->akun, $financeUserId);

            $locked->update([
                'status_pembayaran' => SewaPrinter::PEMBAYARAN_PAID,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details.aset.printer', 'karyawanPic', 'pembayaran.dompet.akun']);
        });
    }

    public function start(SewaPrinter $sewaPrinter, int $financeUserId): SewaPrinter
    {
        return DB::transaction(function () use ($sewaPrinter, $financeUserId): SewaPrinter {
            $locked = SewaPrinter::query()
                ->with(['details', 'karyawanPic', 'pembayaran'])
                ->lockForUpdate()
                ->findOrFail($sewaPrinter->id);

            $this->assertStatus($locked, [SewaPrinter::STATUS_DIKONFIRMASI], 'Hanya kontrak dikonfirmasi yang dapat dimulai.');
            $this->assertActiveKaryawan($locked->karyawanPic);

            if ($locked->status_pembayaran !== SewaPrinter::PEMBAYARAN_PAID || ! $locked->pembayaran) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Kontrak wajib dibayar penuh sebelum periode dimulai.',
                ]);
            }

            $assets = $this->lockAssetsByIds($locked->details->pluck('aset_koperasi_id')->all());
            foreach ($assets as $aset) {
                $this->assertStartableAsset($aset);
            }

            $runningConflict = $this->hasOtherRunning($locked);
            if ($runningConflict) {
                throw ValidationException::withMessages([
                    'details' => 'Masih ada kontrak Sewa Printer lain yang sedang berjalan pada salah satu Printer.',
                ]);
            }

            $locked->update([
                'status' => SewaPrinter::STATUS_BERJALAN,
                'started_at' => now(),
                'updated_by' => $financeUserId,
            ]);

            foreach ($assets as $aset) {
                $aset->update([
                    'status' => AsetKoperasi::STATUS_DIGUNAKAN_DISEWA,
                    'updated_by' => $financeUserId,
                ]);
            }

            return $locked->fresh(['details.aset.printer', 'karyawanPic', 'pembayaran.dompet']);
        });
    }

    public function complete(SewaPrinter $sewaPrinter, int $financeUserId): SewaPrinter
    {
        return DB::transaction(function () use ($sewaPrinter, $financeUserId): SewaPrinter {
            $locked = SewaPrinter::query()
                ->with(['details', 'pembayaran'])
                ->lockForUpdate()
                ->findOrFail($sewaPrinter->id);

            if ($locked->status === SewaPrinter::STATUS_SELESAI) {
                return $locked->fresh(['details.aset.printer', 'pembayaran.dompet', 'jurnal.details']);
            }

            $this->assertStatus($locked, [SewaPrinter::STATUS_BERJALAN], 'Hanya kontrak berjalan yang dapat diselesaikan.');

            if ($locked->status_pembayaran !== SewaPrinter::PEMBAYARAN_PAID || ! $locked->pembayaran) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Kontrak wajib paid sebelum diselesaikan.',
                ]);
            }

            $assets = $this->lockAssetsByIds($locked->details->pluck('aset_koperasi_id')->all());

            $locked->update([
                'status' => SewaPrinter::STATUS_SELESAI,
                'completed_at' => now(),
                'updated_by' => $financeUserId,
            ]);

            foreach ($assets as $aset) {
                if (! $this->assetHasOtherRunning($aset->id, $locked->id)) {
                    $aset->update([
                        'status' => AsetKoperasi::STATUS_TERSEDIA,
                        'updated_by' => $financeUserId,
                        'nonaktif_at' => null,
                        'nonaktif_by' => null,
                    ]);
                }
            }

            $this->akuntansiService->recordPengakuanPendapatanSewaPrinter($locked->fresh(), $financeUserId);

            return $locked->fresh(['details.aset.printer', 'karyawanPic', 'pembayaran.dompet', 'jurnal.details']);
        });
    }

    public function cancelByFinance(SewaPrinter $sewaPrinter, string $reason, int $financeUserId): SewaPrinter
    {
        return DB::transaction(function () use ($sewaPrinter, $reason, $financeUserId): SewaPrinter {
            $locked = SewaPrinter::query()
                ->with(['pembayaran.dompet.akun'])
                ->lockForUpdate()
                ->findOrFail($sewaPrinter->id);

            if (in_array($locked->status, [SewaPrinter::STATUS_BERJALAN, SewaPrinter::STATUS_SELESAI], true)) {
                throw ValidationException::withMessages([
                    'sewa_printer' => 'Kontrak berjalan atau selesai tidak dapat dibatalkan otomatis. Gunakan proses koreksi Finance terpisah.',
                ]);
            }

            if ($locked->status === SewaPrinter::STATUS_DIBATALKAN) {
                throw ValidationException::withMessages([
                    'sewa_printer' => 'Kontrak Sewa Printer ini sudah dibatalkan/refund.',
                ]);
            }

            if ($locked->status_pembayaran === SewaPrinter::PEMBAYARAN_PAID && $locked->pembayaran) {
                $this->refundPaidSewa($locked, $locked->pembayaran, $reason, $financeUserId);
            } else {
                $locked->update([
                    'status' => SewaPrinter::STATUS_DIBATALKAN,
                    'cancelled_at' => now(),
                    'alasan_pembatalan' => $this->normalizeText($reason),
                    'updated_by' => $financeUserId,
                ]);
            }

            return $locked->fresh(['details.aset.printer', 'karyawanPic', 'pembayaran.dompet']);
        });
    }

    private function refundPaidSewa(
        SewaPrinter $sewaPrinter,
        PembayaranSewaPrinter $pembayaran,
        string $reason,
        int $financeUserId
    ): void {
        if ($pembayaran->status === PembayaranSewaPrinter::STATUS_REFUNDED) {
            throw ValidationException::withMessages([
                'refund' => 'Pembayaran Sewa Printer ini sudah pernah direfund.',
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
            'status' => PembayaranSewaPrinter::STATUS_REFUNDED,
            'refunded_at' => now(),
        ]);

        $this->recordRefundMutasi($sewaPrinter, $pembayaran, $dompet, $jumlah);
        $this->akuntansiService->recordRefundSewaPrinter($sewaPrinter, $pembayaran->fresh(), $dompet->akun, $financeUserId);

        $sewaPrinter->update([
            'status' => SewaPrinter::STATUS_DIBATALKAN,
            'status_pembayaran' => SewaPrinter::PEMBAYARAN_REFUNDED,
            'cancelled_at' => now(),
            'alasan_pembatalan' => $this->normalizeText($reason),
            'updated_by' => $financeUserId,
        ]);
    }

    private function lockAssetsForDetails(array $details): Collection
    {
        if ($details === []) {
            throw ValidationException::withMessages([
                'details' => 'Kontrak Sewa Printer wajib mempunyai minimal satu Printer.',
            ]);
        }

        $ids = collect($details)
            ->pluck('aset_koperasi_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($ids->count() !== $ids->unique()->count()) {
            throw ValidationException::withMessages([
                'details' => 'Satu Printer tidak boleh dimasukkan lebih dari sekali dalam kontrak yang sama.',
            ]);
        }

        return $this->lockAssetsByIds($ids->all());
    }

    private function lockAssetsByIds(array $ids): Collection
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->unique()->sort()->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Kontrak Sewa Printer wajib mempunyai minimal satu Printer.',
            ]);
        }

        $assets = AsetKoperasi::query()
            ->with('printer')
            ->whereIn('id', $ids->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($assets->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'details' => 'Salah satu Printer tidak ditemukan.',
            ]);
        }

        foreach ($assets as $asset) {
            $this->assertPrinterAsset($asset);
        }

        return $assets;
    }

    private function buildDetailRows(Collection $assets, array $details): array
    {
        return collect($details)
            ->map(function (array $detail) use ($assets): array {
                $aset = $assets->get((int) $detail['aset_koperasi_id']);
                $hargaDasar = $this->rupiahInt($detail['harga_dasar'] ?? 0);

                if ($hargaDasar <= 0) {
                    throw ValidationException::withMessages([
                        'details' => 'Harga dasar setiap Printer wajib lebih besar dari nol.',
                    ]);
                }

                return $this->detailSnapshotRow($aset, $hargaDasar);
            })
            ->values()
            ->all();
    }

    private function buildDetailRowsFromExisting(Collection $assets, Collection $details): array
    {
        return $details
            ->map(function (SewaPrinterDetail $detail) use ($assets): array {
                $aset = $assets->get((int) $detail->aset_koperasi_id);
                $hargaDasar = $this->rupiahInt($detail->harga_dasar);

                if ($hargaDasar <= 0) {
                    throw ValidationException::withMessages([
                        'details' => 'Harga dasar setiap Printer wajib lebih besar dari nol.',
                    ]);
                }

                return $this->detailSnapshotRow($aset, $hargaDasar);
            })
            ->values()
            ->all();
    }

    private function detailSnapshotRow(AsetKoperasi $aset, int $hargaDasar): array
    {
        $margin = $this->calculateMargin($hargaDasar);

        return [
            'aset_koperasi_id' => $aset->id,
            'kode_aset_snapshot' => $aset->kode_aset,
            'nomor_seri_snapshot' => (string) $aset->printer->nomor_seri,
            'merek_snapshot' => $aset->merek,
            'model_snapshot' => $aset->model,
            'harga_dasar' => $this->rupiahDecimal($hargaDasar),
            'margin_persen_snapshot' => $this->rupiahDecimal(SewaPrinterDetail::MARGIN_PERSEN),
            'margin_nominal' => $this->rupiahDecimal($margin),
            'total_harga' => $this->rupiahDecimal($hargaDasar + $margin),
        ];
    }

    private function calculateTotals(array $detailRows): array
    {
        $hargaDasar = 0;
        $margin = 0;
        $grandTotal = 0;

        foreach ($detailRows as $row) {
            $hargaDasar += $this->rupiahInt($row['harga_dasar']);
            $margin += $this->rupiahInt($row['margin_nominal']);
            $grandTotal += $this->rupiahInt($row['total_harga']);
        }

        return [
            'harga_dasar' => $hargaDasar,
            'margin' => $margin,
            'grand_total' => $grandTotal,
        ];
    }

    private function calculateMargin(int $hargaDasar): int
    {
        return intdiv(($hargaDasar * SewaPrinterDetail::MARGIN_PERSEN) + 50, 100);
    }

    private function firstOverlapConflict(SewaPrinter $sewaPrinter): ?array
    {
        $assetIds = $sewaPrinter->details->pluck('aset_koperasi_id')->all();

        $conflict = SewaPrinterDetail::query()
            ->join('sewa_printer as s', 's.id', '=', 'sewa_printer_detail.sewa_printer_id')
            ->whereIn('sewa_printer_detail.aset_koperasi_id', $assetIds)
            ->where('s.id', '!=', $sewaPrinter->id)
            ->whereIn('s.status', [SewaPrinter::STATUS_DIKONFIRMASI, SewaPrinter::STATUS_BERJALAN])
            ->where('s.mulai_tanggal', '<=', $sewaPrinter->selesai_tanggal->toDateString())
            ->where('s.selesai_tanggal', '>=', $sewaPrinter->mulai_tanggal->toDateString())
            ->lockForUpdate()
            ->first([
                's.kode_sewa',
                'sewa_printer_detail.kode_aset_snapshot',
            ]);

        return $conflict ? [
            'kode_sewa' => $conflict->kode_sewa,
            'kode_aset' => $conflict->kode_aset_snapshot,
        ] : null;
    }

    private function hasOtherRunning(SewaPrinter $sewaPrinter): bool
    {
        $assetIds = $sewaPrinter->details->pluck('aset_koperasi_id')->all();

        return SewaPrinterDetail::query()
            ->join('sewa_printer as s', 's.id', '=', 'sewa_printer_detail.sewa_printer_id')
            ->whereIn('sewa_printer_detail.aset_koperasi_id', $assetIds)
            ->where('s.id', '!=', $sewaPrinter->id)
            ->where('s.status', SewaPrinter::STATUS_BERJALAN)
            ->lockForUpdate()
            ->exists();
    }

    private function assetHasOtherRunning(int $assetId, int $currentSewaId): bool
    {
        return SewaPrinterDetail::query()
            ->join('sewa_printer as s', 's.id', '=', 'sewa_printer_detail.sewa_printer_id')
            ->where('sewa_printer_detail.aset_koperasi_id', $assetId)
            ->where('s.id', '!=', $currentSewaId)
            ->where('s.status', SewaPrinter::STATUS_BERJALAN)
            ->lockForUpdate()
            ->exists();
    }

    private function nextKodeSewa(CarbonImmutable $createdAt): string
    {
        $periode = $createdAt
            ->setTimezone(config('app.timezone', 'Asia/Jakarta'))
            ->format('Ym');

        DB::table('nomor_urut_transaksi')->insertOrIgnore([
            'jenis' => 'sewa_printer',
            'periode' => $periode,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DB::table('nomor_urut_transaksi')
            ->where('jenis', 'sewa_printer')
            ->where('periode', $periode)
            ->lockForUpdate()
            ->first();

        $next = ((int) $counter->last_number) + 1;

        DB::table('nomor_urut_transaksi')
            ->where('jenis', 'sewa_printer')
            ->where('periode', $periode)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        return sprintf('SWP-%s-%06d', $periode, $next);
    }

    private function assertPrinterAsset(AsetKoperasi $aset): void
    {
        if ($aset->jenis_aset !== AsetKoperasi::JENIS_PRINTER || ! $aset->printer) {
            throw ValidationException::withMessages([
                'details' => 'Aset yang dipilih harus Printer Koperasi.',
            ]);
        }

        if (in_array($aset->status, [AsetKoperasi::STATUS_NONAKTIF, AsetKoperasi::STATUS_PERAWATAN], true)) {
            throw ValidationException::withMessages([
                'details' => 'Printer nonaktif atau perawatan tidak dapat dikontrakkan.',
            ]);
        }
    }

    private function assertStartableAsset(AsetKoperasi $aset): void
    {
        $this->assertPrinterAsset($aset);

        if ($aset->status !== AsetKoperasi::STATUS_TERSEDIA) {
            throw ValidationException::withMessages([
                'details' => 'Printer harus berstatus tersedia saat kontrak dimulai.',
            ]);
        }
    }

    private function assertActiveKaryawan(?Karyawan $karyawan): void
    {
        if (! $karyawan || $karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'karyawan_pic_id' => 'PIC Sewa Printer wajib Karyawan aktif.',
            ]);
        }
    }

    private function assertStatus(SewaPrinter $sewaPrinter, array $allowed, string $message): void
    {
        if (! in_array($sewaPrinter->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => $message,
            ]);
        }
    }

    private function assertDompetForPayment(DompetKoperasi $dompet, string $metode): void
    {
        $expected = match ($metode) {
            PembayaranSewaPrinter::METODE_TUNAI => DompetKoperasi::JENIS_KAS,
            PembayaranSewaPrinter::METODE_TRANSFER_BANK => DompetKoperasi::JENIS_BANK,
            default => throw ValidationException::withMessages(['metode_pembayaran' => 'Metode pembayaran Sewa Printer tidak valid.']),
        };

        if ($dompet->jenis_dompet !== $expected) {
            throw ValidationException::withMessages([
                'dompet_id' => $metode === PembayaranSewaPrinter::METODE_TUNAI
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
        SewaPrinter $sewaPrinter,
        PembayaranSewaPrinter $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-printer:pembayaran:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'masuk',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Pembayaran dimuka sewa printer ' . $sewaPrinter->kode_sewa,
                'referensi_tipe' => PembayaranSewaPrinter::class,
                'referensi_id' => $pembayaran->id,
                'tanggal' => $pembayaran->paid_at->toDateString(),
            ]
        );
    }

    private function recordRefundMutasi(
        SewaPrinter $sewaPrinter,
        PembayaranSewaPrinter $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-printer:refund:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'keluar',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Refund penuh sewa printer ' . $sewaPrinter->kode_sewa,
                'referensi_tipe' => PembayaranSewaPrinter::class,
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
