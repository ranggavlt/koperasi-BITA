<?php

namespace App\Services;

use App\Models\DompetKoperasi;
use App\Models\PembayaranVendorSewa;
use App\Models\ReversalTransaksi;
use App\Models\SewaHardware;
use App\Models\SewaMobil;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorRentalPaymentService
{
    public function __construct(
        private readonly RentalEligibilityService $eligibility,
        private readonly MutasiKasService $mutasiKasService,
        private readonly AkuntansiService $akuntansiService,
    ) {
    }

    public function pay(Model $sewa, array $data, int $userId): PembayaranVendorSewa
    {
        return DB::transaction(function () use ($sewa, $data, $userId): PembayaranVendorSewa {
            $locked = $sewa->newQuery()->lockForUpdate()->findOrFail($sewa->getKey());
            $decision = $this->decision($locked);
            if (! $decision['can_pay_vendor']) {
                throw ValidationException::withMessages(['sewa' => 'Pembayaran vendor tidak tersedia pada kondisi sewa ini.']);
            }

            if (! $locked->invoiceDetail()->exists()) {
                throw ValidationException::withMessages([
                    'sewa' => 'Buat invoice perusahaan terlebih dahulu agar utang vendor dan piutang tercatat berimbang.',
                ]);
            }

            $amount = $this->amount($data['jumlah'] ?? 0);
            $expected = $this->amount($locked->total_harga_vendor);
            if ($amount !== $expected) {
                throw ValidationException::withMessages(['jumlah' => 'Pembayaran vendor harus sama dengan total biaya vendor.']);
            }

            $wallet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail((int) $data['dompet_id']);
            $this->assertWallet($wallet, (string) $data['metode']);
            if ($this->amount($wallet->saldo) < $amount) {
                throw ValidationException::withMessages(['dompet_id' => 'Saldo Dompet tidak cukup untuk membayar vendor.']);
            }

            $payment = PembayaranVendorSewa::query()->firstOrCreate([
                'sewa_type' => $locked::class,
                'sewa_id' => $locked->id,
            ], [
                'dompet_id' => $wallet->id,
                'metode' => $data['metode'],
                'jumlah' => $amount,
                'tanggal_bayar' => $data['tanggal_bayar'],
                'nomor_referensi' => $data['nomor_referensi'] ?? null,
                'status' => PembayaranVendorSewa::STATUS_DIBAYAR,
                'created_by' => $userId,
                'idempotency_key' => 'sewa:vendor:pembayaran:' . $locked::class . ':' . $locked->id,
            ]);

            $this->mutasiKasService->record([
                'idempotency_key' => 'sewa:vendor:pembayaran:mutasi:' . $payment->id,
                'dompet_id' => $wallet->id,
                'tipe' => 'keluar',
                'jumlah' => $amount,
                'keterangan' => 'Pembayaran vendor untuk ' . $locked->kode_sewa,
                'referensi_tipe' => PembayaranVendorSewa::class,
                'referensi_id' => $payment->id,
                'tanggal' => $data['tanggal_bayar'],
            ]);
            $this->akuntansiService->recordVendorRentalPayment($payment, $userId);

            return $payment->fresh(['sewa', 'dompet']);
        });
    }

    public function requestRefund(Model $sewa, string $reason, int $userId): PembayaranVendorSewa
    {
        return DB::transaction(function () use ($sewa, $reason, $userId): PembayaranVendorSewa {
            $locked = $sewa->newQuery()->lockForUpdate()->findOrFail($sewa->getKey());
            if (! $this->decision($locked)['can_request_vendor_refund']) {
                throw ValidationException::withMessages(['sewa' => 'Permintaan pengembalian vendor tidak tersedia pada kondisi sewa ini.']);
            }

            $payment = PembayaranVendorSewa::query()
                ->where('sewa_type', $locked::class)
                ->where('sewa_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw ValidationException::withMessages([
                    'sewa' => 'Pembayaran lama harus diselesaikan melalui alur refund lama karena belum terpisah dari penerimaan perusahaan.',
                ]);
            }

            $payment->update([
                'status' => PembayaranVendorSewa::STATUS_MENUNGGU_PENGEMBALIAN,
                'alasan_pengembalian' => trim($reason),
                'diminta_kembali_pada' => now(),
                'diminta_kembali_oleh' => $userId,
            ]);

            return $payment->fresh();
        });
    }

    public function confirmRefund(Model $sewa, array $data, int $userId): PembayaranVendorSewa
    {
        return DB::transaction(function () use ($sewa, $data, $userId): PembayaranVendorSewa {
            $locked = $sewa->newQuery()->lockForUpdate()->findOrFail($sewa->getKey());
            $payment = PembayaranVendorSewa::query()
                ->with(['dompet.akun'])
                ->where('sewa_type', $locked::class)
                ->where('sewa_id', $locked->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === PembayaranVendorSewa::STATUS_DIKEMBALIKAN) {
                return $payment;
            }
            if ($payment->status !== PembayaranVendorSewa::STATUS_MENUNGGU_PENGEMBALIAN) {
                throw ValidationException::withMessages(['sewa' => 'Dana vendor belum dalam status menunggu pengembalian.']);
            }

            $payment->update([
                'status' => PembayaranVendorSewa::STATUS_DIKEMBALIKAN,
                'dikembalikan_pada' => $data['tanggal_pengembalian'] . ' 12:00:00',
                'dikembalikan_oleh' => $userId,
                'nomor_referensi' => $data['nomor_referensi'] ?? $payment->nomor_referensi,
            ]);

            $reversal = ReversalTransaksi::query()->firstOrCreate([
                'idempotency_key' => 'sewa:vendor:pengembalian:reversal:' . $payment->id,
            ], [
                'kode_reversal' => 'REV-VND-' . str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT),
                'source_type' => PembayaranVendorSewa::class,
                'source_id' => $payment->id,
                'jenis_reversal' => 'pengembalian_dana_vendor_sewa',
                'nominal' => $payment->jumlah,
                'alasan' => $payment->alasan_pengembalian,
                'status' => ReversalTransaksi::STATUS_PROCESSED,
                'created_by' => $userId,
                'processed_by' => $userId,
                'processed_at' => now(),
            ]);

            $this->mutasiKasService->record([
                'idempotency_key' => 'sewa:vendor:pengembalian:mutasi:' . $payment->id,
                'dompet_id' => $payment->dompet_id,
                'tipe' => 'masuk',
                'jumlah' => $payment->jumlah,
                'keterangan' => 'Pengembalian dana vendor untuk ' . $locked->kode_sewa,
                'referensi_tipe' => ReversalTransaksi::class,
                'referensi_id' => $reversal->id,
                'tanggal' => $data['tanggal_pengembalian'],
            ]);
            $this->akuntansiService->recordVendorRentalRefund($payment->fresh(), $reversal, $userId);

            return $payment->fresh();
        });
    }

    private function decision(Model $sewa): array
    {
        return $sewa instanceof SewaMobil
            ? $this->eligibility->mobil($sewa)
            : $this->eligibility->hardware($sewa);
    }

    private function assertWallet(DompetKoperasi $wallet, string $method): void
    {
        $expected = $method === 'tunai' ? DompetKoperasi::JENIS_KAS : DompetKoperasi::JENIS_BANK;
        if ($wallet->jenis_dompet !== $expected || ! $wallet->akun) {
            throw ValidationException::withMessages(['dompet_id' => 'Dompet tidak sesuai dengan metode pembayaran vendor.']);
        }
    }

    private function amount(mixed $value): int
    {
        return (int) explode('.', preg_replace('/[^0-9.]/', '', (string) $value) ?: '0')[0];
    }
}
