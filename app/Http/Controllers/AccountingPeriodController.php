<?php

namespace App\Http\Controllers;

use App\Models\PeriodeAkuntansi;
use App\Services\AccountingPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingPeriodController extends Controller
{
    public function index(): View
    {
        return view('pages.akuntansi.periode', [
            'periods' => PeriodeAkuntansi::query()->with(['creator', 'closer', 'closingJournal'])->latest('tanggal_mulai')->paginate(12),
        ]);
    }

    public function store(Request $request, AccountingPeriodService $service): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:periode_akuntansi,kode'],
            'nama' => ['required', 'string', 'max:150'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
        ]);
        $service->create($data, $request->user()->id);

        return back()->with('success', 'Periode akuntansi berhasil dibuat dalam status open.');
    }

    public function close(PeriodeAkuntansi $periodeAkuntansi, Request $request, AccountingPeriodService $service): RedirectResponse
    {
        $reason = $request->validate(['closing_reason' => ['required', 'string', 'min:5', 'max:1000']])['closing_reason'];
        $service->close($periodeAkuntansi, $reason, $request->user()->id);

        return back()->with('success', 'Periode berhasil ditutup, dikunci, dan laba jurnal telah disnapshot.');
    }
}
