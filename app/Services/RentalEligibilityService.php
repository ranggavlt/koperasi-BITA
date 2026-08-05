<?php

namespace App\Services;

use App\Models\PembayaranVendorSewa;
use App\Models\SewaHardware;
use App\Models\SewaMobil;
use Illuminate\Database\Eloquent\Model;

class RentalEligibilityService
{
    /**
     * Satu sumber kebenaran untuk aksi Sewa Mobil yang dipakai service dan UI.
     *
     * @return array<string, bool|float|string>
     */
    public function mobil(SewaMobil $sewa): array
    {
        return $this->evaluate(
            $sewa,
            SewaMobil::STATUS_DISETUJUI,
            [SewaMobil::STATUS_DRAFT, SewaMobil::STATUS_DIAJUKAN, SewaMobil::STATUS_DISETUJUI],
            [SewaMobil::STATUS_DITOLAK, SewaMobil::STATUS_SELESAI, SewaMobil::STATUS_DIBATALKAN, SewaMobil::STATUS_REFUNDED]
        );
    }

    /** @return array<string, bool|float|string> */
    public function hardware(SewaHardware $sewa): array
    {
        return $this->evaluate(
            $sewa,
            SewaHardware::STATUS_DIKONFIRMASI,
            [SewaHardware::STATUS_DRAFT, SewaHardware::STATUS_DIKONFIRMASI],
            [SewaHardware::STATUS_SELESAI, SewaHardware::STATUS_DIBATALKAN, SewaHardware::STATUS_REFUNDED]
        );
    }

    /** @return array<string, bool|float|string> */
    private function evaluate(Model $sewa, string $readyStatus, array $preStartStatuses, array $finalStatuses): array
    {
        $sewa->loadMissing([
            'pembayaran',
            'pembayaranVendorBaru',
            'invoiceDetail.allocations',
            'invoiceDetail.pengembalian',
        ]);

        $vendorPayment = $sewa->pembayaranVendorBaru;
        $legacyPayment = $sewa->pembayaran;
        $legacyVendorPaid = $legacyPayment && (float) ($legacyPayment->jumlah_bayar_vendor ?? 0) > 0;
        $vendorPaid = $legacyVendorPaid || ($vendorPayment && in_array($vendorPayment->status, [
            PembayaranVendorSewa::STATUS_DIBAYAR,
            PembayaranVendorSewa::STATUS_MENUNGGU_PENGEMBALIAN,
            PembayaranVendorSewa::STATUS_DIKEMBALIKAN,
        ], true));
        $waitingVendorRefund = $vendorPayment?->status === PembayaranVendorSewa::STATUS_MENUNGGU_PENGEMBALIAN;
        $vendorReturned = $vendorPayment?->status === PembayaranVendorSewa::STATUS_DIKEMBALIKAN
            || (bool) ($legacyPayment?->refunded_at);

        $allocated = (float) ($sewa->invoiceDetail?->allocations?->sum('jumlah') ?? 0);
        $returned = (float) ($sewa->invoiceDetail?->pengembalian?->sum('jumlah') ?? 0);
        $legacyReceived = (float) ($legacyPayment?->jumlah_diterima ?? $legacyPayment?->jumlah_bayar ?? 0);
        $companyReceived = max(0, max($allocated, $legacyReceived) - $returned);
        $preStart = in_array($sewa->status, $preStartStatuses, true);
        $final = in_array($sewa->status, $finalStatuses, true);
        $overdue = ! $final && $sewa->status !== 'berjalan'
            && ($sewa->tanggal_selesai ?? $sewa->selesai_tanggal)?->isPast();

        return [
            'can_pay_vendor' => $sewa->status === $readyStatus && ! $vendorPaid && (bool) $sewa->invoiceDetail,
            'needs_invoice_before_vendor' => $sewa->status === $readyStatus && ! $vendorPaid && ! $sewa->invoiceDetail,
            'can_cancel' => $preStart && ! $vendorPaid,
            'can_request_vendor_refund' => $preStart && (bool) $vendorPayment && $vendorPaid && ! $waitingVendorRefund && ! $vendorReturned,
            'can_legacy_full_refund' => $preStart && $legacyVendorPaid && ! $vendorPayment,
            'waiting_vendor_refund' => $waitingVendorRefund,
            'vendor_returned' => $vendorReturned,
            'can_refund_company' => $preStart && $vendorReturned && $companyReceived > 0,
            'can_start' => $sewa->status === $readyStatus && $vendorPaid && ! $waitingVendorRefund && ! $vendorReturned,
            'can_complete' => $sewa->status === 'berjalan',
            'can_invoice' => in_array($sewa->status, [$readyStatus, 'berjalan', 'selesai'], true) && ! $sewa->invoiceDetail,
            'is_final' => $final,
            'is_overdue' => $overdue,
            'vendor_paid' => $vendorPaid,
            'company_received' => $companyReceived,
            'company_refunded' => $returned,
            'vendor_status_label' => $waitingVendorRefund
                ? 'Menunggu Pengembalian Vendor'
                : ($vendorReturned ? 'Dana Vendor Sudah Kembali' : ($vendorPaid ? 'Vendor Sudah Dibayar' : 'Vendor Belum Dibayar')),
            'company_status_label' => $companyReceived > 0
                ? 'Perusahaan Sudah Membayar Rp ' . number_format($companyReceived, 0, ',', '.')
                : ($sewa->invoiceDetail ? 'Belum Ada Pembayaran Perusahaan' : 'Belum Ditagihkan'),
        ];
    }
}
