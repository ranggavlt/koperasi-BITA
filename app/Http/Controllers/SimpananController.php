<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoresimpananRequest;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\JenisSimpanan;
use App\Models\Simpanan;
use App\Services\SimpananManualService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SimpananController extends Controller
{
    public function index(): View
    {
        $simpanan = Simpanan::query()
            ->with(['anggota.karyawan', 'karyawan', 'jenisSimpanan', 'ledger', 'mutasiKas.dompet'])
            ->latest()
            ->paginate(10);

        $anggota = Anggota::query()
            ->with('karyawan')
            ->aktif()
            ->orderBy('nomor_anggota')
            ->get();

        $jenis = JenisSimpanan::query()
            ->aktif()
            ->where(fn ($query) => $query
                ->whereNull('kode')
                ->orWhere('kode', '!=', JenisSimpanan::KODE_SIMPANAN_POKOK))
            ->where(fn ($query) => $query
                ->whereNull('kategori')
                ->orWhere('kategori', '!=', JenisSimpanan::KATEGORI_POKOK))
            ->orderBy('nama_jenis')
            ->get();

        $dompet = DompetKoperasi::query()
            ->with('akun')
            ->orderBy('nama_dompet')
            ->get();

        return view('pages.simpanan.index', compact('simpanan', 'anggota', 'jenis', 'dompet'));
    }

    public function store(StoresimpananRequest $request, SimpananManualService $service): RedirectResponse
    {
        try {
            $service->create($request->validated(), $request->user()?->id);

            return redirect()
                ->route('simpanan.index')
                ->with('success', 'Transaksi simpanan berhasil disimpan.');
        } catch (\Throwable $exception) {
            return back()
                ->withErrors(['simpanan' => $exception->getMessage()])
                ->withInput();
        }
    }
}
