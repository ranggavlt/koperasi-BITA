<?php

namespace App\Http\Controllers;

use App\Models\DompetKoperasi;
use App\Models\InvoicePenagihan;
use App\Models\Perusahaan;
use App\Models\SewaHardware;
use App\Models\SewaMobil;
use App\Services\InvoicePenagihanService;
use App\Services\RentalEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoicePenagihanController extends Controller
{
    public function __construct(
        private readonly InvoicePenagihanService $service,
        private readonly RentalEligibilityService $eligibility,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'perusahaan_id' => ['nullable', 'integer', 'exists:perusahaan,id'],
            'status' => ['nullable', Rule::in(['unpaid', 'partial', 'paid', 'overdue'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = InvoicePenagihan::query()->with('perusahaan')->latest('tanggal_invoice')->latest('id');
        $query->when($filters['perusahaan_id'] ?? null, fn ($q, $id) => $q->where('perusahaan_id', $id));
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where('nomor_invoice', 'like', '%' . trim($search) . '%'));
        $query->when($filters['status'] ?? null, function ($q, $status): void {
            if ($status === 'overdue') {
                $q->where('status', '!=', InvoicePenagihan::STATUS_PAID)->whereDate('jatuh_tempo', '<', today());
            } else {
                $q->where('status', $status);
            }
        });

        $companies = Perusahaan::query()->orderBy('kode')->get();
        $summary = $companies->map(function (Perusahaan $company): array {
            $base = InvoicePenagihan::query()->where('perusahaan_id', $company->id);
            return [
                'company' => $company,
                'count' => (clone $base)->count(),
                'total' => (float) (clone $base)->sum('total_tagihan'),
                'paid' => (float) (clone $base)->sum('total_dibayar'),
                'remaining' => (float) (clone $base)->sum('sisa_tagihan'),
            ];
        });

        return view('pages.invoice-penagihan.index', [
            'invoices' => $query->paginate(15)->withQueryString(),
            'companies' => $companies,
            'summary' => $summary,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $companyId = $request->integer('perusahaan_id') ?: null;
        $companies = Perusahaan::query()->orderBy('kode')->get();
        $mobil = collect();
        $hardware = collect();

        if ($companyId) {
            $mobil = SewaMobil::query()
                ->with(['karyawan', 'invoiceDetail', 'pembayaran', 'pembayaranVendorBaru'])
                ->whereHas('karyawan', fn ($q) => $q->where('perusahaan_id', $companyId))
                ->whereIn('status', [SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN, SewaMobil::STATUS_SELESAI])
                ->whereDoesntHave('invoiceDetail')->orderBy('tanggal_mulai')->get()
                ->filter(fn (SewaMobil $item) => $this->eligibility->mobil($item)['can_invoice']);
            $hardware = SewaHardware::query()
                ->with(['karyawan', 'invoiceDetail', 'pembayaran', 'pembayaranVendorBaru'])
                ->whereHas('karyawan', fn ($q) => $q->where('perusahaan_id', $companyId))
                ->whereIn('status', [SewaHardware::STATUS_DIKONFIRMASI, SewaHardware::STATUS_BERJALAN, SewaHardware::STATUS_SELESAI])
                ->whereDoesntHave('invoiceDetail')->orderBy('mulai_tanggal')->get()
                ->filter(fn (SewaHardware $item) => $this->eligibility->hardware($item)['can_invoice']);
        }

        return view('pages.invoice-penagihan.create', compact('companies', 'companyId', 'mobil', 'hardware'));
    }

    public function store(Request $request, B2BRentalService $service): RedirectResponse
    {
        $validated = $request->validate([
            'perusahaan_id' => ['required', 'integer', 'exists:perusahaan,id'],
            'tanggal_invoice' => ['required', 'date'],
            'jatuh_tempo' => ['required', 'date', 'after_or_equal:tanggal_invoice'],
            'sewa_mobil_ids' => ['nullable', 'array'],
            'sewa_mobil_ids.*' => ['integer', 'distinct', 'exists:sewa_mobil,id'],
            'sewa_hardware_ids' => ['nullable', 'array'],
            'sewa_hardware_ids.*' => ['integer', 'distinct', 'exists:sewa_hardware,id'],
        ]);

        $invoice = $this->service->create($validated, (int) $request->user()->id);

        return redirect()->route('invoice-penagihan.show', $invoice)
            ->with('success', 'Invoice perusahaan berhasil dibuat.');
    }

    public function show(InvoicePenagihan $invoicePenagihan)
    {
        $invoicePenagihan->load([
            'perusahaan', 'creator', 'detail.referensi', 'detail.allocations',
            'detail.pengembalian', 'pembayaran.dompet', 'pembayaran.creator',
        ]);

        return view('pages.invoice-penagihan.show', [
            'invoice' => $invoicePenagihan,
            'wallets' => DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get(),
        ]);
    }

    public function storePayment(Request $request, InvoicePenagihan $invoicePenagihan)
    {
        $validated = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1'],
            'metode' => ['required', Rule::in(['tunai', 'transfer_bank'])],
            'dompet_id' => ['required', 'integer', 'exists:dompet_koperasi,id'],
            'tanggal_bayar' => ['required', 'date'],
            'nomor_referensi' => ['nullable', 'string', 'max:120'],
        ]);
        $this->service->recordPayment($invoicePenagihan, $validated, (int) $request->user()->id);

        return back()->with('success', 'Pembayaran invoice berhasil dicatat.');
    }
}
