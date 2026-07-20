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
        $kategoriOptions = [
            PemakaianPotongGaji::KATEGORI_CICILAN => 'Cicilan',
            PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK => 'Simpanan Pokok',
            PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB => 'Simpanan Wajib',
            PemakaianPotongGaji::KATEGORI_POS => 'POS',
        ];

        if (config('features.jasa_print_enabled', false)) {
            $kategoriOptions[PemakaianPotongGaji::KATEGORI_JASA_PRINT] = 'Jasa Print';
        }

        $filters = $request->only(['anggota_id', 'status', 'kategori']);
        if (($filters['kategori'] ?? null) && ! array_key_exists($filters['kategori'], $kategoriOptions)) {
            unset($filters['kategori']);
        }

        $report = $this->reportService->payroll($periode, $filters);

        return view('pages.laporan.potong-gaji', [
            'periode' => $periode,
            'mulai' => $report['mulai'],
            'akhir' => $report['akhir'],
            'periodeRow' => $report['periodeRow'],
            'laporan' => $report['rows'],
            'details' => $report['details'],
            'summary' => $report['summary'],
            'warnings' => $report['warnings'],
            'anggotaOptions' => Anggota::query()->with('karyawan')->orderBy('nomor_anggota')->get(),
            'kategoriOptions' => $kategoriOptions,
        ]);
    }
}
