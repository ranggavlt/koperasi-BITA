<?php

namespace App\Http\Controllers;

use App\Models\PeriodeAkuntansi;
use App\Models\ShuConfig;
use App\Models\ShuKoperasi;
use App\Services\ShuKoperasiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShuKoperasiController extends Controller
{
    public function index(): View
    {
        return view('pages.shu-koperasi.index', [
            'data' => ShuKoperasi::query()->with('periodeAkuntansi')->latest()->paginate(10),
            'closedPeriods' => PeriodeAkuntansi::query()->where('status', PeriodeAkuntansi::STATUS_CLOSED)->whereDoesntHave('shuKoperasi')->orderByDesc('tanggal_selesai')->get(),
            'shuConfig' => ShuConfig::effectiveFor(now()),
        ]);
    }

    public function store(Request $request, ShuKoperasiService $service): RedirectResponse
    {
        $data = $request->validate(['periode_akuntansi_id' => ['required', 'exists:periode_akuntansi,id'], 'judul' => ['nullable', 'string', 'max:255'], 'keterangan' => ['nullable', 'string', 'max:1000']]);
        $shu = $service->create($data, $request->user()->id);
        return redirect()->route('shu-koperasi.show', $shu)->with('success', 'Perhitungan SHU dibuat otomatis dari snapshot periode tertutup.');
    }

    public function show(ShuKoperasi $shuKoperasi): View
    {
        $shuKoperasi->load(['periodeAkuntansi', 'anggotaPembagian.karyawan', 'creator', 'approver', 'poster', 'allocationJournal.details']);
        return view('pages.shu-koperasi.show', compact('shuKoperasi'));
    }

    public function approve(ShuKoperasi $shuKoperasi, Request $request, ShuKoperasiService $service): RedirectResponse
    {
        $reason = $request->validate(['approval_reason' => ['required', 'string', 'min:5', 'max:1000']])['approval_reason'];
        $service->approve($shuKoperasi, $reason, $request->user()->id);
        return back()->with('success', 'Perhitungan SHU disetujui dan siap diposting.');
    }

    public function post(ShuKoperasi $shuKoperasi, Request $request, ShuKoperasiService $service): RedirectResponse
    {
        $service->post($shuKoperasi, $request->user()->id);
        return back()->with('success', 'Alokasi SHU berhasil diposting dan sumber Dana Sosial terbentuk otomatis.');
    }

    public function reverse(ShuKoperasi $shuKoperasi, Request $request, ShuKoperasiService $service): RedirectResponse
    {
        $reason = $request->validate(['reversal_reason' => ['required', 'string', 'min:5', 'max:1000']])['reversal_reason'];
        $service->reverse($shuKoperasi, $reason, $request->user()->id);
        return back()->with('success', 'Alokasi SHU direversal melalui counter-entry; posting asli tetap tersimpan.');
    }
}
