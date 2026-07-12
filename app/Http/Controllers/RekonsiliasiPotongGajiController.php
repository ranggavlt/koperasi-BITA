<?php

namespace App\Http\Controllers;

use App\Services\PotongGajiReportService;
use Illuminate\Http\Request;

class RekonsiliasiPotongGajiController extends Controller
{
    public function __construct(private readonly PotongGajiReportService $reportService)
    {
    }

    public function index(Request $request)
    {
        $periode = $request->get('periode', now(config('app.timezone'))->format('Y-m'));
        $rekonsiliasi = $this->reportService->reconciliation($periode);

        return view('pages.rekonsiliasi-potong-gaji.index', compact('periode', 'rekonsiliasi'));
    }
}
