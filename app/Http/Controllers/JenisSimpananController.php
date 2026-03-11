<?php

namespace App\Http\Controllers;

use App\Models\JenisSimpanan;
use Illuminate\Http\Request;

class JenisSimpananController extends Controller
{
    public function index()
    {
        $jenisSimpanan = JenisSimpanan::latest()->paginate(10);

        return view('pages.jenis-simpanan.index', compact('jenisSimpanan'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_jenis' => 'required|string|max:100',
            'wajib' => 'required|in:0,1',
            'nominal_default' => 'nullable|numeric|min:0',
            'keterangan' => 'nullable|string',
        ], [
            'nama_jenis.required' => 'Nama jenis simpanan wajib diisi.',
            'wajib.required' => 'Status simpanan wajib dipilih.',
        ]);

        JenisSimpanan::create([
            'nama_jenis' => $validated['nama_jenis'],
            'wajib' => (int) $validated['wajib'] === 1,
            'nominal_default' => $validated['nominal_default'] ?? null,
            'keterangan' => $validated['keterangan'] ?? null,
        ]);

        return redirect()->route('jenis-simpanan.index')
            ->with('success', 'Jenis simpanan berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $data = JenisSimpanan::findOrFail($id);
        $jenisSimpanan = JenisSimpanan::latest()->paginate(10);

        return view('pages.jenis-simpanan.index', compact('data', 'jenisSimpanan'));
    }

    public function update(Request $request, $id)
    {
        $data = JenisSimpanan::findOrFail($id);

        $validated = $request->validate([
            'nama_jenis' => 'required|string|max:100',
            'wajib' => 'required|in:0,1',
            'nominal_default' => 'nullable|numeric|min:0',
            'keterangan' => 'nullable|string',
        ], [
            'nama_jenis.required' => 'Nama jenis simpanan wajib diisi.',
            'wajib.required' => 'Status simpanan wajib dipilih.',
        ]);

        $data->update([
            'nama_jenis' => $validated['nama_jenis'],
            'wajib' => (int) $validated['wajib'] === 1,
            'nominal_default' => $validated['nominal_default'] ?? null,
            'keterangan' => $validated['keterangan'] ?? null,
        ]);

        return redirect()->route('jenis-simpanan.index')
            ->with('success', 'Jenis simpanan berhasil diupdate.');
    }

    public function destroy($id)
    {
        $data = JenisSimpanan::findOrFail($id);
        $data->delete();

        return redirect()->route('jenis-simpanan.index')
            ->with('success', 'Jenis simpanan berhasil dihapus.');
    }
}
