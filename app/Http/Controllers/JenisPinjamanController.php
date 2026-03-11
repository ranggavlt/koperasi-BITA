<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JenisPinjaman;

class JenisPinjamanController extends Controller
{
    public function index()
    {
        $jenisPinjaman = JenisPinjaman::latest()->paginate(10);

        return view('pages.jenis-pinjaman.index', compact('jenisPinjaman'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_pinjaman' => 'required|string|max:255',
            'bunga_persen' => 'nullable|numeric|min:0',
            'tenor_bulan' => 'nullable|integer|min:1',
            'keterangan' => 'nullable|string',
        ], [
            'nama_pinjaman.required' => 'Nama jenis pinjaman wajib diisi.',
        ]);

        JenisPinjaman::create([
            'nama_pinjaman' => $validated['nama_pinjaman'],
            'bunga_persen' => $validated['bunga_persen'] ?? 0,
            'tenor_bulan' => $validated['tenor_bulan'] ?? null,
            'keterangan' => $validated['keterangan'] ?? null,
        ]);

        return redirect()->route('jenis-pinjaman.index')
            ->with('success', 'Jenis pinjaman berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $data = JenisPinjaman::findOrFail($id);
        $jenisPinjaman = JenisPinjaman::latest()->paginate(10);

        return view('pages.jenis-pinjaman.index', compact('data', 'jenisPinjaman'));
    }

    public function update(Request $request, $id)
    {
        $data = JenisPinjaman::findOrFail($id);

        $validated = $request->validate([
            'nama_pinjaman' => 'required|string|max:255',
            'bunga_persen' => 'nullable|numeric|min:0',
            'tenor_bulan' => 'nullable|integer|min:1',
            'keterangan' => 'nullable|string',
        ], [
            'nama_pinjaman.required' => 'Nama jenis pinjaman wajib diisi.',
        ]);

        $data->update([
            'nama_pinjaman' => $validated['nama_pinjaman'],
            'bunga_persen' => $validated['bunga_persen'] ?? 0,
            'tenor_bulan' => $validated['tenor_bulan'] ?? null,
            'keterangan' => $validated['keterangan'] ?? null,
        ]);

        return redirect()->route('jenis-pinjaman.index')
            ->with('success', 'Jenis pinjaman berhasil diupdate.');
    }

    public function destroy($id)
    {
        $data = JenisPinjaman::findOrFail($id);

        $data->delete();

        return redirect()->route('jenis-pinjaman.index')
            ->with('success', 'Jenis pinjaman berhasil dihapus.');
    }
}
