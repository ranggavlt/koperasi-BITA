<?php

namespace App\Http\Controllers;

use App\Models\Perusahaan;
use App\Services\PotongGajiReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekonsiliasiPotongGajiController extends Controller
{
    public function __construct(private readonly PotongGajiReportService $reportService)
    {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'periode' => ['nullable', 'date_format:Y-m'],
            'perusahaan_id' => ['nullable', 'integer', Rule::exists('perusahaan', 'id')],
        ]);

        $periode = $validated['periode'] ?? now(config('app.timezone'))->format('Y-m');
        $perusahaanId = isset($validated['perusahaan_id']) ? (int) $validated['perusahaan_id'] : null;
        $rekonsiliasi = $this->reportService->reconciliation($periode, $perusahaanId);
        $perusahaanList = Perusahaan::query()
            ->whereIn('kode', ['BEE', 'BBS', 'BKM'])
            ->orderBy('kode')
            ->get();

        return view('pages.rekonsiliasi-potong-gaji.index', compact('periode', 'perusahaanId', 'perusahaanList', 'rekonsiliasi'));
    }
}
