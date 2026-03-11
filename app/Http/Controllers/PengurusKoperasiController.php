<?php

namespace App\Http\Controllers;

use App\Models\PengurusKoperasi;
use Illuminate\Http\Request;

class PengurusKoperasiController extends Controller
{
    public function index()
    {
        $pengurusKoperasi = PengurusKoperasi::orderBy('id', 'desc')->paginate(10);

        return view('pages.pengurus-koperasi.index', compact('pengurusKoperasi'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:pengurus_koperasi,email',
            'telepon' => 'nullable|string|max:50',
            'jabatan' => 'required|string|max:255',
        ]);

        PengurusKoperasi::create($validated);

        return redirect()
            ->route('pengurus-koperasi.index')
            ->with('success', 'Data pengurus koperasi berhasil ditambahkan.');
    }

    public function edit(PengurusKoperasi $pengurusKoperasi)
    {
        $data = $pengurusKoperasi;
        $pengurusKoperasi = PengurusKoperasi::orderBy('id', 'desc')->paginate(10);

        return view('pages.pengurus-koperasi.index', compact('data', 'pengurusKoperasi'));
    }

    public function update(Request $request, PengurusKoperasi $pengurusKoperasi)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:pengurus_koperasi,email,' . $pengurusKoperasi->id,
            'telepon' => 'nullable|string|max:50',
            'jabatan' => 'required|string|max:255',
        ]);

        $pengurusKoperasi->update($validated);

        return redirect()
            ->route('pengurus-koperasi.index')
            ->with('success', 'Data pengurus koperasi berhasil diupdate.');
    }

    public function destroy(PengurusKoperasi $pengurusKoperasi)
    {
        $pengurusKoperasi->delete();

        return redirect()
            ->route('pengurus-koperasi.index')
            ->with('success', 'Data pengurus koperasi berhasil dihapus.');
    }
}
