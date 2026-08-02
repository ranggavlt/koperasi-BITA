<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJenisSimpananRequest;
use App\Http\Requests\UpdateJenisSimpananRequest;
use App\Models\Akun;
use App\Models\JenisSimpanan;
use App\Services\JenisSimpananService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JenisSimpananController extends Controller
{
    public function index(): View
    {
        $jenisSimpanan = JenisSimpanan::query()
            ->with(['akun', 'latestRiwayat.changedBy'])
            ->withCount('simpanan')
            ->whereIn('kategori', array_keys(JenisSimpanan::KATEGORI))
            ->orderByRaw("case kategori when 'wajib' then 1 when 'manasuka' then 2 else 99 end")
            ->orderBy('nama_jenis')
            ->paginate(12)
            ->withQueryString();

        $activeCounts = JenisSimpanan::query()
            ->where('aktif', true)
            ->whereIn('kategori', array_keys(JenisSimpanan::KATEGORI))
            ->select('kategori', DB::raw('COUNT(*) as total'))
            ->groupBy('kategori')
            ->pluck('total', 'kategori');

        return view('pages.jenis-simpanan.index', [
            'jenisSimpanan' => $jenisSimpanan,
            'kategoriOptions' => JenisSimpanan::KATEGORI,
            'activeCounts' => $activeCounts,
        ]);
    }

    public function create(): View
    {
        return view('pages.jenis-simpanan.form', [
            'jenis' => new JenisSimpanan(['aktif' => true, 'berlaku_mulai' => now(config('app.timezone', 'Asia/Jakarta'))]),
            'akunOptions' => $this->akunOptions(),
            'kategoriOptions' => JenisSimpanan::KATEGORI,
            'action' => route('jenis-simpanan.store'),
            'method' => 'POST',
            'title' => 'Tambah Master Jenis Simpanan',
            'submitLabel' => 'Simpan Master',
        ]);
    }

    public function store(StoreJenisSimpananRequest $request, JenisSimpananService $service): RedirectResponse
    {
        try {
            $service->create($request->validated(), $request->user()?->id);

            return redirect()
                ->route('jenis-simpanan.index')
                ->with('success', 'Master Jenis Simpanan berhasil dibuat.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
    }

    public function edit(JenisSimpanan $jenisSimpanan): View
    {
        abort_unless(array_key_exists($jenisSimpanan->kategori, JenisSimpanan::KATEGORI), 404);

        return view('pages.jenis-simpanan.form', [
            'jenis' => $jenisSimpanan->load('akun', 'latestRiwayat.changedBy'),
            'akunOptions' => $this->akunOptions(),
            'kategoriOptions' => JenisSimpanan::KATEGORI,
            'action' => route('jenis-simpanan.update', $jenisSimpanan),
            'method' => 'PUT',
            'title' => 'Edit Master Jenis Simpanan',
            'submitLabel' => 'Simpan Perubahan',
        ]);
    }

    public function update(UpdateJenisSimpananRequest $request, JenisSimpanan $jenisSimpanan, JenisSimpananService $service): RedirectResponse
    {
        try {
            $service->update($jenisSimpanan, $request->validated(), $request->user()?->id);

            return redirect()
                ->route('jenis-simpanan.index')
                ->with('success', 'Master Jenis Simpanan berhasil diperbarui.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
    }

    private function akunOptions()
    {
        return Akun::query()
            ->aktif()
            ->whereIn('kategori', ['kewajiban', 'ekuitas'])
            ->where('posisi_saldo', 'kredit')
            ->orderBy('kode_akun')
            ->get();
    }
}
