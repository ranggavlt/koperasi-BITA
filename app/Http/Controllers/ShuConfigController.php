<?php

namespace App\Http\Controllers;

use App\Models\ShuConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ShuConfigController extends Controller
{
    public function index()
    {
        $shuConfig = ShuConfig::query()
            ->approved()
            ->orderByDesc('berlaku_mulai')
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->first() ?? new ShuConfig();
        $configHistory = ShuConfig::query()
            ->with('approver:id,name')
            ->orderByDesc('berlaku_mulai')
            ->orderByDesc('id')
            ->paginate(10);

        return view('pages.shu-koperasi.config', compact('shuConfig', 'configHistory'));
    }

    public function update(Request $request)
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
            'berlaku_mulai' => [
                'required',
                'date',
                Rule::unique('shu_configs', 'berlaku_mulai')
                    ->where('status_persetujuan', ShuConfig::STATUS_APPROVED),
            ],
            'dasar_persetujuan' => 'required|string|min:5|max:1000',
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

        DB::transaction(function () use ($validated, $request): void {
            ShuConfig::query()->create([
                ...$validated,
                'status_persetujuan' => ShuConfig::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', 'Versi konfigurasi SHU berhasil disimpan. Periode lama tidak berubah.');
    }
}
