<?php

namespace App\Http\Controllers;

use App\Models\Akun;
use App\Models\JenisSimpanan;
use App\Models\Simpanan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

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
                    ->whereIn('kategori', ['kewajiban', 'ekuitas'])
                    ->where('posisi_saldo', 'kredit')),
            ],
            'kode' => 'nullable|string|max:60|unique:jenis_simpanan,kode',
            'nama_jenis' => 'required|string|max:100',
            'wajib' => 'required|in:0,1',
            'aktif' => 'nullable|in:0,1',
            'nominal_default' => 'nullable|numeric|min:0',
            'keterangan' => 'nullable|string',
        ], [
            'nama_jenis.required' => 'Nama jenis simpanan wajib diisi.',
            'wajib.required' => 'Status simpanan wajib dipilih.',
        ]);

        $kode = $this->normalizeKode($validated['kode'] ?? null, $validated['nama_jenis']);
        $aktif = (int) ($validated['aktif'] ?? 1) === 1;
        $this->assertSingleActiveSimpananPokok($kode, $aktif);

        JenisSimpanan::create([
            'akun_id' => $validated['akun_id'],
            'kode' => $kode,
            'nama_jenis' => $validated['nama_jenis'],
            'wajib' => (int) $validated['wajib'] === 1,
            'aktif' => $aktif,
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
                    ->whereIn('kategori', ['kewajiban', 'ekuitas'])
                    ->where('posisi_saldo', 'kredit')),
            ],
            'kode' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('jenis_simpanan', 'kode')->ignore($data->id),
            ],
            'nama_jenis' => 'required|string|max:100',
            'wajib' => 'required|in:0,1',
            'aktif' => 'nullable|in:0,1',
            'nominal_default' => 'nullable|numeric|min:0',
            'keterangan' => 'nullable|string',
        ], [
            'nama_jenis.required' => 'Nama jenis simpanan wajib diisi.',
            'wajib.required' => 'Status simpanan wajib dipilih.',
        ]);

        $kode = $this->normalizeKode($validated['kode'] ?? $data->kode, $validated['nama_jenis']);
        if ($data->kode === JenisSimpanan::KODE_SIMPANAN_POKOK && $kode !== JenisSimpanan::KODE_SIMPANAN_POKOK) {
            $used = Simpanan::query()
                ->where('jenis_simpanan_id', $data->id)
                ->orWhere('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_POKOK)
                ->exists();

            if ($used) {
                return back()
                    ->withErrors(['kode' => 'Kode SIMPANAN_POKOK tidak boleh diubah karena sudah dipakai transaksi.'])
                    ->withInput();
            }
        }

        $aktif = (int) ($validated['aktif'] ?? (int) $data->aktif) === 1;
        $this->assertSingleActiveSimpananPokok($kode, $aktif, $data->id);

        $data->update([
            'akun_id' => $validated['akun_id'],
            'kode' => $kode,
            'nama_jenis' => $validated['nama_jenis'],
            'wajib' => (int) $validated['wajib'] === 1,
            'aktif' => $aktif,
            'nominal_default' => $validated['nominal_default'] ?? null,
            'keterangan' => $validated['keterangan'] ?? null,
        ]);

        return redirect()->route('jenis-simpanan.index')
            ->with('success', 'Jenis simpanan berhasil diupdate.');
    }

    public function destroy($id)
    {
        $data = JenisSimpanan::findOrFail($id);

        if (Simpanan::query()->where('jenis_simpanan_id', $data->id)->exists()) {
            return back()->withErrors([
                'jenis_simpanan' => 'Jenis simpanan yang sudah dipakai transaksi tidak boleh dihapus permanen.',
            ]);
        }

        $data->delete();

        return redirect()->route('jenis-simpanan.index')
            ->with('success', 'Jenis simpanan berhasil dihapus.');
    }

    private function akunSimpanan()
    {
        return Akun::query()
            ->aktif()
            ->whereIn('kategori', ['kewajiban', 'ekuitas'])
            ->where('posisi_saldo', 'kredit')
            ->orderBy('kode_akun')
            ->get();
    }

    private function normalizeKode(?string $kode, string $nama): string
    {
        $candidate = trim((string) ($kode ?: $nama));

        return Str::upper(Str::slug($candidate, '_'));
    }

    private function assertSingleActiveSimpananPokok(string $kode, bool $aktif, ?int $ignoreId = null): void
    {
        if ($kode !== JenisSimpanan::KODE_SIMPANAN_POKOK || ! $aktif) {
            return;
        }

        $query = JenisSimpanan::query()
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->where('aktif', true);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'kode' => 'Hanya boleh ada satu Master Simpanan Pokok aktif.',
            ]);
        }
    }
}
