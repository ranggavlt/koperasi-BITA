<?php

namespace App\Http\Controllers;

use App\Models\CicilanPinjaman;
use App\Models\DompetKoperasi;
use App\Models\Penjualan;
use App\Models\ReversalTransaksi;
use App\Models\Simpanan;
use App\Services\TransaksiReversalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReversalTransaksiController extends Controller
{
    public function __construct(private readonly TransaksiReversalService $service)
    {
    }

    public function index()
    {
        $reversals = ReversalTransaksi::query()
            ->with(['dompetRefund', 'kreditPayroll.anggota.karyawan'])
            ->latest('id')
            ->paginate(20);

        return view('pages.reversal-transaksi.index', compact('reversals'));
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
