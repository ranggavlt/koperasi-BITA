<?php

namespace App\Http\Controllers;

use App\Models\SewaHardware;
use App\Models\SewaMobil;
use App\Services\InvoicePenagihanService;
use App\Services\VendorRentalPaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RentalPaymentController extends Controller
{
    public function __construct(
        private readonly VendorRentalPaymentService $vendorService,
        private readonly InvoicePenagihanService $invoiceService,
    ) {
    }

    public function payMobilVendor(Request $request, SewaMobil $sewaMobil)
    {
        $this->vendorService->pay($sewaMobil, $this->validateVendorPayment($request), (int) $request->user()->id);
        return back()->with('success', 'Pembayaran vendor Sewa Mobil berhasil dicatat.');
    }

    public function payHardwareVendor(Request $request, SewaHardware $sewaHardware)
    {
        $this->vendorService->pay($sewaHardware, $this->validateVendorPayment($request), (int) $request->user()->id);
        return back()->with('success', 'Pembayaran vendor Sewa Hardware berhasil dicatat.');
    }

    public function requestMobilVendorRefund(Request $request, SewaMobil $sewaMobil)
    {
        $validated = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->vendorService->requestRefund($sewaMobil, $validated['alasan'], (int) $request->user()->id);
        return back()->with('success', 'Permintaan pengembalian dana vendor dicatat.');
    }

    public function requestHardwareVendorRefund(Request $request, SewaHardware $sewaHardware)
    {
        $validated = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->vendorService->requestRefund($sewaHardware, $validated['alasan'], (int) $request->user()->id);
        return back()->with('success', 'Permintaan pengembalian dana vendor dicatat.');
    }

    public function confirmMobilVendorRefund(Request $request, SewaMobil $sewaMobil)
    {
        $this->vendorService->confirmRefund($sewaMobil, $this->validateVendorRefund($request), (int) $request->user()->id);
        return back()->with('success', 'Dana dari vendor Sewa Mobil sudah diterima kembali.');
    }

    public function confirmHardwareVendorRefund(Request $request, SewaHardware $sewaHardware)
    {
        $this->vendorService->confirmRefund($sewaHardware, $this->validateVendorRefund($request), (int) $request->user()->id);
        return back()->with('success', 'Dana dari vendor Sewa Hardware sudah diterima kembali.');
    }

    public function refundMobilCompany(Request $request, SewaMobil $sewaMobil)
    {
        $this->invoiceService->refundRental($sewaMobil, $this->validateCompanyRefund($request), (int) $request->user()->id);
        return back()->with('success', 'Dana perusahaan untuk Sewa Mobil berhasil dikembalikan.');
    }

    public function refundHardwareCompany(Request $request, SewaHardware $sewaHardware)
    {
        $this->invoiceService->refundRental($sewaHardware, $this->validateCompanyRefund($request), (int) $request->user()->id);
        return back()->with('success', 'Dana perusahaan untuk Sewa Hardware berhasil dikembalikan.');
    }

    private function validateVendorPayment(Request $request): array
    {
        return $request->validate([
            'jumlah' => ['required', 'integer', 'min:1'],
            'metode' => ['required', Rule::in(['tunai', 'transfer_bank'])],
            'dompet_id' => ['required', 'integer', 'exists:dompet_koperasi,id'],
            'tanggal_bayar' => ['required', 'date'],
            'nomor_referensi' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function validateVendorRefund(Request $request): array
    {
        return $request->validate([
            'tanggal_pengembalian' => ['required', 'date'],
            'nomor_referensi' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function validateCompanyRefund(Request $request): array
    {
        return $request->validate([
            'metode' => ['required', Rule::in(['tunai', 'transfer_bank'])],
            'dompet_id' => ['required', 'integer', 'exists:dompet_koperasi,id'],
            'tanggal_pengembalian' => ['required', 'date'],
            'nomor_referensi' => ['nullable', 'string', 'max:120'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
    }
}
