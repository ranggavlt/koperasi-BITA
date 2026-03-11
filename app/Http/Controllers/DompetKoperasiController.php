<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DompetKoperasi;

class DompetKoperasiController extends Controller
{
    public function index()
    {
        $dompetKoperasi = DompetKoperasi::latest()->paginate(10);

        return view('pages.dompet-koperasi.index', compact('dompetKoperasi'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_dompet' => 'required|string|max:100',
        ], [
            'nama_dompet.required' => 'Nama dompet koperasi wajib diisi.',
        ]);

        DompetKoperasi::create([
            'nama_dompet' => $validated['nama_dompet'],
            'saldo' => 0,
        ]);

        return redirect()->route('dompet-koperasi.index')
            ->with('success', 'Dompet koperasi berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $data = DompetKoperasi::findOrFail($id);
        $dompetKoperasi = DompetKoperasi::latest()->paginate(10);

        return view('pages.dompet-koperasi.index', compact('data', 'dompetKoperasi'));
    }

    public function update(Request $request, $id)
    {
        $data = DompetKoperasi::findOrFail($id);

        $validated = $request->validate([
            'nama_dompet' => 'required|string|max:100',
        ], [
            'nama_dompet.required' => 'Nama dompet koperasi wajib diisi.',
        ]);

        $data->update([
            'nama_dompet' => $validated['nama_dompet'],
        ]);

        return redirect()->route('dompet-koperasi.index')
            ->with('success', 'Dompet koperasi berhasil diupdate.');
    }

    public function destroy($id)
    {
        $data = DompetKoperasi::findOrFail($id);

        $data->delete();

        return redirect()->route('dompet-koperasi.index')
            ->with('success', 'Dompet koperasi berhasil dihapus.');
    }
}
