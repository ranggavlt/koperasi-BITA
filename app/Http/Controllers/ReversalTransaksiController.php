<?php

namespace App\Http\Controllers;

use App\Models\CicilanPinjaman;
use App\Models\DompetKoperasi;
use App\Models\Penjualan;
use App\Models\ReversalTransaksi;
use App\Models\Simpanan;
use App\Services\SimpananManasukaService;
use App\Services\TransaksiReversalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReversalTransaksiController extends Controller
{
    public function __construct(
        private readonly TransaksiReversalService $service,
        private readonly SimpananManasukaService $simpananManasukaService
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in([ReversalTransaksi::STATUS_PROCESSED, ReversalTransaksi::STATUS_CANCELLED])]]);
        $reversals = ReversalTransaksi::query()
            ->with(['dompetRefund', 'kreditPayroll.anggota.karyawan', 'creator', 'processor'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($nested) => $nested->where('kode_reversal', 'like', '%'.trim($search).'%')->orWhere('alasan', 'like', '%'.trim($search).'%')))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(20)->withQueryString();

        return view('pages.reversal-transaksi.index', compact('reversals', 'filters'));
    }

    public function show(ReversalTransaksi $reversal)
    {
        return view('pages.reversal-transaksi.show', ['reversal' => $reversal->load(['source', 'originalJurnal.details', 'originalMutasi', 'dompetRefund', 'creator', 'processor'])]);
    }

    public function refundPos(Request $request, Penjualan $penjualan)
    {
        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5'],
            'dompet_refund_id' => ['nullable', 'integer', Rule::exists('dompet_koperasi', 'id')],
        ]);

        $dompet = isset($validated['dompet_refund_id'])
            ? DompetKoperasi::query()->findOrFail($validated['dompet_refund_id'])
            : null;

        $this->service->refundPos($penjualan, $validated['alasan'], (int) $request->user()->id, $dompet);

        return back()->with('success', 'Reversal/refund POS berhasil diproses.');
    }

    public function koreksiSimpanan(Request $request, Simpanan $simpanan)
    {
        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5'],
            'nominal_pengganti' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($simpanan->isSimpananManasuka()) {
            $this->simpananManasukaService->koreksi($simpanan, $validated['alasan'], (int) $request->user()->id);
            return back()->with('success', 'Koreksi Transaksi Simpanan Manasuka berhasil diproses.');
        }

        $this->service->correctPendingSimpananPokok(
            $simpanan,
            $validated['alasan'],
            (int) $request->user()->id,
            isset($validated['nominal_pengganti']) ? (int) $validated['nominal_pengganti'] : null
        );

        return back()->with('success', 'Koreksi Simpanan Pokok berhasil diproses.');
    }

    public function reverseCicilan(Request $request, CicilanPinjaman $cicilan)
    {
        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5'],
            'dompet_refund_id' => ['nullable', 'integer', Rule::exists('dompet_koperasi', 'id')],
        ]);

        $dompet = isset($validated['dompet_refund_id'])
            ? DompetKoperasi::query()->findOrFail($validated['dompet_refund_id'])
            : null;

        $this->service->reverseCicilan($cicilan, $validated['alasan'], (int) $request->user()->id, $dompet);

        return back()->with('success', 'Reversal cicilan berhasil diproses.');
    }
}
