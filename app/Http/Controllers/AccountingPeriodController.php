<?php

namespace App\Http\Controllers;

use App\Models\PeriodeAkuntansi;
use App\Services\AccountingPeriodService;
use Illuminate\Http\Request;

class AccountingPeriodController extends Controller
{
    public function __construct(private readonly AccountingPeriodService $service) {}

    public function index()
    {
        return view('pages.akuntansi.periode.index', ['periods' => PeriodeAkuntansi::query()->with('closer')->latest('tanggal_mulai')->paginate(15)]);
    }

    public function create()
    {
        return view('pages.akuntansi.periode.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:periode_akuntansi,kode'],
            'nama' => ['required', 'string', 'max:120'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
        ]);
        $period = $this->service->create($validated, (int) $request->user()->id);
        return redirect()->route('akuntansi.periode.show', $period)->with('success', 'Periode pembukuan berhasil dimulai.');
    }

    public function show(PeriodeAkuntansi $periode)
    {
        return view('pages.akuntansi.periode.show', ['period' => $periode->load(['creator', 'closer', 'closingJournal.details'])]);
    }

    public function close(PeriodeAkuntansi $periode)
    {
        $this->service->close($periode, (int) auth()->id());
        return back()->with('success', 'Tutup buku berhasil diproses dan tanggal periode sudah dikunci.');
    }
}
