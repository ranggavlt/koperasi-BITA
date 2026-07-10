<?php

namespace App\Http\Controllers;

use App\Models\Akun;
use App\Models\JenisSimpanan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JenisSimpananController extends Controller
{
    public function index()
    {
        $jenisSimpanan = JenisSimpanan::with('akun')->latest()->paginate(10);
        $akunSimpanan = $this->akunSimpanan();

        return view('pages.jenis-simpanan.index', compact('jenisSimpanan', 'akunSimpanan'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'akun_id' => [
                'required',
                Rule::exists('akun', 'id')->where(fn ($query) => $query
                    ->where('is_aktif', true)
                    ->whereIn('kategori', ['kewajiban', 'ekuitas'])),
            ],
            'nama_jenis' => 'required|string|max:100',
            'wajib' => 'required|in:0,1',
            'nominal_default' => 'nullable|numeric|min:0',
            'keterangan' => 'nullable|string',
        ], [
            'nama_jenis.required' => 'Nama jenis simpanan wajib diisi.',
            'wajib.required' => 'Status simpanan wajib dipilih.',
        ]);

        JenisSimpanan::create([
            'akun_id' => $validated['akun_id'],
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
        $jenisSimpanan = JenisSimpanan::with('akun')->latest()->paginate(10);
        $akunSimpanan = $this->akunSimpanan();

        return view('pages.jenis-simpanan.index', compact('data', 'jenisSimpanan', 'akunSimpanan'));
    }

    public function update(Request $request, $id)
    {
        $data = JenisSimpanan::findOrFail($id);

        $validated = $request->validate([
            'akun_id' => [
                'required',
                Rule::exists('akun', 'id')->where(fn ($query) => $query
                    ->where('is_aktif', true)
                    ->whereIn('kategori', ['kewajiban', 'ekuitas'])),
            ],
            'nama_jenis' => 'required|string|max:100',
            'wajib' => 'required|in:0,1',
            'nominal_default' => 'nullable|numeric|min:0',
            'keterangan' => 'nullable|string',
        ], [
            'nama_jenis.required' => 'Nama jenis simpanan wajib diisi.',
            'wajib.required' => 'Status simpanan wajib dipilih.',
        ]);

        $data->update([
            'akun_id' => $validated['akun_id'],
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

    private function akunSimpanan()
    {
        return Akun::query()
            ->aktif()
            ->whereIn('kategori', ['kewajiban', 'ekuitas'])
            ->orderBy('kode_akun')
            ->get();
    }
}
