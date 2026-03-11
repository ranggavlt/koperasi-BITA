<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MutasiKas;
use App\Models\DompetKoperasi;
use App\Services\MutasiKasService;

class MutasiKasController extends Controller
{
    public function index(MutasiKasService $mutasiKasService)
    {
        $hasDompet = $mutasiKasService->hasAvailableDompet();

        if ($hasDompet) {
            $mutasiKasService->backfillHistoricalTransactions();
        }

        $data = MutasiKas::with('dompet')
            ->latest()
            ->get();

        return view('pages.mutasi-kas.index', compact('data', 'hasDompet'));
    }



    public function create()
    {
        return redirect()->route('mutasi-kas.index');
    }

    public function store(Request $request, MutasiKasService $mutasiKasService)
    {
        $validated = $request->validate([
            'dompet_id' => 'required|exists:dompet_koperasi,id',
            'tipe' => 'required|in:masuk,keluar',
            'jumlah' => 'required|numeric|min:0.01',
            'tanggal' => 'required|date',
            'keterangan' => 'nullable|string',
        ]);

        $mutasiKasService->record($validated);

        return redirect()
            ->route('mutasi-kas.index')
            ->with('success', 'Mutasi kas berhasil disimpan.');
    }

    public function destroy($id, MutasiKasService $mutasiKasService)
    {
        $data = MutasiKas::findOrFail($id);

        $mutasiKasService->deleteAndReverse($data);

        return redirect()
            ->route('mutasi-kas.index')
            ->with('success', 'Mutasi kas berhasil dihapus.');
    }
}
