<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ShuConfigController extends Controller
{
    public function index()
    {
        $shuConfig = \App\Models\ShuConfig::first() ?? new \App\Models\ShuConfig();
        return view('pages.shu-koperasi.config', compact('shuConfig'));
    }

    public function update(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'persen_pembina' => 'required|numeric|min:0|max:100',
            'persen_pengawas' => 'required|numeric|min:0|max:100',
            'persen_pengurus' => 'required|numeric|min:0|max:100',
            'persen_anggota' => 'required|numeric|min:0|max:100',
            'persen_dana_sosial' => 'required|numeric|min:0|max:100',
            'persen_dana_cadangan' => 'required|numeric|min:0|max:100',
            'persen_dana_pendidikan' => 'required|numeric|min:0|max:100',
            'persen_jasa_modal' => 'required|numeric|min:0|max:100',
            'persen_jasa_usaha' => 'required|numeric|min:0|max:100',
        ]);

        $totalPembagian = round(
            (float) $validated['persen_dana_cadangan']
            + (float) $validated['persen_anggota']
            + (float) $validated['persen_pengawas']
            + (float) $validated['persen_pembina']
            + (float) $validated['persen_pengurus']
            + (float) $validated['persen_dana_sosial']
            + (float) $validated['persen_dana_pendidikan'],
            2
        );

        if (abs($totalPembagian - 100) > 0.01) {
            return back()->withErrors(['persen_dana_cadangan' => 'Total persentase pembagian SHU harus tepat 100%.'])->withInput();
        }

        $totalJasaAnggota = round(
            (float) $validated['persen_jasa_modal']
            + (float) $validated['persen_jasa_usaha'],
            2
        );

        if (abs($totalJasaAnggota - 100) > 0.01) {
            return back()->withErrors(['persen_jasa_modal' => 'Total persentase Jasa Modal dan Jasa Usaha harus tepat 100%.'])->withInput();
        }

        $shuConfig = \App\Models\ShuConfig::first() ?? new \App\Models\ShuConfig();
        $shuConfig->fill($validated);
        $shuConfig->save();

        return redirect()->back()->with('success', 'Konfigurasi SHU berhasil diperbarui.');
    }
}
