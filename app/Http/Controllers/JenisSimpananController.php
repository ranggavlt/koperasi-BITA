<?php

namespace App\Http\Controllers;

use App\Models\Akun;
use App\Models\JenisSimpanan;
use App\Http\Requests\StoreJenisSimpananRequest;
use App\Http\Requests\UpdateJenisSimpananRequest;
use App\Services\JenisSimpananService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class JenisSimpananController extends Controller
{
    public function __construct(private readonly JenisSimpananService $service)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'kategori' => ['nullable', 'in:' . implode(',', array_keys(JenisSimpanan::KATEGORI))],
            'status' => ['nullable', 'in:aktif,nonaktif'],
        ]);

        $jenisSimpanan = JenisSimpanan::query()
            ->with(['akun', 'creator', 'updater', 'latestRiwayat.changedBy'])
            ->when($filters['kategori'] ?? null, fn ($query, $kategori) => $query->where('kategori', $kategori))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('aktif', $status === 'aktif'))
            ->orderByRaw("case kategori when 'pokok' then 1 when 'wajib' then 2 when 'sukarela' then 3 else 9 end")
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $missingActiveCategories = $this->service->missingActiveCategories();

        return view('pages.jenis-simpanan.index', compact('jenisSimpanan', 'missingActiveCategories'));
    }

    public function create()
    {
        $jenisSimpanan = null;
        $akunSimpanan = $this->akunSimpanan();
        $mode = 'create';

        return view('pages.jenis-simpanan.form', compact('jenisSimpanan', 'akunSimpanan', 'mode'));
    }

    public function store(StoreJenisSimpananRequest $request)
    {
        $this->service->create($request->validated(), $request->user()?->id);

        return redirect()->route('jenis-simpanan.index')
            ->with('success', 'Jenis simpanan berhasil ditambahkan.');
    }

    public function edit(JenisSimpanan $jenis_simpanan)
    {
        $jenisSimpanan = $jenis_simpanan;
        $akunSimpanan = $this->akunSimpanan();
        $mode = 'edit';

        return view('pages.jenis-simpanan.form', compact('jenisSimpanan', 'akunSimpanan', 'mode'));
    }

    public function update(UpdateJenisSimpananRequest $request, JenisSimpanan $jenis_simpanan)
    {
        $this->service->update($jenis_simpanan, $request->validated(), $request->user()?->id);

        return redirect()->route('jenis-simpanan.index')
            ->with('success', 'Jenis simpanan berhasil diupdate.');
    }

    public function destroy(JenisSimpanan $jenis_simpanan)
    {
        try {
            $this->service->deleteUnused($jenis_simpanan);
        } catch (ValidationException $exception) {
            return back()->withErrors([
                'jenis_simpanan' => $exception->errors()['jenis_simpanan'][0] ?? 'Jenis simpanan tidak dapat dihapus.',
            ]);
        }

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
}
