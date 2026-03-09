<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CicilanPinjaman;
use App\Models\Pinjaman;

class CicilanPinjamanController extends Controller
{
    public function index()
    {
        $data = CicilanPinjaman::with('pinjaman')
            ->latest()
            ->get();

        return view('pages.cicilan-pinjaman.index', compact('data'));
    }

    public function create()
    {
        $pinjaman = Pinjaman::all();

        return view('pages.cicilan-pinjaman.create', compact('pinjaman'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'pinjaman_id' => 'required',
            'jumlah_cicilan' => 'required|numeric',
            'periode' => 'required'
        ]);

        CicilanPinjaman::create([
            'pinjaman_id' => $request->pinjaman_id,
            'jumlah_cicilan' => $request->jumlah_cicilan,
            'periode' => $request->periode,
            'status' => 'sudah_bayar',
            'tanggal_bayar' => now()
        ]);

        return redirect()
            ->route('cicilan-pinjaman.index')
            ->with('success', 'Cicilan berhasil disimpan');
    }

    public function destroy($id)
    {
        $data = CicilanPinjaman::findOrFail($id);

        $data->delete();

        return redirect()
            ->route('cicilan-pinjaman.index')
            ->with('success', 'Data berhasil dihapus');
    }
}