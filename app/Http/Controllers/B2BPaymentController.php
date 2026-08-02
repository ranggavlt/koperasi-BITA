<?php

namespace App\Http\Controllers;

use App\Models\SewaMobil;
use App\Models\SewaPrinter;
use App\Services\B2BRentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class B2BPaymentController extends Controller
{
    public function payMobilVendor(Request $request, SewaMobil $sewaMobil, B2BRentalService $service): RedirectResponse
    {
        $service->payVendor($sewaMobil, $this->validated($request), $request->user()->id);
        return back()->with('success', 'Vendor Sewa Mobil berhasil dibayar dari Kas Operasional.');
    }

    public function payHardwareVendor(Request $request, SewaPrinter $sewaPrinter, B2BRentalService $service): RedirectResponse
    {
        $service->payVendor($sewaPrinter, $this->validated($request), $request->user()->id);
        return back()->with('success', 'Vendor Sewa Hardware berhasil dibayar dari Kas Operasional.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
            'tanggal_bayar' => ['required', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
        ]);
    }
}
