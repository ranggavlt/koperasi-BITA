<?php

namespace App\Http\Controllers;

use App\Models\DompetKoperasi;
use App\Models\InvoicePenagihan;
use App\Models\Perusahaan;
use App\Models\SewaMobil;
use App\Models\SewaPrinter;
use App\Services\B2BRentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoicePenagihanController extends Controller
{
    public function index(): View
    {
        return view('pages.invoice-penagihan.index', [
            'invoices' => InvoicePenagihan::query()
                ->with(['perusahaan', 'detail', 'pembayaran.dompet'])
                ->latest('tanggal_invoice')
                ->latest('id')
                ->paginate(12),
            'perusahaan' => Perusahaan::query()->whereIn('kode', ['BEE', 'BBS', 'BKM'])->orderBy('kode')->get(),
            'eligibleMobil' => SewaMobil::query()
                ->with(['karyawan.perusahaan', 'pembayaranVendor'])
                ->whereHas('pembayaranVendor')
                ->whereDoesntHave('invoiceDetail')
                ->whereIn('status', [SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN, SewaMobil::STATUS_SELESAI])
                ->orderBy('kode_sewa')->get(),
            'eligibleHardware' => SewaPrinter::query()
                ->with(['karyawan.perusahaan', 'pembayaranVendor'])
                ->whereHas('pembayaranVendor')
                ->whereDoesntHave('invoiceDetail')
                ->whereIn('status', [SewaPrinter::STATUS_DIKONFIRMASI, SewaPrinter::STATUS_BERJALAN, SewaPrinter::STATUS_SELESAI])
                ->orderBy('kode_sewa')->get(),
            'dompetOptions' => DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get(),
        ]);
    }

    public function store(Request $request, B2BRentalService $service): RedirectResponse
    {
        $data = $request->validate([
            'perusahaan_id' => ['required', 'exists:perusahaan,id'],
            'tanggal_invoice' => ['required', 'date'],
            'jatuh_tempo' => ['required', 'date', 'after_or_equal:tanggal_invoice'],
            'sewa_mobil_ids' => ['nullable', 'array'],
            'sewa_mobil_ids.*' => ['integer', 'exists:sewa_mobil,id'],
            'sewa_hardware_ids' => ['nullable', 'array'],
            'sewa_hardware_ids.*' => ['integer', 'exists:sewa_printer,id'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
        ]);
        $service->createInvoice(Perusahaan::query()->findOrFail($data['perusahaan_id']), $data, $request->user()->id);

        return back()->with('success', 'Invoice perusahaan berhasil difinalisasi. Pembayaran dapat dicatat sebagian sampai lunas.');
    }

    public function pay(Request $request, InvoicePenagihan $invoicePenagihan, B2BRentalService $service): RedirectResponse
    {
        $data = $request->validate([
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
            'metode_pembayaran' => ['required', 'in:tunai,transfer_bank'],
            'jumlah_bayar' => ['required', 'integer', 'min:1'],
            'tanggal_bayar' => ['required', 'date'],
            'nomor_referensi' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
        ]);
        $service->payInvoice($invoicePenagihan, $data, $request->user()->id);

        return back()->with('success', 'Pembayaran perusahaan berhasil dicatat dan sisa invoice diperbarui.');
    }
}
