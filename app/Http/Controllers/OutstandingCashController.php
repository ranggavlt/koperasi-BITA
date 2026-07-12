<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Services\PotongGajiReportService;
use App\Services\TransaksiReversalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OutstandingCashController extends Controller
{
    public function __construct(
        private readonly PotongGajiReportService $reportService,
        private readonly TransaksiReversalService $service
    ) {
    }

    public function index(Request $request)
    {
        $report = $this->reportService->outstanding($request->only(['status', 'anggota_id']));

        return view('pages.outstanding-cash.index', [
            'rows' => $report['rows'],
            'summary' => $report['summary'],
            'dompetKas' => DompetKoperasi::query()->kas()->with('akun')->orderBy('nama_dompet')->get(),
            'anggotaOptions' => Anggota::query()->with('karyawan')->orderBy('nomor_anggota')->get(),
        ]);
    }

    public function paySource(Request $request)
    {
        $validated = $request->validate([
            'source_type' => ['required', 'string'],
            'source_id' => ['required', 'integer'],
            'dompet_id' => ['required', 'integer', Rule::exists('dompet_koperasi', 'id')],
        ]);

        $this->service->payOutstandingSource(
            $validated['source_type'],
            (int) $validated['source_id'],
            DompetKoperasi::query()->findOrFail($validated['dompet_id']),
            (int) $request->user()->id
        );

        return back()->with('success', 'Outstanding cash sumber terpilih berhasil dilunasi penuh.');
    }

    public function payAll(Request $request, Anggota $anggota)
    {
        $validated = $request->validate([
            'dompet_id' => ['required', 'integer', Rule::exists('dompet_koperasi', 'id')],
        ]);

        $this->service->payAllOutstandingForAnggota(
            $anggota,
            DompetKoperasi::query()->findOrFail($validated['dompet_id']),
            (int) $request->user()->id
        );

        return back()->with('success', 'Seluruh outstanding cash Anggota berhasil dilunasi penuh.');
    }
}
