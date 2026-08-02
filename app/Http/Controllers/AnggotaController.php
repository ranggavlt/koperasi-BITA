<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnggotaRequest;
use App\Http\Requests\UpdateAnggotaRequest;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Services\MasterDataKoperasiService;

class AnggotaController extends Controller
{
    public function index()
    {
        return $this->renderIndex();
    }

    public function store(StoreAnggotaRequest $request, MasterDataKoperasiService $service)
    {
        $service->createAnggota($request->validated());

        return redirect()->route('anggota.index')
            ->with('success', 'Karyawan berhasil didaftarkan sebagai Anggota aktif.');
    }

    public function edit(Anggota $anggota)
    {
        return $this->renderIndex($anggota->load('karyawan'));
    }

    public function update(
        UpdateAnggotaRequest $request,
        Anggota $anggota,
        MasterDataKoperasiService $service
    ) {
        $service->updateAnggota($anggota, $request->validated());

        return redirect()->route('anggota.index')
            ->with('success', 'Data Anggota berhasil diperbarui.');
    }

    public function deactivate(Anggota $anggota, MasterDataKoperasiService $service)
    {
        $service->deactivateAnggota($anggota);

        return redirect()->route('anggota.index')
            ->with('success', 'Anggota dan jabatan Pengurus aktif terkait berhasil dinonaktifkan.');
    }

    public function activate(Anggota $anggota, MasterDataKoperasiService $service)
    {
        $service->activateAnggota($anggota);

        return redirect()->route('anggota.index')
            ->with('success', 'Anggota berhasil diaktifkan kembali.');
    }

    public function destroy(Anggota $anggota, MasterDataKoperasiService $service)
    {
        $service->deleteUnusedAnggota($anggota);

        return redirect()->route('anggota.index')
            ->with('success', 'Data Anggota yang belum digunakan berhasil dihapus.');
    }

    private function renderIndex(?Anggota $data = null)
    {
        $anggota = Anggota::query()
            ->with(['karyawan', 'pengurusAktif'])
            ->latest('id')
            ->paginate(10);

        $karyawanTersedia = Karyawan::query()
            ->aktif()
            ->whereDoesntHave('anggota')
            ->orderBy('nama')
            ->get();

        $dompetKas = DompetKoperasi::query()
            ->with('akun')
            ->kas()
            ->orderBy('nama_dompet')
            ->get();

        $dompetBank = DompetKoperasi::query()
            ->with('akun')
            ->bank()
            ->orderBy('nama_dompet')
            ->get();

        return view('pages.anggota.index', compact('anggota', 'karyawanTersedia', 'data', 'dompetKas', 'dompetBank'));
    }
}
