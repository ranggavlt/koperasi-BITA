<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePengurusKoperasiRequest;
use App\Http\Requests\UpdatePengurusKoperasiRequest;
use App\Models\Anggota;
use App\Models\PengurusKoperasi;
use App\Services\MasterDataKoperasiService;

class PengurusKoperasiController extends Controller
{
    public function index()
    {
        return $this->renderIndex();
    }

    public function store(StorePengurusKoperasiRequest $request, MasterDataKoperasiService $service)
    {
        $service->createPengurus($request->validated());

        return redirect()->route('pengurus-koperasi.index')
            ->with('success', 'Jabatan Pengurus aktif berhasil ditambahkan.');
    }

    public function edit(PengurusKoperasi $pengurusKoperasi)
    {
        return $this->renderIndex($pengurusKoperasi->load('anggota.karyawan'));
    }

    public function update(
        UpdatePengurusKoperasiRequest $request,
        PengurusKoperasi $pengurusKoperasi,
        MasterDataKoperasiService $service
    ) {
        $service->updatePengurus($pengurusKoperasi, $request->validated());

        return redirect()->route('pengurus-koperasi.index')
            ->with('success', 'Data Pengurus berhasil diperbarui.');
    }

    public function deactivate(PengurusKoperasi $pengurusKoperasi, MasterDataKoperasiService $service)
    {
        $service->deactivatePengurus($pengurusKoperasi);

        return redirect()->route('pengurus-koperasi.index')
            ->with('success', 'Jabatan Pengurus berhasil dinonaktifkan dan tetap disimpan sebagai histori.');
    }

    public function activate(PengurusKoperasi $pengurusKoperasi, MasterDataKoperasiService $service)
    {
        $service->activatePengurus($pengurusKoperasi);

        return redirect()->route('pengurus-koperasi.index')
            ->with('success', 'Jabatan Pengurus berhasil diaktifkan kembali.');
    }

    private function renderIndex(?PengurusKoperasi $data = null)
    {
        $pengurusKoperasi = PengurusKoperasi::query()
            ->with('anggota.karyawan')
            ->latest('id')
            ->paginate(10);

        $anggotaAktif = Anggota::query()
            ->aktif()
            ->whereHas('karyawan', fn ($query) => $query->aktif())
            ->whereDoesntHave('pengurusAktif', function ($query) use ($data) {
                if ($data) {
                    $query->where('id', '!=', $data->id);
                }
            })
            ->with('karyawan')
            ->orderBy('nomor_anggota')
            ->get();

        $jabatan = PengurusKoperasi::JABATAN;

        return view('pages.pengurus-koperasi.index', compact(
            'pengurusKoperasi',
            'anggotaAktif',
            'jabatan',
            'data'
        ));
    }
}
