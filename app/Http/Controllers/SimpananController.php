<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Simpanan;
use App\Models\Karyawan;
use App\Models\JenisSimpanan;
use App\Services\AkuntansiService;
use App\Services\MutasiKasService;
use Illuminate\Support\Facades\DB;

class SimpananController extends Controller
{
    public function index()
    {
        $simpanan = Simpanan::with(['karyawan', 'jenisSimpanan'])
            ->latest()
            ->paginate(10);

        $karyawan = Karyawan::orderBy('nama')->get();
        $jenis = JenisSimpanan::orderBy('nama_jenis')->get();

        return view('pages.simpanan.index', compact('simpanan', 'karyawan', 'jenis'));
    }

    public function store(Request $request, MutasiKasService $mutasiKasService, AkuntansiService $akuntansiService)
    {
        $validated = $request->validate([
            'karyawan_id' => 'required|exists:karyawan,id',
            'jenis_simpanan_id' => 'required|exists:jenis_simpanan,id',
            'jumlah' => 'required|numeric|min:0',
            'tanggal' => 'required|date',
            'keterangan' => 'nullable|string',
        ], [
            'karyawan_id.required' => 'Karyawan wajib dipilih.',
            'jenis_simpanan_id.required' => 'Jenis simpanan wajib dipilih.',
            'jumlah.required' => 'Jumlah simpanan wajib diisi.',
            'tanggal.required' => 'Tanggal simpanan wajib diisi.',
        ]);

        try {
            DB::transaction(function () use ($validated, $mutasiKasService, $akuntansiService) {
                $simpanan = Simpanan::create($validated);

                $mutasiKasService->record([
                    'tipe' => 'masuk',
                    'jumlah' => $validated['jumlah'],
                    'keterangan' => 'Penerimaan simpanan karyawan',
                    'referensi_tipe' => Simpanan::class,
                    'referensi_id' => $simpanan->id,
                    'tanggal' => $validated['tanggal'],
                ]);

                $akuntansiService->recordSimpanan($simpanan);
            });

            return redirect()
                ->route('simpanan.index')
                ->with('success', 'Transaksi simpanan berhasil disimpan.');
        } catch (\Throwable $e) {
            return back()->withErrors(['Error: ' . $e->getMessage()])->withInput();
        }
    }

    public function destroy($id, MutasiKasService $mutasiKasService, AkuntansiService $akuntansiService)
    {
        $data = Simpanan::findOrFail($id);

        try {
            DB::transaction(function () use ($data, $mutasiKasService, $akuntansiService) {
                $mutasiKasService->reverseByReference(Simpanan::class, $data->id);
                $akuntansiService->reverseByReference(Simpanan::class, $data->id);
                $data->delete();
            });

            return redirect()
                ->route('simpanan.index')
                ->with('success', 'Transaksi simpanan berhasil dihapus.');
        } catch (\Throwable $e) {
            return back()->withErrors(['Error: ' . $e->getMessage()]);
        }
    }
}
