<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\Pinjaman;
use App\Services\PinjamanReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CicilanPinjamanController extends Controller
{
    public function index(Request $request, PinjamanReportService $reportService)
    {
        $validated = $request->validate([
            'anggota_id' => ['nullable', 'integer', 'exists:anggota,id'],
            'pinjaman_id' => ['nullable', 'integer', 'exists:pinjaman,id'],
            'status' => ['nullable', Rule::in(array_keys($reportService->cicilanStatusOptions()))],
            'periode_mulai' => ['nullable', 'date'],
            'periode_selesai' => ['nullable', 'date', 'after_or_equal:periode_mulai'],
        ]);

        $report = $reportService->cicilanIndex($validated);

        $dompetRefund = DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get();

        return view('pages.cicilan-pinjaman.index', [
            'jadwalCicilan' => $report['rows'],
            'summary' => $report['summary'],
            'filters' => $report['filters'],
            'statusOptions' => $report['statusOptions'],
            'dompetRefund' => $dompetRefund,
            'anggotaOptions' => Anggota::query()->with('karyawan')->orderBy('nomor_anggota')->get(),
            'pinjamanOptions' => Pinjaman::query()->with('anggota.karyawan')->orderBy('kode_pinjaman')->get(),
        ]);
    }
}
