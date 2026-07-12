<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKaryawanRequest;
use App\Http\Requests\UpdateKaryawanRequest;
use App\Models\Karyawan;
use App\Services\MasterDataKoperasiService;

class KaryawanController extends Controller
{
    public function index()
    {
        $karyawan = Karyawan::query()
            ->with('anggota')
            ->latest('id')
            ->paginate(10);

        return view('pages.karyawan.index', compact('karyawan'));
    }

    public function store(StoreKaryawanRequest $request, MasterDataKoperasiService $service)
    {
        $service->createKaryawan($request->validated());

        return redirect()->route('karyawan.index')
            ->with('success', 'Data Karyawan berhasil ditambahkan.');
    }

    public function edit(Karyawan $karyawan)
    {
        $data = $karyawan->load('anggota');
        $karyawan = Karyawan::query()
            ->with('anggota')
            ->latest('id')
            ->paginate(10);

        return view('pages.karyawan.index', compact('data', 'karyawan'));
    }

    public function update(
        UpdateKaryawanRequest $request,
        Karyawan $karyawan,
        MasterDataKoperasiService $service
    ) {
        $service->updateKaryawan($karyawan, $request->validated());

        return redirect()->route('karyawan.index')
            ->with('success', 'Data dan status kerja Karyawan berhasil diperbarui.');
    }

    public function destroy(Karyawan $karyawan, MasterDataKoperasiService $service)
    {
        $service->deleteUnusedKaryawan($karyawan);

        return redirect()->route('karyawan.index')
            ->with('success', 'Karyawan yang belum digunakan berhasil dihapus.');
    }
}
