<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorepinjamanRequest;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\Pinjaman;
use App\Services\PinjamanKoperasiService;
use App\Services\PotongGajiBulananService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PinjamanController extends Controller
{
    public function index()
    {
        $pinjaman = Pinjaman::with(['anggota.karyawan', 'karyawan', 'dompet', 'jadwalCicilan'])
            ->latest()
            ->paginate(10);

        $anggota = Anggota::query()
            ->with('karyawan')
            ->aktif()
            ->whereHas('karyawan', fn ($query) => $query->aktif())
            ->whereDoesntHave('pinjaman', fn ($query) => $query->where('status', Pinjaman::STATUS_AKTIF))
            ->orderBy('nomor_anggota')
            ->get();

        $dompet = DompetKoperasi::query()
            ->with('akun')
            ->orderBy('nama_dompet')
            ->get();

        return view('pages.pinjaman.index', compact('pinjaman', 'anggota', 'dompet'));
    }

    public function store(StorepinjamanRequest $request, PinjamanKoperasiService $service)
    {
        try {
            $pinjaman = $service->create($request->validated(), $request->user()->id);

            return redirect()
                ->route('pinjaman.show', $pinjaman)
                ->with('success', 'Pinjaman berhasil dicairkan dan jadwal cicilan otomatis dibuat.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            return back()
                ->withErrors(['pinjaman' => 'Pinjaman gagal dicairkan: ' . $exception->getMessage()])
                ->withInput();
        }
    }

    public function show(Pinjaman $pinjaman)
    {
        $pinjaman->load(['anggota.karyawan', 'dompet.akun', 'jadwalCicilan.cicilanPembayaran', 'mutasiKas', 'jurnal.details']);
        $dompetKas = DompetKoperasi::query()
            ->with('akun')
            ->kas()
            ->orderBy('nama_dompet')
            ->get();

        return view('pages.pinjaman.show', compact('pinjaman', 'dompetKas'));
    }

    public function payCashSchedule(Request $request, Pinjaman $pinjaman, PotongGajiBulananService $service)
    {
        $validated = $request->validate([
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
        ]);

        $service->payScheduledCash($pinjaman, DompetKoperasi::findOrFail($validated['dompet_id']), $request->user()->id);

        return redirect()
            ->route('pinjaman.show', $pinjaman)
            ->with('success', 'Cicilan terjadwal tunai berhasil dibayar.');
    }

    public function payCashFull(Request $request, Pinjaman $pinjaman, PotongGajiBulananService $service)
    {
        $validated = $request->validate([
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
        ]);

        $service->payFullCash($pinjaman, DompetKoperasi::findOrFail($validated['dompet_id']), $request->user()->id);

        return redirect()
            ->route('pinjaman.show', $pinjaman)
            ->with('success', 'Seluruh sisa Pinjaman berhasil dilunasi tunai.');
    }
}
