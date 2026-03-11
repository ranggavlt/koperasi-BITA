<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pinjaman;
use App\Models\Karyawan;
use App\Services\MutasiKasService;
use Illuminate\Support\Facades\DB;

class PinjamanController extends Controller
{
    public function index()
    {
        $pinjaman = Pinjaman::with('karyawan')
            ->latest()
            ->paginate(10);

        $karyawan = Karyawan::orderBy('nama')->get();

        return view('pages.pinjaman.index', compact('pinjaman', 'karyawan'));
    }

    public function store(Request $request, MutasiKasService $mutasiKasService)
    {
        $validated = $request->validate([
            'karyawan_id' => 'required|exists:karyawan,id',
            'jumlah_pinjaman' => 'required|numeric|min:0',
            'bunga_persen' => 'nullable|numeric|min:0',
            'tenor_bulan' => 'required|integer|min:1',
            'tanggal_pinjaman' => 'required|date',
            'keterangan' => 'nullable|string',
        ], [
            'karyawan_id.required' => 'Karyawan wajib dipilih.',
            'jumlah_pinjaman.required' => 'Jumlah pinjaman wajib diisi.',
            'tenor_bulan.required' => 'Tenor pinjaman wajib diisi.',
            'tanggal_pinjaman.required' => 'Tanggal pinjaman wajib diisi.',
        ]);

        try {
            DB::transaction(function () use ($validated, $mutasiKasService) {
                $pinjaman = Pinjaman::create([
                    'karyawan_id' => $validated['karyawan_id'],
                    'jumlah_pinjaman' => $validated['jumlah_pinjaman'],
                    'bunga_persen' => $validated['bunga_persen'] ?? 0,
                    'tenor_bulan' => $validated['tenor_bulan'],
                    'sisa_pinjaman' => $validated['jumlah_pinjaman'],
                    'status' => 'aktif',
                    'tanggal_pinjaman' => $validated['tanggal_pinjaman'],
                    'keterangan' => $validated['keterangan'] ?? null,
                ]);

                $mutasiKasService->record([
                    'tipe' => 'keluar',
                    'jumlah' => $validated['jumlah_pinjaman'],
                    'keterangan' => 'Pencairan pinjaman karyawan',
                    'referensi_tipe' => Pinjaman::class,
                    'referensi_id' => $pinjaman->id,
                    'tanggal' => $validated['tanggal_pinjaman'],
                ]);
            });

            return redirect()
                ->route('pinjaman.index')
                ->with('success', 'Pinjaman berhasil dibuat.');
        } catch (\Throwable $e) {
            return back()->withErrors(['Error: ' . $e->getMessage()])->withInput();
        }
    }

    public function destroy($id, MutasiKasService $mutasiKasService)
    {
        $data = Pinjaman::findOrFail($id);

        try {
            DB::transaction(function () use ($data, $mutasiKasService) {
                $mutasiKasService->reverseByReference(Pinjaman::class, $data->id);
                $data->delete();
            });

            return redirect()
                ->route('pinjaman.index')
                ->with('success', 'Transaksi pinjaman berhasil dihapus.');
        } catch (\Throwable $e) {
            return back()->withErrors(['Error: ' . $e->getMessage()]);
        }
    }
}
