<?php

namespace App\Http\Controllers;

use App\Models\DompetKoperasi;
use App\Models\InvoicePenagihan;
use App\Models\Perusahaan;
use App\Models\SewaHardware;
use App\Models\SewaMobil;
use App\Services\B2BRentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoicePenagihanController extends Controller
{
    public function index(): View
    {
        $companies = Perusahaan::query()->whereIn('kode', ['BEE', 'BBS', 'BKM'])->orderBy('kode')->get();
        $totals = InvoicePenagihan::query()
            ->selectRaw('perusahaan_id, SUM(total_tagihan) as total_tagihan, SUM(jumlah_dibayar) as total_dibayar, SUM(sisa_tagihan) as sisa_tagihan, COUNT(*) as jumlah_invoice')
            ->whereIn('perusahaan_id', $companies->pluck('id'))
            ->groupBy('perusahaan_id')
            ->get()
            ->keyBy('perusahaan_id');

        return view('pages.invoice-penagihan.index', [
            'invoices' => InvoicePenagihan::query()
                ->with(['perusahaan', 'detail', 'pembayaran.dompet'])
                ->latest('tanggal_invoice')
                ->latest('id')
                ->paginate(12),
            'perusahaan' => $companies,
            'companySummaries' => $companies->map(function (Perusahaan $company) use ($totals): array {
                $total = $totals->get($company->id);
                return [
                    'kode' => $company->kode,
                    'nama' => $company->nama,
                    'jumlah_invoice' => (int) ($total?->jumlah_invoice ?? 0),
                    'total_tagihan' => (float) ($total?->total_tagihan ?? 0),
                    'total_dibayar' => (float) ($total?->total_dibayar ?? 0),
                    'sisa_tagihan' => (float) ($total?->sisa_tagihan ?? 0),
                ];
            }),
            'eligibleMobil' => SewaMobil::query()
                ->with(['karyawan.perusahaan', 'pembayaranVendor'])
                ->whereHas('pembayaranVendor')
                ->whereDoesntHave('invoiceDetail')
                ->whereIn('status', [SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN, SewaMobil::STATUS_SELESAI])
                ->orderBy('kode_sewa')
                ->get(),
            'eligibleHardware' => SewaHardware::query()
                ->with(['karyawan.perusahaan', 'pembayaranVendor'])
                ->whereHas('pembayaranVendor')
                ->whereDoesntHave('invoiceDetail')
                ->whereIn('status', [SewaHardware::STATUS_DIKONFIRMASI, SewaHardware::STATUS_BERJALAN, SewaHardware::STATUS_SELESAI])
                ->orderBy('kode_sewa')
                ->get(),
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
            'sewa_hardware_ids.*' => ['integer', 'exists:sewa_hardware,id'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
        ]);

        $service->createInvoice(
            Perusahaan::query()->findOrFail($data['perusahaan_id']),
            $data,
            $request->user()->id
        );

        return back()->with('success', 'Invoice penagihan berhasil difinalisasi.');
    }

    public function pay(InvoicePenagihan $invoicePenagihan, Request $request, B2BRentalService $service): RedirectResponse
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

        return back()->with('success', 'Pembayaran invoice perusahaan berhasil dicatat.');
    }
}
