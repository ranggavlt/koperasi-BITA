<?php

namespace App\Services;

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
            $karyawan = Karyawan::query()->lockForUpdate()->findOrFail((int) $data['karyawan_id']);
            $this->assertActiveKaryawan($karyawan);

            $detailRows = $this->buildDetailRows($data['details'] ?? []);
            $totals = $this->calculateTotals($detailRows);
            $createdAt = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));

            $sewa = SewaPrinter::query()->create([
                'kode_sewa' => $this->nextKodeSewa($createdAt),
                'nama_perusahaan_snapshot' => config('koperasi.nama_perusahaan_penyewa', 'Bita Enarcon Engineering'),
                'karyawan_pic_id' => $karyawan->id,
                'mulai_tanggal' => $mulai->toDateString(),
                'selesai_tanggal' => $selesai->toDateString(),
                'kebutuhan' => $this->nullableText($data['kebutuhan'] ?? null),
                'vendor_nama' => $this->normalizeText($data['vendor_nama']),
                'vendor_kontak' => $this->normalizeText($data['vendor_kontak']),
                'vendor_alamat' => $this->normalizeText($data['vendor_alamat']),
                'total_harga_vendor' => $totals['harga_vendor'],
                'total_margin' => $totals['margin'],
                'total_tagihan_perusahaan' => $totals['tagihan'],
                'status' => SewaPrinter::STATUS_DRAFT,
                'status_pembayaran' => SewaPrinter::PEMBAYARAN_BELUM_BAYAR,
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

    public function updateDraft(SewaPrinter $sewaPrinter, array $data, int $financeUserId): SewaPrinter
    {
        return DB::transaction(function () use ($sewaPrinter, $data, $financeUserId): SewaPrinter {
            $locked = SewaPrinter::query()
                ->with('details')
                ->lockForUpdate()
                ->findOrFail($sewaPrinter->id);

            $this->assertStatus($locked, [SewaPrinter::STATUS_DRAFT], 'Kontrak yang sudah dikonfirmasi tidak dapat diedit.');

            [$mulai, $selesai] = $this->normalizePeriod($data['mulai_tanggal'], $data['selesai_tanggal']);
            $karyawan = Karyawan::query()->lockForUpdate()->findOrFail((int) $data['karyawan_id']);
            $this->assertActiveKaryawan($karyawan);

            $detailRows = $this->buildDetailRows($data['details'] ?? []);
            $totals = $this->calculateTotals($detailRows);

            $locked->details->each->delete();
            $locked->details()->createMany($detailRows);

            $locked->update([
                'karyawan_pic_id' => $karyawan->id,
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

    public function confirm(SewaPrinter $sewaPrinter, int $financeUserId): SewaPrinter
    {
        return DB::transaction(function () use ($sewaPrinter, $financeUserId): SewaPrinter {
            $locked = SewaPrinter::query()
                ->with(['details', 'karyawan'])
                ->lockForUpdate()
                ->findOrFail($sewaPrinter->id);

            $this->assertStatus($locked, [SewaPrinter::STATUS_DRAFT], 'Hanya draft Sewa Printer yang dapat dikonfirmasi.');
            $this->assertActiveKaryawan($locked->karyawan);

            if ($locked->details->isEmpty()) {
                throw ValidationException::withMessages([
                    'details' => 'Kontrak Sewa Printer wajib mempunyai minimal satu detail printer.',
                ]);
            }

            $totals = $this->calculateTotals($locked->details->map(fn (SewaPrinterDetail $detail): array => [
                'subtotal_harga_vendor' => $this->rupiahInt($detail->subtotal_harga_vendor),
                'subtotal_margin' => $this->rupiahInt($detail->subtotal_margin),
                'subtotal_tagihan' => $this->rupiahInt($detail->subtotal_tagihan),
            ])->all());

            $locked->update([
                'total_harga_vendor' => $totals['harga_vendor'],
                'total_margin' => $totals['margin'],
                'total_tagihan_perusahaan' => $totals['tagihan'],
                'status' => SewaPrinter::STATUS_DIKONFIRMASI,
                'confirmed_at' => now(),
                'confirmed_by' => $financeUserId,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details', 'karyawan', 'confirmer']);
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

            $pembayaran = PembayaranSewaPrinter::query()->create([
                'sewa_printer_id' => $locked->id,
                'dompet_penerimaan_id' => $dompetPenerimaan->id,
                'dompet_vendor_id' => $dompetVendor->id,
                'metode_penerimaan' => $data['metode_penerimaan'],
                'metode_pembayaran_vendor' => $data['metode_pembayaran_vendor'],
                'jumlah_diterima' => $jumlahDiterima,
                'jumlah_bayar_vendor' => $jumlahBayarVendor,
                'status' => PembayaranSewaPrinter::STATUS_PAID,
                'paid_at' => $paidAt->toDateTimeString(),
                'created_by' => $financeUserId,
                'idempotency_key' => 'sewa-printer:pembayaran:' . $locked->id,
            ]);

            if ((int) $dompetPenerimaan->id === (int) $dompetVendor->id) {
                $this->setSaldoDompet($dompetPenerimaan, $this->rupiahInt($dompetPenerimaan->saldo) + $jumlahDiterima - $jumlahBayarVendor);
            } else {
                $this->setSaldoDompet($dompetPenerimaan, $this->rupiahInt($dompetPenerimaan->saldo) + $jumlahDiterima);
                $this->setSaldoDompet($dompetVendor, $this->rupiahInt($dompetVendor->saldo) - $jumlahBayarVendor);
            }

            $this->recordCompanyReceiptMutasi($locked, $pembayaran, $dompetPenerimaan, $jumlahDiterima);
            $this->recordVendorPaymentMutasi($locked, $pembayaran, $dompetVendor, $jumlahBayarVendor);
            $this->akuntansiService->recordPembayaranDimukaSewaPrinter($locked, $pembayaran, $dompetPenerimaan->akun, $financeUserId);
            $this->akuntansiService->recordPembayaranVendorSewaPrinter($locked, $pembayaran, $dompetVendor->akun, $financeUserId);

            $locked->update([
                'status_pembayaran' => SewaPrinter::PEMBAYARAN_PAID,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details', 'karyawan', 'pembayaran.dompetPenerimaan.akun', 'pembayaran.dompetVendor.akun']);
        });
    }

    public function start(SewaPrinter $sewaPrinter, int $financeUserId): SewaPrinter
    {
        return DB::transaction(function () use ($sewaPrinter, $financeUserId): SewaPrinter {
            $locked = SewaPrinter::query()
                ->with(['karyawan', 'pembayaran'])
                ->lockForUpdate()
                ->findOrFail($sewaPrinter->id);

            $this->assertStatus($locked, [SewaPrinter::STATUS_DIKONFIRMASI], 'Hanya kontrak dikonfirmasi yang dapat dimulai.');
            $this->assertActiveKaryawan($locked->karyawan);

            if ($locked->status_pembayaran !== SewaPrinter::PEMBAYARAN_PAID || ! $locked->pembayaran) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Kontrak wajib dibayar penuh sebelum periode dimulai.',
                ]);
            }

            $locked->update([
                'status' => SewaPrinter::STATUS_BERJALAN,
                'started_at' => now(),
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details', 'karyawan', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor']);
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
                return $locked->fresh(['details', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor', 'jurnal.details']);
            }

            $this->assertStatus($locked, [SewaPrinter::STATUS_BERJALAN], 'Hanya kontrak berjalan yang dapat diselesaikan.');

            if ($locked->status_pembayaran !== SewaPrinter::PEMBAYARAN_PAID || ! $locked->pembayaran) {
                throw ValidationException::withMessages([
                    'pembayaran' => 'Kontrak wajib paid sebelum diselesaikan.',
                ]);
            }

            $locked->update([
                'status' => SewaPrinter::STATUS_SELESAI,
                'completed_at' => now(),
                'updated_by' => $financeUserId,
            ]);

            $this->akuntansiService->recordPengakuanPendapatanSewaPrinter($locked->fresh(), $financeUserId);

            return $locked->fresh(['details', 'karyawan', 'pembayaran.dompetPenerimaan', 'pembayaran.dompetVendor', 'jurnal.details']);
        });
    }

    public function cancelByFinance(SewaPrinter $sewaPrinter, string $reason, int $financeUserId): SewaPrinter
    {
        return DB::transaction(function () use ($sewaPrinter, $reason, $financeUserId): SewaPrinter {
            $locked = SewaPrinter::query()
                ->with('pembayaran')
                ->lockForUpdate()
                ->findOrFail($sewaPrinter->id);

            if ($locked->status === SewaPrinter::STATUS_DIBATALKAN) {
                throw ValidationException::withMessages([
                    'sewa_printer' => 'Kontrak Sewa Printer ini sudah dibatalkan.',
                ]);
            }

            if ($locked->status_pembayaran === SewaPrinter::PEMBAYARAN_PAID || $locked->pembayaran) {
                throw ValidationException::withMessages([
                    'sewa_printer' => 'Kontrak yang sudah paid tidak dapat dibatalkan/refund otomatis. Gunakan proses koreksi Finance manual.',
                ]);
            }

            if (in_array($locked->status, [SewaPrinter::STATUS_BERJALAN, SewaPrinter::STATUS_SELESAI], true)) {
                throw ValidationException::withMessages([
                    'sewa_printer' => 'Kontrak berjalan atau selesai bersifat immutable dan tidak dapat dibatalkan otomatis.',
                ]);
            }

            $this->assertStatus($locked, [SewaPrinter::STATUS_DRAFT, SewaPrinter::STATUS_DIKONFIRMASI], 'Hanya draft atau kontrak confirmed yang belum dibayar dapat dibatalkan.');

            $locked->update([
                'status' => SewaPrinter::STATUS_DIBATALKAN,
                'cancelled_at' => now(),
                'alasan_pembatalan' => $this->normalizeText($reason),
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details', 'karyawan', 'pembayaran']);
        });
    }

    private function buildDetailRows(array $details): array
    {
        if ($details === []) {
            throw ValidationException::withMessages([
                'details' => 'Kontrak Sewa Printer wajib mempunyai minimal satu detail kebutuhan printer.',
            ]);
        }

        return collect($details)
            ->map(function (array $detail): array {
                $jenisModel = $this->normalizeText($detail['jenis_model_printer'] ?? '');
                $kuantitas = (int) ($detail['kuantitas'] ?? 0);
                $hargaVendorPerUnit = $this->rupiahInt($detail['harga_vendor_per_unit'] ?? 0);

                if ($jenisModel === '') {
                    throw ValidationException::withMessages([
                        'details' => 'Jenis/model printer wajib diisi pada setiap baris.',
                    ]);
                }

                if ($kuantitas <= 0) {
                    throw ValidationException::withMessages([
                        'details' => 'Kuantitas printer wajib lebih besar dari nol.',
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
                    'jenis_model_printer' => $jenisModel,
                    'spesifikasi_kebutuhan' => $this->nullableText($detail['spesifikasi_kebutuhan'] ?? null),
                    'kuantitas' => $kuantitas,
                    'harga_vendor_per_unit' => $hargaVendorPerUnit,
                    'margin_persen_snapshot' => SewaPrinterDetail::MARGIN_PERSEN,
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
        return intdiv(($hargaVendorPerUnit * SewaPrinterDetail::MARGIN_PERSEN) + 50, 100);
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

    private function assertActiveKaryawan(?Karyawan $karyawan): void
    {
        if (! $karyawan || $karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'karyawan_id' => 'Sewa Printer hanya untuk Karyawan aktif.',
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
            PembayaranSewaPrinter::METODE_TUNAI => DompetKoperasi::JENIS_KAS,
            PembayaranSewaPrinter::METODE_TRANSFER_BANK => DompetKoperasi::JENIS_BANK,
            default => throw ValidationException::withMessages([$field => 'Metode pembayaran Sewa Printer tidak valid.']),
        };

        if ($dompet->jenis_dompet !== $expected) {
            throw ValidationException::withMessages([
                $field => $metode === PembayaranSewaPrinter::METODE_TUNAI
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
        SewaPrinter $sewaPrinter,
        PembayaranSewaPrinter $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-printer:penerimaan:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'masuk',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Penerimaan perusahaan atas sewa printer ' . $sewaPrinter->kode_sewa,
                'referensi_tipe' => PembayaranSewaPrinter::class,
                'referensi_id' => $pembayaran->id,
                'tanggal' => $pembayaran->paid_at->toDateString(),
            ]
        );
    }

    private function recordVendorPaymentMutasi(
        SewaPrinter $sewaPrinter,
        PembayaranSewaPrinter $pembayaran,
        DompetKoperasi $dompet,
        int $jumlah
    ): MutasiKas {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'sewa-printer:pembayaran-vendor:mutasi:' . $pembayaran->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'keluar',
                'jumlah' => $this->rupiahDecimal($jumlah),
                'keterangan' => 'Pembayaran vendor sewa printer ' . $sewaPrinter->kode_sewa,
                'referensi_tipe' => PembayaranSewaPrinter::class,
                'referensi_id' => $pembayaran->id,
                'tanggal' => $pembayaran->paid_at->toDateString(),
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
