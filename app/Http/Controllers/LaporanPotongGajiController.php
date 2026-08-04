<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\Karyawan;
use App\Models\Perusahaan;
use App\Services\PotongGajiReportService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LaporanPotongGajiController extends Controller
{
    public function __construct(private readonly PotongGajiReportService $reportService)
    {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'periode' => ['nullable', 'date_format:Y-m'],
            'perusahaan_id' => ['nullable', 'integer', Rule::exists('perusahaan', 'id')],
            'anggota_id' => ['nullable', 'integer', Rule::exists('anggota', 'id')],
            'tampilkan_tanpa_potongan' => ['nullable', Rule::in(['1'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! empty($validated['anggota_id'])) {
            $memberIsValid = Anggota::query()
                ->aktif()
                ->whereKey($validated['anggota_id'])
                ->whereHas('karyawan', function ($query) use ($validated): void {
                    $query->where('status_kerja', Karyawan::STATUS_AKTIF)
                        ->when($validated['perusahaan_id'] ?? null, fn ($query, $companyId) => $query->where('perusahaan_id', $companyId));
                })
                ->exists();

            if (! $memberIsValid) {
                throw ValidationException::withMessages([
                    'anggota_id' => 'Anggota yang dipilih tidak aktif atau tidak termasuk perusahaan terpilih.',
                ]);
            }
        }

        $periode = $validated['periode'] ?? now(config('app.timezone'))->format('Y-m');
        $filters = [
            'perusahaan_id' => $validated['perusahaan_id'] ?? null,
            'anggota_id' => $validated['anggota_id'] ?? null,
        ];
        $report = $this->reportService->payroll($periode, $filters);
        $showWithoutDeductions = isset($validated['tampilkan_tanpa_potongan']);
        $allRows = $report['rows'];

        if (empty($filters['anggota_id']) && ! $showWithoutDeductions) {
            $allRows = $allRows->filter(fn ($row): bool => (float) $row->gross_payroll > 0)->values();
        }

        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = 15;
        $laporan = new LengthAwarePaginator(
            $allRows->forPage($page, $perPage)->values(),
            $allRows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $anggotaOptions = Anggota::query()
            ->with('karyawan.perusahaan')
            ->aktif()
            ->whereHas('karyawan', fn ($query) => $query->where('status_kerja', Karyawan::STATUS_AKTIF))
            ->orderBy('nomor_anggota')
            ->get();

        $perusahaanList = Perusahaan::query()
            ->whereIn('kode', ['BEE', 'BBS', 'BKM'])
            ->orderBy('kode')
            ->get();

        return view('pages.laporan.potong-gaji', [
            'periode' => $periode,
            'mulai' => $report['mulai'],
            'akhir' => $report['akhir'],
            'periodeRow' => $report['periodeRow'],
            'laporan' => $laporan,
            'details' => $report['details'],
            'summary' => $report['summary'],
            'warnings' => $report['warnings'],
            'anggotaOptions' => $anggotaOptions,
            'perusahaanList' => $perusahaanList,
            'filters' => $filters,
            'showWithoutDeductions' => $showWithoutDeductions,
            'selectedMember' => $report['rows']->first(
                fn ($row): bool => (int) ($row->anggota?->id ?? 0) === (int) ($filters['anggota_id'] ?? 0)
            ),
        ]);
    }
}
