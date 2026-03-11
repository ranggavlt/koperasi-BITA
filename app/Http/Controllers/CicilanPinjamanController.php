<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CicilanPinjaman;
use App\Models\Pinjaman;
use App\Services\MutasiKasService;
use Illuminate\Support\Facades\DB;

class CicilanPinjamanController extends Controller
{
    public function index()
    {
        $cicilanPinjaman = CicilanPinjaman::with('pinjaman.karyawan')
            ->latest()
            ->paginate(10);

        $pinjaman = Pinjaman::with('karyawan')
            ->where('status', 'aktif')
            ->where('sisa_pinjaman', '>', 0)
            ->latest()
            ->get();

        return view('pages.cicilan-pinjaman.index', compact('cicilanPinjaman', 'pinjaman'));
    }

    public function store(Request $request, MutasiKasService $mutasiKasService)
    {
        $validated = $request->validate([
            'pinjaman_id' => 'required|exists:pinjaman,id',
            'jumlah_cicilan' => 'required|numeric|min:0.01',
            'periode' => ['required', 'regex:/^\d{4}\-\d{2}$/'],
            'tanggal_bayar' => 'required|date',
        ], [
            'pinjaman_id.required' => 'Pinjaman wajib dipilih.',
            'jumlah_cicilan.required' => 'Jumlah cicilan wajib diisi.',
            'periode.required' => 'Periode cicilan wajib diisi.',
            'periode.regex' => 'Format periode harus YYYY-MM.',
            'tanggal_bayar.required' => 'Tanggal bayar wajib diisi.',
        ]);

        $pinjaman = Pinjaman::findOrFail($validated['pinjaman_id']);

        if ($pinjaman->status !== 'aktif' || (float) $pinjaman->sisa_pinjaman <= 0) {
            return redirect()
                ->route('cicilan-pinjaman.index')
                ->withErrors(['pinjaman_id' => 'Pinjaman ini sudah lunas atau tidak aktif.'])
                ->withInput();
        }

        if ((float) $validated['jumlah_cicilan'] > (float) $pinjaman->sisa_pinjaman) {
            return redirect()
                ->route('cicilan-pinjaman.index')
                ->withErrors(['jumlah_cicilan' => 'Jumlah cicilan melebihi sisa pinjaman.'])
                ->withInput();
        }

        DB::transaction(function () use ($validated, $pinjaman, $mutasiKasService) {
            $cicilanPinjaman = CicilanPinjaman::create([
                'pinjaman_id' => $validated['pinjaman_id'],
                'jumlah_cicilan' => $validated['jumlah_cicilan'],
                'periode' => $validated['periode'],
                'status' => 'sudah_bayar',
                'tanggal_bayar' => $validated['tanggal_bayar'],
            ]);

            $sisaPinjaman = max(0, (float) $pinjaman->sisa_pinjaman - (float) $validated['jumlah_cicilan']);

            $pinjaman->update([
                'sisa_pinjaman' => $sisaPinjaman,
                'status' => $sisaPinjaman <= 0 ? 'lunas' : 'aktif',
            ]);

            $mutasiKasService->record([
                'tipe' => 'masuk',
                'jumlah' => $validated['jumlah_cicilan'],
                'keterangan' => 'Pembayaran cicilan pinjaman',
                'referensi_tipe' => CicilanPinjaman::class,
                'referensi_id' => $cicilanPinjaman->id,
                'tanggal' => $validated['tanggal_bayar'],
            ]);
        });

        return redirect()
            ->route('cicilan-pinjaman.index')
            ->with('success', 'Cicilan pinjaman berhasil disimpan.');
    }

    public function destroy($id, MutasiKasService $mutasiKasService)
    {
        $data = CicilanPinjaman::findOrFail($id);

        DB::transaction(function () use ($data, $mutasiKasService) {
            $pinjaman = Pinjaman::find($data->pinjaman_id);

            if ($pinjaman && $data->status === 'sudah_bayar') {
                $sisaPinjaman = min(
                    (float) $pinjaman->jumlah_pinjaman,
                    (float) $pinjaman->sisa_pinjaman + (float) $data->jumlah_cicilan
                );

                $pinjaman->update([
                    'sisa_pinjaman' => $sisaPinjaman,
                    'status' => $sisaPinjaman <= 0 ? 'lunas' : 'aktif',
                ]);
            }

            $mutasiKasService->reverseByReference(CicilanPinjaman::class, $data->id);
            $data->delete();
        });

        return redirect()
            ->route('cicilan-pinjaman.index')
            ->with('success', 'Cicilan pinjaman berhasil dihapus.');
    }
}
