<?php

namespace App\Http\Controllers;

use App\Models\PengurusKoperasi;
use App\Models\ShuKoperasi;
use App\Models\ShuTransaksi;
use App\Services\ShuKoperasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShuKoperasiController extends Controller
{
    public function index()
    {
        $data = ShuKoperasi::query()
            ->withCount('transaksi')
            ->latest()
            ->paginate(10);

        $shuConfig = \App\Models\ShuConfig::first();

        return view('pages.shu-koperasi.index', compact('data', 'shuConfig'));
    }

    public function store(Request $request, ShuKoperasiService $shuKoperasiService)
    {
        $validated = $this->validateShuKoperasi($request);

        $shuKoperasiService->create($validated);

        return redirect()
            ->route('shu-koperasi.index')
            ->with('success', 'Periode SHU koperasi berhasil dibuat.');
    }

    public function show(ShuKoperasi $shuKoperasi)
    {
        $shuKoperasi->load([
            'transaksi' => fn ($query) => $query->latest('tanggal')->latest('id'),
            'anggotaPembagian' => fn ($query) => $query
                ->with('karyawan')
                ->orderByDesc('nominal_shu')
                ->orderBy('karyawan_id'),
        ]);

        $jumlahPengurus = PengurusKoperasi::query()->aktif()->count();
        $estimasiPengurus = $jumlahPengurus > 0
            ? round((float) $shuKoperasi->nominal_pengurus / $jumlahPengurus, 2)
            : 0;

        return view('pages.shu-koperasi.show', compact(
            'shuKoperasi',
            'jumlahPengurus',
            'estimasiPengurus'
        ));
    }

    public function update(Request $request, ShuKoperasi $shuKoperasi, ShuKoperasiService $shuKoperasiService)
    {
        $validated = $this->validateShuKoperasi($request);

        $shuKoperasiService->update($shuKoperasi, $validated);

        return redirect()
            ->route('shu-koperasi.show', $shuKoperasi)
            ->with('success', 'Konfigurasi SHU koperasi berhasil diperbarui.');
    }

    public function destroy(ShuKoperasi $shuKoperasi)
    {
        abort(410, 'Hard delete periode SHU dinonaktifkan permanen.');
    }

    public function refresh(ShuKoperasi $shuKoperasi, ShuKoperasiService $shuKoperasiService)
    {
        $shuKoperasiService->refresh($shuKoperasi);

        return redirect()
            ->route('shu-koperasi.show', $shuKoperasi)
            ->with('success', 'Perhitungan SHU berhasil diperbarui dari data terbaru.');
    }

    public function storeTransaksi(Request $request, ShuKoperasi $shuKoperasi, ShuKoperasiService $shuKoperasiService)
    {
        $validated = $this->validateShuTransaksi($request, $shuKoperasi);

        $shuKoperasiService->addTransaksi($shuKoperasi, $validated);

        return redirect()
            ->route('shu-koperasi.show', $shuKoperasi)
            ->with('success', 'Transaksi SHU berhasil ditambahkan.');
    }

    public function destroyTransaksi(
        ShuKoperasi $shuKoperasi,
        ShuTransaksi $shuTransaksi,
        ShuKoperasiService $shuKoperasiService
    ) {
        abort(410, 'Hard delete transaksi SHU dinonaktifkan permanen.');
    }

    protected function validateShuKoperasi(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'judul' => 'required|string|max:255',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'persen_dana_cadangan' => 'required|numeric|min:0|max:100',
            'persen_shu_anggota' => 'required|numeric|min:0|max:100',
            'persen_pengawas' => 'required|numeric|min:0|max:100',
            'persen_pembina' => 'required|numeric|min:0|max:100',
            'persen_pengurus' => 'required|numeric|min:0|max:100',
            'persen_dana_sosial' => 'required|numeric|min:0|max:100',
            'persen_dana_pendidikan' => 'required|numeric|min:0|max:100',
            'persen_jasa_modal' => 'required|numeric|min:0|max:100',
            'persen_jasa_usaha' => 'required|numeric|min:0|max:100',
            'keterangan' => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request) {
            $totalPembagian = round(
                (float) $request->input('persen_dana_cadangan', 0)
                + (float) $request->input('persen_shu_anggota', 0)
                + (float) $request->input('persen_pengawas', 0)
                + (float) $request->input('persen_pembina', 0)
                + (float) $request->input('persen_pengurus', 0)
                + (float) $request->input('persen_dana_sosial', 0)
                + (float) $request->input('persen_dana_pendidikan', 0),
                2
            );

            if (abs($totalPembagian - 100) > 0.01) {
                $validator->errors()->add('persen_dana_cadangan', 'Total persentase pembagian SHU harus tepat 100%.');
            }

            $totalJasaAnggota = round(
                (float) $request->input('persen_jasa_modal', 0)
                + (float) $request->input('persen_jasa_usaha', 0),
                2
            );

            if (abs($totalJasaAnggota - 100) > 0.01) {
                $validator->errors()->add('persen_jasa_modal', 'Total persentase Jasa Modal dan Jasa Usaha harus tepat 100%.');
            }
        });

        return $validator->validate();
    }

    protected function validateShuTransaksi(Request $request, ShuKoperasi $shuKoperasi): array
    {
        $validator = Validator::make($request->all(), [
            'jenis' => 'required|in:pendapatan,biaya',
            'tanggal' => 'required|date',
            'jumlah' => 'required|numeric|min:0.01',
            'keterangan' => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request, $shuKoperasi) {
            $tanggal = $request->input('tanggal');

            if (! $tanggal) {
                return;
            }

            if ($tanggal < $shuKoperasi->tanggal_mulai->toDateString() || $tanggal > $shuKoperasi->tanggal_selesai->toDateString()) {
                $validator->errors()->add('tanggal', 'Tanggal transaksi SHU harus berada di dalam periode SHU yang dipilih.');
            }
        });

        return $validator->validate();
    }

    public function cairkan(\Illuminate\Http\Request $request, \App\Models\ShuAnggota $shuAnggota)
    {
        abort(410, 'Pencairan SHU belum disetujui dan tetap dinonaktifkan.');
    }
}
