<?php

namespace App\Services;

use App\Models\DompetKoperasi;
use App\Models\InvoicePenagihan;
use App\Models\InvoicePenagihanDetail;
use App\Models\MutasiKas;
use App\Models\PembayaranInvoicePerusahaan;
use App\Models\PembayaranVendorSewa;
use App\Models\Perusahaan;
use App\Models\SewaMobil;
use App\Models\SewaHardware;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class B2BRentalService
{
    public function __construct(
        private readonly MutasiKasService $mutasiKasService,
        private readonly AkuntansiService $akuntansiService,
        private readonly AkunResolver $akunResolver
    ) {
    }

    public function payVendor(SewaMobil|SewaHardware $rental, array $data, int $userId): PembayaranVendorSewa
    {
        return DB::transaction(function () use ($rental, $data, $userId): PembayaranVendorSewa {
            $locked = $rental instanceof SewaMobil
                ? SewaMobil::query()->lockForUpdate()->findOrFail($rental->id)
                : SewaHardware::query()->lockForUpdate()->findOrFail($rental->id);

            $existing = PembayaranVendorSewa::query()
                ->where('sewa_type', $locked::class)
                ->where('sewa_id', $locked->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            $this->assertVendorPaymentEligible($locked);
            $snapshot = $this->rentalSnapshot($locked);
            if ($snapshot['vendor_total'] <= 0 || $snapshot['vendor_name'] === '') {
                throw ValidationException::withMessages(['vendor' => 'Snapshot vendor dan harga total vendor wajib tersedia sebelum pembayaran.']);
            }

            $dompet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail((int) ($data['dompet_id'] ?? 0));
            $this->assertOperationalCash($dompet);
            if ((int) $dompet->saldo < $snapshot['vendor_total']) {
                throw ValidationException::withMessages(['dompet_id' => 'Saldo Kas Operasional tidak mencukupi untuk membayar vendor.']);
            }

            $date = CarbonImmutable::parse($data['tanggal_bayar'] ?? now(), config('app.timezone', 'Asia/Jakarta'));
            $payment = PembayaranVendorSewa::query()->create([
                'kode_pembayaran' => $this->nextCode('pembayaran_vendor_sewa', 'PVS', $date),
                'sewa_type' => $locked::class,
                'sewa_id' => $locked->id,
                'dompet_id' => $dompet->id,
                'metode_pembayaran' => 'tunai',
                'jumlah_bayar' => $snapshot['vendor_total'],
                'vendor_nama_snapshot' => $snapshot['vendor_name'],
                'vendor_kontak_snapshot' => $snapshot['vendor_contact'],
                'vendor_alamat_snapshot' => $snapshot['vendor_address'],
                'tanggal_bayar' => $date->toDateString(),
                'status' => PembayaranVendorSewa::STATUS_PAID,
                'created_by' => $userId,
                'idempotency_key' => (string) ($data['idempotency_key'] ?? 'vendor-payment:'.$locked::class.':'.$locked->id),
            ]);

            $this->mutasiKasService->record([
                'idempotency_key' => 'b2b:vendor:mutasi:'.$payment->id,
                'dompet_id' => $dompet->id,
                'tipe' => 'keluar',
                'jumlah' => $snapshot['vendor_total'],
                'keterangan' => 'Pembayaran vendor '.$snapshot['vendor_name'].' untuk '.$snapshot['code'],
                'referensi_tipe' => PembayaranVendorSewa::class,
                'referensi_id' => $payment->id,
                'tanggal' => $date->toDateString(),
            ]);
            $this->akuntansiService->record([
                'idempotency_key' => 'b2b:vendor:jurnal:'.$payment->id,
                'tanggal' => $date->toDateString(),
                'nomor_bukti' => $payment->kode_pembayaran,
                'keterangan' => 'Pembayaran vendor sewa lebih dahulu',
                'referensi_tipe' => PembayaranVendorSewa::class,
                'referensi_id' => $payment->id,
                'created_by' => $userId,
            ], [
                $this->akunResolver->line($this->akunResolver->posting('b2b.uang_muka_vendor'), 'debit', $snapshot['vendor_total']),
                $this->akunResolver->line($dompet->akun, 'kredit', $snapshot['vendor_total']),
            ]);

            return $payment->fresh(['sewa', 'dompet.akun', 'mutasiKas', 'jurnal.details']);
        });
    }

    /**
     * @param array{sewa_mobil_ids?:array<int,int>,sewa_hardware_ids?:array<int,int>,tanggal_invoice?:string,jatuh_tempo?:string,idempotency_key?:string} $data
     */
    public function createInvoice(Perusahaan $company, array $data, int $userId): InvoicePenagihan
    {
        try {
            return DB::transaction(function () use ($company, $data, $userId): InvoicePenagihan {
                $lockedCompany = Perusahaan::query()->lockForUpdate()->findOrFail($company->id);
                $date = CarbonImmutable::parse($data['tanggal_invoice'] ?? now(), config('app.timezone', 'Asia/Jakarta'));
                $key = (string) ($data['idempotency_key'] ?? 'invoice:'.$lockedCompany->id.':'.$date->format('Ym').':'.sha1(json_encode($data)));
                if ($existing = InvoicePenagihan::query()->where('idempotency_key', $key)->first()) {
                    return $existing->load(['detail.referensi', 'pembayaran', 'perusahaan']);
                }

                $sources = collect();
                foreach (array_unique(array_map('intval', $data['sewa_mobil_ids'] ?? [])) as $id) {
                    $sources->push(SewaMobil::query()->with(['karyawan.perusahaan', 'pembayaranVendor', 'invoiceDetail'])->lockForUpdate()->findOrFail($id));
                }
                foreach (array_unique(array_map('intval', $data['sewa_hardware_ids'] ?? [])) as $id) {
                    $sources->push(SewaHardware::query()->with(['karyawan.perusahaan', 'pembayaranVendor', 'invoiceDetail'])->lockForUpdate()->findOrFail($id));
                }
                if ($sources->isEmpty()) {
                    throw ValidationException::withMessages(['transaksi' => 'Pilih minimal satu transaksi sewa yang eligible.']);
                }

                $snapshots = $sources->map(function ($source) use ($lockedCompany): array {
                    $this->assertInvoiceEligible($source, $lockedCompany);
                    return $this->rentalSnapshot($source) + ['source' => $source];
                });
                $total = (int) $snapshots->sum('company_total');
                $invoice = InvoicePenagihan::query()->create([
                    'nomor_invoice' => $this->nextCode('invoice_penagihan', 'INV-'.$lockedCompany->kode, $date),
                    'perusahaan_id' => $lockedCompany->id,
                    'tanggal_invoice' => $date->toDateString(),
                    'jatuh_tempo' => CarbonImmutable::parse($data['jatuh_tempo'] ?? $date->addDays(14))->toDateString(),
                    'total_tagihan' => $total,
                    'jumlah_dibayar' => 0,
                    'sisa_tagihan' => $total,
                    'status' => 'unpaid',
                    'kode_perusahaan_snapshot' => $lockedCompany->kode,
                    'nama_perusahaan_snapshot' => $lockedCompany->nama,
                    'finalized_at' => now(),
                    'created_by' => $userId,
                    'idempotency_key' => $key,
                ]);

                foreach ($snapshots as $snapshot) {
                    InvoicePenagihanDetail::query()->create([
                        'invoice_penagihan_id' => $invoice->id,
                        'deskripsi' => $snapshot['description'],
                        'nominal' => $snapshot['company_total'],
                        'referensi_type' => $snapshot['source']::class,
                        'referensi_id' => $snapshot['source']->id,
                        'kode_sewa_snapshot' => $snapshot['code'],
                        'vendor_nama_snapshot' => $snapshot['vendor_name'],
                        'harga_vendor_snapshot' => $snapshot['vendor_total'],
                        'margin_snapshot' => $snapshot['margin'],
                    ]);
                }

                $vendorTotal = (int) $snapshots->sum('vendor_total');
                $marginMobil = (int) $snapshots->filter(fn ($row) => $row['source'] instanceof SewaMobil)->sum('margin');
                $marginHardware = (int) $snapshots->filter(fn ($row) => $row['source'] instanceof SewaHardware)->sum('margin');
                $lines = [
                    $this->akunResolver->line($this->akunResolver->posting('b2b.piutang_perusahaan'), 'debit', $total),
                    $this->akunResolver->line($this->akunResolver->posting('b2b.uang_muka_vendor'), 'kredit', $vendorTotal),
                ];
                if ($marginMobil > 0) {
                    $lines[] = $this->akunResolver->line($this->akunResolver->posting('sewa_mobil.pendapatan_diterima_dimuka'), 'kredit', $marginMobil);
                }
                if ($marginHardware > 0) {
                    $lines[] = $this->akunResolver->line($this->akunResolver->posting('sewa_hardware.pendapatan_diterima_dimuka_margin'), 'kredit', $marginHardware);
                }
                $this->akuntansiService->record([
                    'idempotency_key' => 'b2b:invoice:jurnal:'.$invoice->id,
                    'tanggal' => $date->toDateString(),
                    'nomor_bukti' => $invoice->nomor_invoice,
                    'keterangan' => 'Finalisasi invoice perusahaan '.$lockedCompany->kode,
                    'referensi_tipe' => InvoicePenagihan::class,
                    'referensi_id' => $invoice->id,
                    'created_by' => $userId,
                ], $lines);

                return $invoice->fresh(['detail.referensi', 'perusahaan', 'pembayaran', 'jurnal.details']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['invoice' => 'Salah satu transaksi sewa sudah masuk invoice lain atau permintaan ini sudah diproses.']);
        }
    }

    public function payInvoice(InvoicePenagihan $invoice, array $data, int $userId): PembayaranInvoicePerusahaan
    {
        return DB::transaction(function () use ($invoice, $data, $userId): PembayaranInvoicePerusahaan {
            $locked = InvoicePenagihan::query()->with('pembayaran')->lockForUpdate()->findOrFail($invoice->id);
            $key = (string) ($data['idempotency_key'] ?? 'invoice-payment:'.$locked->id.':'.sha1(json_encode($data)));
            if ($existing = PembayaranInvoicePerusahaan::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }
            if ($locked->status === 'paid' || (int) $locked->sisa_tagihan <= 0) {
                throw ValidationException::withMessages(['invoice' => 'Invoice sudah lunas.']);
            }

            $amount = $this->rupiahInt($data['jumlah_bayar'] ?? 0);
            if ($amount <= 0 || $amount > (int) $locked->sisa_tagihan) {
                throw ValidationException::withMessages(['jumlah_bayar' => 'Pembayaran harus lebih dari nol dan tidak boleh melebihi sisa tagihan.']);
            }
            $method = (string) ($data['metode_pembayaran'] ?? '');
            $dompet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail((int) ($data['dompet_id'] ?? 0));
            $this->assertReceiptWallet($dompet, $method);
            $date = CarbonImmutable::parse($data['tanggal_bayar'] ?? now(), config('app.timezone', 'Asia/Jakarta'));
            $payment = PembayaranInvoicePerusahaan::query()->create([
                'kode_pembayaran' => $this->nextCode('pembayaran_invoice_perusahaan', 'PIP', $date),
                'invoice_penagihan_id' => $locked->id,
                'dompet_id' => $dompet->id,
                'metode_pembayaran' => $method,
                'jumlah_bayar' => $amount,
                'tanggal_bayar' => $date->toDateString(),
                'nomor_referensi' => trim((string) ($data['nomor_referensi'] ?? '')) ?: null,
                'status' => PembayaranInvoicePerusahaan::STATUS_PAID,
                'created_by' => $userId,
                'idempotency_key' => $key,
            ]);

            $this->mutasiKasService->record([
                'idempotency_key' => 'b2b:invoice-payment:mutasi:'.$payment->id,
                'dompet_id' => $dompet->id,
                'tipe' => 'masuk',
                'jumlah' => $amount,
                'keterangan' => 'Pembayaran perusahaan untuk '.$locked->nomor_invoice,
                'referensi_tipe' => PembayaranInvoicePerusahaan::class,
                'referensi_id' => $payment->id,
                'tanggal' => $date->toDateString(),
            ]);
            $this->akuntansiService->record([
                'idempotency_key' => 'b2b:invoice-payment:jurnal:'.$payment->id,
                'tanggal' => $date->toDateString(),
                'nomor_bukti' => $payment->kode_pembayaran,
                'keterangan' => 'Penerimaan cicilan invoice perusahaan',
                'referensi_tipe' => PembayaranInvoicePerusahaan::class,
                'referensi_id' => $payment->id,
                'created_by' => $userId,
            ], [
                $this->akunResolver->line($dompet->akun, 'debit', $amount),
                $this->akunResolver->line($this->akunResolver->posting('b2b.piutang_perusahaan'), 'kredit', $amount),
            ]);

            $paid = (int) $locked->pembayaran()->where('status', PembayaranInvoicePerusahaan::STATUS_PAID)->sum('jumlah_bayar');
            $remaining = max(0, (int) $locked->total_tagihan - $paid);
            $locked->update(['jumlah_dibayar' => $paid, 'sisa_tagihan' => $remaining, 'status' => $remaining === 0 ? 'paid' : 'partial']);

            return $payment->fresh(['invoice.perusahaan', 'dompet.akun', 'mutasiKas', 'jurnal.details']);
        });
    }

    public function recognizeRentalMargin(SewaMobil|SewaHardware $rental, int $userId): void
    {
        DB::transaction(function () use ($rental, $userId): void {
            $locked = $rental instanceof SewaMobil
                ? SewaMobil::query()->with('invoiceDetail')->lockForUpdate()->findOrFail($rental->id)
                : SewaHardware::query()->with('invoiceDetail')->lockForUpdate()->findOrFail($rental->id);
            if (! $locked->invoiceDetail) {
                throw ValidationException::withMessages(['invoice' => 'Transaksi harus masuk invoice final sebelum pendapatan margin diakui.']);
            }
            $snapshot = $this->rentalSnapshot($locked);
            if ($snapshot['margin'] <= 0) {
                return;
            }
            $isMobil = $locked instanceof SewaMobil;
            $key = 'b2b:margin:jurnal:'.$locked::class.':'.$locked->id;
            if (\App\Models\JurnalUmum::query()->where('idempotency_key', $key)->exists()) {
                return;
            }
            $this->akuntansiService->record([
                'idempotency_key' => $key,
                'tanggal' => now(config('app.timezone', 'Asia/Jakarta'))->toDateString(),
                'nomor_bukti' => 'MRG-'.$snapshot['code'],
                'keterangan' => 'Pengakuan margin '.$snapshot['description'],
                'referensi_tipe' => $locked::class,
                'referensi_id' => $locked->id,
                'created_by' => $userId,
            ], [
                $this->akunResolver->line($this->akunResolver->posting($isMobil ? 'sewa_mobil.pendapatan_diterima_dimuka' : 'sewa_hardware.pendapatan_diterima_dimuka_margin'), 'debit', $snapshot['margin']),
                $this->akunResolver->line($this->akunResolver->posting($isMobil ? 'b2b.pendapatan_sewa_mobil' : 'b2b.pendapatan_margin_hardware'), 'kredit', $snapshot['margin']),
            ]);
        });
    }

    private function assertVendorPaymentEligible(SewaMobil|SewaHardware $rental): void
    {
        $allowed = $rental instanceof SewaMobil
            ? [SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN, SewaMobil::STATUS_SELESAI]
            : [SewaHardware::STATUS_DIKONFIRMASI, SewaHardware::STATUS_BERJALAN, SewaHardware::STATUS_SELESAI];
        if (! in_array($rental->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Vendor hanya boleh dibayar setelah transaksi disetujui/dikonfirmasi.']);
        }
    }

    private function assertInvoiceEligible(SewaMobil|SewaHardware $rental, Perusahaan $company): void
    {
        $this->assertVendorPaymentEligible($rental);
        $companyId = $rental->perusahaan_id ?: $rental->karyawan?->perusahaan_id;
        if ((int) $companyId !== (int) $company->id) {
            throw ValidationException::withMessages(['perusahaan_id' => 'Semua transaksi dalam invoice harus berasal dari perusahaan yang sama.']);
        }
        if (! $rental->pembayaranVendor || $rental->pembayaranVendor->status !== PembayaranVendorSewa::STATUS_PAID) {
            throw ValidationException::withMessages(['vendor' => 'Vendor harus dibayar lebih dahulu sebelum transaksi masuk invoice perusahaan.']);
        }
        if ($rental->invoiceDetail) {
            throw ValidationException::withMessages(['invoice' => 'Transaksi '.$this->rentalSnapshot($rental)['code'].' sudah masuk invoice lain.']);
        }
    }

    private function assertOperationalCash(DompetKoperasi $wallet): void
    {
        if ($wallet->jenis_dompet !== DompetKoperasi::JENIS_KAS || ! $wallet->is_kas_operasional || ! $wallet->akun || ! $wallet->akun->is_aktif || $wallet->akun->kategori !== 'aset' || $wallet->akun->posisi_saldo !== 'debit') {
            throw ValidationException::withMessages(['dompet_id' => 'Pembayaran vendor wajib memakai Kas Operasional dengan COA Aset aktif Debit.']);
        }
    }

    private function assertReceiptWallet(DompetKoperasi $wallet, string $method): void
    {
        $expected = $method === 'tunai' ? DompetKoperasi::JENIS_KAS : ($method === 'transfer_bank' ? DompetKoperasi::JENIS_BANK : null);
        if (! $expected || $wallet->jenis_dompet !== $expected || ! $wallet->akun || ! $wallet->akun->is_aktif || $wallet->akun->kategori !== 'aset' || $wallet->akun->posisi_saldo !== 'debit') {
            throw ValidationException::withMessages(['dompet_id' => 'Dompet penerimaan harus sesuai metode Tunai/Transfer Bank dan mempunyai COA valid.']);
        }
    }

    /** @return array{code:string,vendor_name:string,vendor_contact:?string,vendor_address:?string,vendor_total:int,margin:int,company_total:int,description:string} */
    private function rentalSnapshot(SewaMobil|SewaHardware $rental): array
    {
        if ($rental instanceof SewaMobil) {
            $vendorTotal = (int) ($rental->harga_vendor_total ?: $rental->tarif_harian_snapshot ?: 0);
            $companyTotal = (int) ($rental->total_tagihan_perusahaan ?: $rental->total_sewa ?: 0);
            return [
                'code' => (string) $rental->kode_sewa,
                'vendor_name' => trim((string) ($rental->vendor_nama_snapshot ?: $rental->aset?->vendor?->nama)),
                'vendor_contact' => $rental->vendor_kontak_snapshot,
                'vendor_address' => $rental->vendor_alamat_snapshot,
                'vendor_total' => $vendorTotal,
                'margin' => max(0, (int) ($rental->markup_total ?: $companyTotal - $vendorTotal)),
                'company_total' => $companyTotal,
                'description' => 'Sewa Mobil '.$rental->kode_sewa.' - '.($rental->kendaraan_merk_tipe_snapshot ?: 'kendaraan vendor'),
            ];
        }

        return [
            'code' => (string) $rental->kode_sewa,
            'vendor_name' => trim((string) $rental->vendor_nama),
            'vendor_contact' => $rental->vendor_kontak,
            'vendor_address' => $rental->vendor_alamat,
            'vendor_total' => (int) $rental->total_harga_vendor,
            'margin' => (int) $rental->total_margin,
            'company_total' => (int) $rental->total_tagihan_perusahaan,
            'description' => 'Sewa Hardware '.$rental->kode_sewa,
        ];
    }

    private function nextCode(string $type, string $prefix, CarbonImmutable $date): string
    {
        $period = $date->format('Ym');
        try {
            DB::table('nomor_urut_transaksi')->insert(['jenis' => $type, 'periode' => $period, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
        } catch (QueryException) {
        }
        $counter = DB::table('nomor_urut_transaksi')->where('jenis', $type)->where('periode', $period)->lockForUpdate()->first();
        if (! $counter) {
            throw new RuntimeException('Counter nomor transaksi B2B tidak tersedia.');
        }
        $next = (int) $counter->last_number + 1;
        DB::table('nomor_urut_transaksi')->where('id', $counter->id)->update(['last_number' => $next, 'updated_at' => now()]);

        return sprintf('%s-%s-%06d', $prefix, $period, $next);
    }

    private function rupiahInt(mixed $value): int
    {
        return (int) preg_replace('/[^\d]/', '', explode('.', trim((string) $value))[0] ?? '0');
    }
}
