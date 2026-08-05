<?php

namespace App\Services;

use App\Models\DompetKoperasi;
use App\Models\InvoicePenagihan;
use App\Models\InvoicePenagihanDetail;
use App\Models\PembayaranInvoicePenagihan;
use App\Models\PengembalianInvoicePenagihan;
use App\Models\Perusahaan;
use App\Models\SewaHardware;
use App\Models\SewaMobil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoicePenagihanService
{
    public function __construct(
        private readonly AkuntansiService $akuntansiService,
        private readonly MutasiKasService $mutasiKasService,
        private readonly RentalEligibilityService $rentalEligibility,
    ) {
    }

    public function create(array $data, int $userId): InvoicePenagihan
    {
        return DB::transaction(function () use ($data, $userId): InvoicePenagihan {
            $company = Perusahaan::query()->lockForUpdate()->findOrFail((int) $data['perusahaan_id']);
            $references = $this->selectedReferences($data, $company);
            if ($references->isEmpty()) {
                throw ValidationException::withMessages(['kontrak' => 'Pilih minimal satu kontrak yang siap ditagihkan.']);
            }

            foreach ($references as $reference) {
                $decision = $reference instanceof SewaMobil
                    ? $this->rentalEligibility->mobil($reference)
                    : $this->rentalEligibility->hardware($reference);
                if (! $decision['can_invoice']) {
                    throw ValidationException::withMessages(['kontrak' => 'Salah satu kontrak sudah ditagihkan atau tidak lagi siap ditagihkan.']);
                }
            }

            $total = (int) $references->sum(fn ($reference) => (int) $reference->total_tagihan_perusahaan);
            $invoice = InvoicePenagihan::query()->create([
                'nomor_invoice' => $this->nextNumber($company, (string) $data['tanggal_invoice']),
                'perusahaan_id' => $company->id,
                'tanggal_invoice' => $data['tanggal_invoice'],
                'jatuh_tempo' => $data['jatuh_tempo'],
                'total_tagihan' => $total,
                'total_dibayar' => 0,
                'sisa_tagihan' => $total,
                'status' => InvoicePenagihan::STATUS_UNPAID,
                'created_by' => $userId,
                'finalized_at' => now(),
                'idempotency_key' => 'invoice-b2b:' . Str::uuid(),
            ]);

            foreach ($references as $reference) {
                $invoice->detail()->create([
                    'deskripsi' => $reference instanceof SewaMobil
                        ? 'Sewa Mobil ' . $reference->kode_sewa . ' - ' . $reference->vehicle_label
                        : 'Sewa Hardware ' . $reference->kode_sewa,
                    'nominal' => $reference->total_tagihan_perusahaan,
                    'status' => 'aktif',
                    'referensi_type' => $reference::class,
                    'referensi_id' => $reference->id,
                ]);
            }

            $this->akuntansiService->recordInvoiceB2bFinalization($invoice->fresh(['detail.referensi']), $userId);

            return $invoice->fresh(['perusahaan', 'detail.referensi']);
        });
    }

    public function recordPayment(InvoicePenagihan $invoice, array $data, int $userId): PembayaranInvoicePenagihan
    {
        return DB::transaction(function () use ($invoice, $data, $userId): PembayaranInvoicePenagihan {
            $locked = InvoicePenagihan::query()->with(['detail.allocations'])->lockForUpdate()->findOrFail($invoice->id);
            $amount = $this->amount($data['jumlah']);
            $remaining = $this->amount($locked->sisa_tagihan);
            if ($amount <= 0 || $amount > $remaining) {
                throw ValidationException::withMessages(['jumlah' => 'Jumlah pembayaran harus lebih dari nol dan tidak boleh melebihi sisa utang.']);
            }

            $wallet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail((int) $data['dompet_id']);
            $this->assertWallet($wallet, (string) $data['metode']);

            $payment = PembayaranInvoicePenagihan::query()->create([
                'invoice_penagihan_id' => $locked->id,
                'dompet_id' => $wallet->id,
                'metode' => $data['metode'],
                'jumlah' => $amount,
                'tanggal_bayar' => $data['tanggal_bayar'],
                'nomor_referensi' => $data['nomor_referensi'] ?? null,
                'created_by' => $userId,
                'idempotency_key' => 'invoice-b2b:pembayaran:' . Str::uuid(),
            ]);

            $unallocated = $amount;
            foreach ($locked->detail->where('status', 'aktif') as $detail) {
                $paid = (int) $detail->allocations->sum('jumlah');
                $due = max(0, $this->amount($detail->nominal) - $paid);
                $allocation = min($due, $unallocated);
                if ($allocation > 0) {
                    $payment->allocations()->create([
                        'invoice_penagihan_detail_id' => $detail->id,
                        'jumlah' => $allocation,
                    ]);
                    $unallocated -= $allocation;
                }
                if ($unallocated === 0) {
                    break;
                }
            }

            $this->mutasiKasService->record([
                'idempotency_key' => 'invoice-b2b:pembayaran:mutasi:' . $payment->id,
                'dompet_id' => $wallet->id,
                'tipe' => 'masuk',
                'jumlah' => $amount,
                'keterangan' => 'Pembayaran invoice perusahaan ' . $locked->nomor_invoice,
                'referensi_tipe' => PembayaranInvoicePenagihan::class,
                'referensi_id' => $payment->id,
                'tanggal' => $data['tanggal_bayar'],
            ]);
            $this->akuntansiService->recordInvoiceB2bPayment($payment, $userId);
            $this->recalculate($locked);

            return $payment->fresh(['dompet', 'creator', 'allocations']);
        });
    }

    public function refundRental(object $sewa, array $data, int $userId): PengembalianInvoicePenagihan
    {
        return DB::transaction(function () use ($sewa, $data, $userId): PengembalianInvoicePenagihan {
            $lockedRental = $sewa->newQuery()->lockForUpdate()->findOrFail($sewa->id);
            $decision = $lockedRental instanceof SewaMobil
                ? $this->rentalEligibility->mobil($lockedRental)
                : $this->rentalEligibility->hardware($lockedRental);
            if (! $decision['can_refund_company']) {
                throw ValidationException::withMessages(['sewa' => 'Pengembalian dana perusahaan belum tersedia untuk sewa ini.']);
            }

            $detail = InvoicePenagihanDetail::query()
                ->with(['invoice', 'allocations', 'pengembalian', 'referensi'])
                ->where('referensi_type', $lockedRental::class)
                ->where('referensi_id', $lockedRental->id)
                ->lockForUpdate()
                ->firstOrFail();
            $refundable = (int) $detail->allocations->sum('jumlah') - (int) $detail->pengembalian->sum('jumlah');
            if ($refundable <= 0) {
                throw ValidationException::withMessages(['sewa' => 'Tidak ada pembayaran perusahaan yang masih dapat dikembalikan.']);
            }

            $wallet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail((int) $data['dompet_id']);
            $this->assertWallet($wallet, (string) $data['metode']);
            if ($this->amount($wallet->saldo) < $refundable) {
                throw ValidationException::withMessages(['dompet_id' => 'Saldo Dompet tidak cukup untuk mengembalikan dana perusahaan.']);
            }

            $refund = PengembalianInvoicePenagihan::query()->create([
                'invoice_penagihan_detail_id' => $detail->id,
                'dompet_id' => $wallet->id,
                'metode' => $data['metode'],
                'jumlah' => $refundable,
                'tanggal_pengembalian' => $data['tanggal_pengembalian'],
                'nomor_referensi' => $data['nomor_referensi'] ?? null,
                'alasan' => trim((string) $data['alasan']),
                'created_by' => $userId,
                'idempotency_key' => 'invoice-b2b:pengembalian:' . $detail->id,
            ]);

            $this->mutasiKasService->record([
                'idempotency_key' => 'invoice-b2b:pengembalian:mutasi:' . $refund->id,
                'dompet_id' => $wallet->id,
                'tipe' => 'keluar',
                'jumlah' => $refundable,
                'keterangan' => 'Pengembalian dana perusahaan atas ' . $lockedRental->kode_sewa,
                'referensi_tipe' => PengembalianInvoicePenagihan::class,
                'referensi_id' => $refund->id,
                'tanggal' => $data['tanggal_pengembalian'],
            ]);
            $this->akuntansiService->recordInvoiceB2bRefund($refund, $userId);

            $detail->update(['status' => 'dikembalikan', 'total_dikembalikan' => $refundable]);
            $lockedRental->update([
                'status' => 'refunded',
                'status_pembayaran' => 'refunded',
                'refunded_at' => now(),
                'refunded_by' => $userId,
                'refund_reason' => trim((string) $data['alasan']),
            ]);
            $this->recalculate($detail->invoice);

            return $refund->fresh(['detail.invoice', 'dompet']);
        });
    }

    public function recalculate(InvoicePenagihan $invoice): InvoicePenagihan
    {
        $invoice->load(['detail.allocations', 'detail.pengembalian']);
        $active = $invoice->detail->where('status', 'aktif');
        $collectible = (int) $active->sum('nominal');
        $paid = (int) $active->sum(fn ($detail) => $detail->allocations->sum('jumlah'));
        $remaining = max(0, $collectible - $paid);
        $status = $remaining === 0
            ? InvoicePenagihan::STATUS_PAID
            : ($paid > 0 ? InvoicePenagihan::STATUS_PARTIAL : InvoicePenagihan::STATUS_UNPAID);

        $invoice->update(['total_dibayar' => $paid, 'sisa_tagihan' => $remaining, 'status' => $status]);

        return $invoice->fresh();
    }

    private function selectedReferences(array $data, Perusahaan $company)
    {
        $mobilIds = array_map('intval', $data['sewa_mobil_ids'] ?? []);
        $hardwareIds = array_map('intval', $data['sewa_hardware_ids'] ?? []);
        $mobil = SewaMobil::query()
            ->with(['karyawan', 'invoiceDetail', 'pembayaran', 'pembayaranVendorBaru'])
            ->whereIn('id', $mobilIds)
            ->whereHas('karyawan', fn ($query) => $query->where('perusahaan_id', $company->id))
            ->lockForUpdate()->get();
        $hardware = SewaHardware::query()
            ->with(['karyawan', 'invoiceDetail', 'pembayaran', 'pembayaranVendorBaru'])
            ->whereIn('id', $hardwareIds)
            ->whereHas('karyawan', fn ($query) => $query->where('perusahaan_id', $company->id))
            ->lockForUpdate()->get();

        if ($mobil->count() !== count(array_unique($mobilIds)) || $hardware->count() !== count(array_unique($hardwareIds))) {
            throw ValidationException::withMessages(['kontrak' => 'Pilihan kontrak tidak sesuai dengan perusahaan yang dipilih.']);
        }

        return $mobil->concat($hardware);
    }

    private function nextNumber(Perusahaan $company, string $date): string
    {
        $year = substr($date, 0, 4);
        $sequence = InvoicePenagihan::query()->whereYear('tanggal_invoice', $year)->lockForUpdate()->count() + 1;

        return sprintf('INV-%s-%s-%04d', $company->kode, $year, $sequence);
    }

    private function assertWallet(DompetKoperasi $wallet, string $method): void
    {
        $expected = $method === 'tunai' ? DompetKoperasi::JENIS_KAS : DompetKoperasi::JENIS_BANK;
        if ($wallet->jenis_dompet !== $expected || ! $wallet->akun) {
            throw ValidationException::withMessages(['dompet_id' => 'Dompet tidak sesuai dengan metode pembayaran.']);
        }
    }

    private function amount(mixed $value): int
    {
        return (int) explode('.', preg_replace('/[^0-9.]/', '', (string) $value) ?: '0')[0];
    }
}
