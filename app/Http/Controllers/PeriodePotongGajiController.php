<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\KebijakanLimitPotongGaji;
use App\Models\LimitPotongGajiAnggota;
use App\Models\OverrideLimitPotongGajiAnggota;
use App\Models\PeriodePotongGaji;
use App\Models\Perusahaan;
use App\Services\PotongGajiBulananService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PeriodePotongGajiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'periode_id' => ['nullable', 'integer', Rule::exists('periode_potong_gaji', 'id')],
            'perusahaan_id' => ['nullable', 'integer', Rule::exists('perusahaan', 'id')],
            'anggota_id' => ['nullable', 'integer', Rule::exists('anggota', 'id')],
            'status' => ['nullable', Rule::in(['limit_umum', 'limit_khusus', 'kredit_nonaktif', 'belum_limit'])],
        ]);

        if (! empty($validated['anggota_id'])) {
            $memberIsValid = Anggota::query()
                ->aktif()
                ->whereKey($validated['anggota_id'])
                ->whereHas('karyawan', function ($query) use ($validated): void {
                    $query->where('status_kerja', \App\Models\Karyawan::STATUS_AKTIF)
                        ->when($validated['perusahaan_id'] ?? null, fn ($query, $companyId) => $query->where('perusahaan_id', $companyId));
                })
                ->exists();

            if (! $memberIsValid) {
                throw ValidationException::withMessages([
                    'anggota_id' => 'Anggota yang dipilih tidak aktif atau tidak termasuk perusahaan terpilih.',
                ]);
            }
        }

        $filters = [
            'perusahaan_id' => $validated['perusahaan_id'] ?? null,
            'anggota_id' => $validated['anggota_id'] ?? null,
            'status' => $validated['status'] ?? null,
        ];

        $periodeList = PeriodePotongGaji::query()
            ->withCount('limits')
            ->orderByDesc('periode')
            ->paginate(12);

        $selectedPeriode = ! empty($validated['periode_id'])
            ? PeriodePotongGaji::query()->find($validated['periode_id'])
            : PeriodePotongGaji::query()->orderByDesc('periode')->first();

        $limits = collect();
        $perusahaanList = Perusahaan::query()
            ->whereIn('kode', ['BEE', 'BBS', 'BKM'])
            ->orderBy('kode')
            ->get();
        $activePolicy = KebijakanLimitPotongGaji::query()
            ->where('status', KebijakanLimitPotongGaji::STATUS_ACTIVE)
            ->whereNull('berlaku_sampai_periode')
            ->orderByDesc('berlaku_mulai_periode')
            ->first();

        $allEligible = Anggota::query()
            ->with(['karyawan.perusahaan', 'pinjaman.jadwalCicilan', 'overrideLimitPotongGaji'])
            ->aktif()
            ->whereHas('karyawan', fn ($query) => $query->where('status_kerja', \App\Models\Karyawan::STATUS_AKTIF))
            ->orderBy('nomor_anggota')
            ->get();

        if ($selectedPeriode) {
            $limits = $selectedPeriode->limits()
                ->with(['anggota.karyawan.perusahaan', 'pemakaian', 'riwayat', 'dompetPenerimaan', 'kebijakanLimit', 'overrideLimit', 'perusahaanSnapshot'])
                ->get()
                ->keyBy('anggota_id');
        }

        $summary = [
            'limit_umum' => $allEligible->filter(fn (Anggota $anggota): bool => $limits->get($anggota->id)?->sumber_limit === LimitPotongGajiAnggota::SUMBER_LIMIT_UMUM)->count(),
            'limit_khusus' => $allEligible->filter(fn (Anggota $anggota): bool => ($limits->get($anggota->id)?->sumber_limit === LimitPotongGajiAnggota::SUMBER_OVERRIDE_ANGGOTA) || $anggota->overrideLimitPotongGaji?->status === OverrideLimitPotongGajiAnggota::STATUS_ACTIVE)->count(),
            'kredit_waserba_nonaktif' => $allEligible->filter(fn (Anggota $anggota): bool => $anggota->overrideLimitPotongGaji && ! $anggota->overrideLimitPotongGaji->kredit_waserba_enabled)->count(),
            'belum_limit' => $selectedPeriode ? $allEligible->filter(fn (Anggota $anggota): bool => ! $limits->has($anggota->id))->count() : $allEligible->count(),
        ];

        $anggotaAktif = $allEligible
            ->filter(function (Anggota $anggota) use ($filters, $limits): bool {
                $limit = $limits->get($anggota->id);
                $setting = $anggota->overrideLimitPotongGaji;

                if ($filters['perusahaan_id'] && (int) $anggota->karyawan?->perusahaan_id !== (int) $filters['perusahaan_id']) {
                    return false;
                }

                if ($filters['anggota_id'] && (int) $anggota->id !== (int) $filters['anggota_id']) {
                    return false;
                }

                return match ($filters['status']) {
                    'limit_umum' => $limit?->sumber_limit === LimitPotongGajiAnggota::SUMBER_LIMIT_UMUM,
                    'limit_khusus' => $limit?->sumber_limit === LimitPotongGajiAnggota::SUMBER_OVERRIDE_ANGGOTA || $setting?->status === OverrideLimitPotongGajiAnggota::STATUS_ACTIVE,
                    'kredit_nonaktif' => $setting && ! $setting->kredit_waserba_enabled,
                    'belum_limit' => ! $limit,
                    default => true,
                };
            })
            ->values();

        $generationWarnings = session('generation_warnings', []);

        return view('pages.periode-potong-gaji.index', compact(
            'periodeList',
            'selectedPeriode',
            'limits',
            'anggotaAktif',
            'allEligible',
            'perusahaanList',
            'activePolicy',
            'summary',
            'filters',
            'generationWarnings'
        ));
    }

    public function create()
    {
        return view('pages.periode-potong-gaji.create');
    }

    public function storePeriode(Request $request, PotongGajiBulananService $service)
    {
        $validated = $request->validate([
            'periode' => ['required', 'date'],
        ]);

        $periode = $service->createPeriodeDraft($validated['periode'], $request->user()->id);
        $summary = $service->bulkGenerateLimitsForPeriod($periode, $request->user()->id);

        Anggota::query()
            ->where('status', Anggota::STATUS_AKTIF)
            ->whereHas('karyawan', fn ($query) => $query->where('status_kerja', \App\Models\Karyawan::STATUS_AKTIF))
            ->orderBy('id')
            ->each(function (Anggota $anggota) use ($service, $periode, $request): void {
                if (! $service->findLimitFor($anggota, $periode->periode)) {
                    $service->createLimitFromPolicy($anggota, $periode->periode, $request->user()->id);
                }
            });

        return redirect()
            ->route('periode-potong-gaji.index', ['periode_id' => $periode->id])
            ->with('success', sprintf('Periode potong gaji berhasil disiapkan. Limit otomatis dibuat: %d, sudah ada: %d, gagal: %d.', $summary['created'], $summary['existing'], $summary['failed']))
            ->with('generation_warnings', $summary['warnings']);
    }

    public function storeLimit(Request $request, PotongGajiBulananService $service)
    {
        $validated = $request->validate([
            'periode_id' => ['required', 'exists:periode_potong_gaji,id'],
            'anggota_id' => ['required', 'exists:anggota,id'],
            'limit_nominal' => ['required', 'integer', 'min:0'],
            'alasan' => ['required', 'string'],
        ]);

        $periode = PeriodePotongGaji::query()->findOrFail($validated['periode_id']);

        $service->createLimit(
            (int) $validated['anggota_id'],
            $periode->periode,
            (int) $validated['limit_nominal'],
            $request->user()->id,
            $validated['alasan']
        );

        return redirect()
            ->route('periode-potong-gaji.index', ['periode_id' => $periode->id])
            ->with('success', 'Limit Anggota berhasil dibuat.');
    }

    public function updateLimit(Request $request, LimitPotongGajiAnggota $limit, PotongGajiBulananService $service)
    {
        $validated = $request->validate([
            'limit_nominal' => ['required', 'integer', 'min:0'],
            'alasan' => ['required', 'string'],
        ]);

        $limit = $service->updateLimit(
            $limit,
            (int) $validated['limit_nominal'],
            $request->user()->id,
            $validated['alasan']
        );

        return redirect()
            ->route('periode-potong-gaji.index', ['periode_id' => $limit->periode_potong_gaji_id])
            ->with('success', 'Limit Anggota berhasil diperbarui.');
    }

    public function activate(LimitPotongGajiAnggota $limit, PotongGajiBulananService $service)
    {
        $limit = $service->activateLimit($limit, request()->user()->id);

        return redirect()
            ->route('periode-potong-gaji.index', ['periode_id' => $limit->periode_potong_gaji_id])
            ->with('success', 'Limit aktif dan cicilan bulan ini sudah direservasi bila ada.');
    }

    public function close(LimitPotongGajiAnggota $limit, PotongGajiBulananService $service)
    {
        $limit = $service->closeLimit($limit, request()->user()->id);

        return redirect()
            ->route('periode-potong-gaji.index', ['periode_id' => $limit->periode_potong_gaji_id])
            ->with('success', 'Limit ditutup dan menunggu konfirmasi payroll.');
    }

    public function confirm(LimitPotongGajiAnggota $limit, PotongGajiBulananService $service)
    {
        try {
            $limit = $service->confirmLimit($limit, request()->user()->id);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            return back()->withErrors(['payroll' => 'Konfirmasi payroll gagal: ' . $exception->getMessage()]);
        }

        return redirect()
            ->route('periode-potong-gaji.index', ['periode_id' => $limit->periode_potong_gaji_id])
            ->with('success', 'Payroll Anggota berhasil dikonfirmasi.');
    }

    public function payoffPayroll(LimitPotongGajiAnggota $limit, PotongGajiBulananService $service)
    {
        $limit = $service->reserveFullPayoffPayroll($limit, request()->user()->id);

        return redirect()
            ->route('periode-potong-gaji.index', ['periode_id' => $limit->periode_potong_gaji_id])
            ->with('success', 'Reservasi pelunasan penuh payroll berhasil dibuat.');
    }

    public function updateGlobalPolicy(Request $request, PotongGajiBulananService $service)
    {
        $validated = $request->validate([
            'nominal_limit' => ['required', 'integer', 'min:0'],
            'berlaku_mulai_periode' => ['nullable', 'date'],
            'alasan' => ['required', 'string', 'max:1000'],
        ]);

        $effective = $validated['berlaku_mulai_periode']
            ?? $service->normalizePeriod()->addMonthNoOverflow()->toDateString();

        $service->updateGlobalPolicy(
            (int) $validated['nominal_limit'],
            $effective,
            $request->user()->id,
            $validated['alasan']
        );

        return back()->with('success', 'Kebijakan limit umum berhasil diperbarui untuk periode berlaku yang dipilih.');
    }

    public function bulkGenerate(PeriodePotongGaji $periode, PotongGajiBulananService $service)
    {
        $summary = $service->bulkGenerateLimitsForPeriod($periode, request()->user()->id);

        return redirect()
            ->route('periode-potong-gaji.index', ['periode_id' => $periode->id])
            ->with('success', sprintf('Generate limit selesai. Dibuat: %d, sudah ada: %d, gagal: %d.', $summary['created'], $summary['existing'], $summary['failed']))
            ->with('generation_warnings', $summary['warnings']);
    }

    public function bulkActivate(PeriodePotongGaji $periode, PotongGajiBulananService $service)
    {
        $summary = $service->bulkActivateLimitsForPeriod($periode, request()->user()->id);

        return redirect()
            ->route('periode-potong-gaji.index', ['periode_id' => $periode->id])
            ->with('success', sprintf('Bulk aktivasi selesai. Aktif: %d, dilewati: %d, gagal: %d.', $summary['activated'], $summary['skipped'], $summary['failed']))
            ->with('generation_warnings', $summary['warnings']);
    }

    public function setOverride(Request $request, Anggota $anggota, PotongGajiBulananService $service)
    {
        $validated = $request->validate([
            'nominal_override' => ['required', 'integer', 'min:0'],
            'berlaku_mulai_periode' => ['nullable', 'date'],
            'alasan' => ['required', 'string', 'max:1000'],
        ]);

        $effective = $validated['berlaku_mulai_periode']
            ?? $service->normalizePeriod()->toDateString();

        $service->setMemberOverride(
            $anggota,
            (int) $validated['nominal_override'],
            $effective,
            $request->user()->id,
            $validated['alasan']
        );

        return back()->with('success', 'Limit khusus Anggota berhasil disimpan.');
    }

    public function resetOverride(Request $request, Anggota $anggota, PotongGajiBulananService $service)
    {
        $validated = $request->validate([
            'alasan' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->resetMemberOverrideToGlobal(
            $anggota,
            $request->user()->id,
            $validated['alasan'] ?? 'Kembali ke limit umum aktif.'
        );

        return back()->with('success', 'Limit khusus Anggota dikembalikan ke Limit Umum.');
    }

    public function disableWaserba(Request $request, Anggota $anggota, PotongGajiBulananService $service)
    {
        $validated = $request->validate([
            'alasan' => ['required', 'string', 'max:1000'],
        ]);

        $service->setWaserbaCredit($anggota, false, $request->user()->id, $validated['alasan']);

        return back()->with('success', 'Kredit Waserba Anggota berhasil dinonaktifkan.');
    }

    public function enableWaserba(Request $request, Anggota $anggota, PotongGajiBulananService $service)
    {
        $validated = $request->validate([
            'alasan' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->setWaserbaCredit($anggota, true, $request->user()->id, $validated['alasan'] ?? 'Kredit Waserba diaktifkan kembali.');

        return back()->with('success', 'Kredit Waserba Anggota berhasil diaktifkan.');
    }
}
