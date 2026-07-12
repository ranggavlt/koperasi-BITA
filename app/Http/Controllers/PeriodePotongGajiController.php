<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\LimitPotongGajiAnggota;
use App\Models\PeriodePotongGaji;
use App\Services\PotongGajiBulananService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PeriodePotongGajiController extends Controller
{
    public function index(Request $request)
    {
        $periodeList = PeriodePotongGaji::query()
            ->withCount('limits')
            ->orderByDesc('periode')
            ->paginate(12);

        $selectedPeriode = $request->integer('periode_id')
            ? PeriodePotongGaji::query()->find($request->integer('periode_id'))
            : PeriodePotongGaji::query()->orderByDesc('periode')->first();

        $limits = collect();
        $anggotaAktif = Anggota::query()
            ->with(['karyawan', 'pinjaman.jadwalCicilan'])
            ->whereHas('karyawan')
            ->orderBy('nomor_anggota')
            ->get();

        if ($selectedPeriode) {
            $limits = $selectedPeriode->limits()
                ->with(['anggota.karyawan', 'pemakaian', 'riwayat', 'dompetPenerimaan'])
                ->get()
                ->keyBy('anggota_id');
        }

        return view('pages.periode-potong-gaji.index', compact('periodeList', 'selectedPeriode', 'limits', 'anggotaAktif'));
    }

    public function storePeriode(Request $request, PotongGajiBulananService $service)
    {
        $validated = $request->validate([
            'periode' => ['required', 'date'],
        ]);

        $periode = $service->createPeriodeDraft($validated['periode'], $request->user()->id);

        return redirect()
            ->route('periode-potong-gaji.index', ['periode_id' => $periode->id])
            ->with('success', 'Periode potong gaji berhasil disiapkan.');
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
}
