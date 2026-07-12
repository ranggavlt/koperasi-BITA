<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\PemakaianPotongGaji;
use App\Services\PotongGajiReportService;
use Illuminate\Http\Request;

class LaporanPotongGajiController extends Controller
{
    public function __construct(private readonly PotongGajiReportService $reportService)
    {
    }

    public function index(Request $request)
    {
        $periode = $request->get('periode', now(config('app.timezone'))->format('Y-m'));
        $report = $this->reportService->payroll($periode, $request->only(['anggota_id', 'status', 'kategori']));

        return view('pages.laporan.potong-gaji', [
            'periode' => $periode,
            'mulai' => $report['mulai'],
            'akhir' => $report['akhir'],
            'periodeRow' => $report['periodeRow'],
            'laporan' => $report['rows'],
            'details' => $report['details'],
            'summary' => $report['summary'],
            'anggotaOptions' => Anggota::query()->with('karyawan')->orderBy('nomor_anggota')->get(),
            'kategoriOptions' => [
                PemakaianPotongGaji::KATEGORI_CICILAN => 'Cicilan',
                PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK => 'Simpanan Pokok',
                PemakaianPotongGaji::KATEGORI_POS => 'POS',
                PemakaianPotongGaji::KATEGORI_JASA_PRINT => 'Jasa Print',
            ],
        ]);
    }
}
