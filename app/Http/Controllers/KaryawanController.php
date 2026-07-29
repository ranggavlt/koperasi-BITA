<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKaryawanRequest;
use App\Http\Requests\StoreKaryawanAccountRequest;

use App\Http\Requests\UpdateKaryawanRequest;
use App\Models\Karyawan;

use App\Services\KaryawanAccountService;
use App\Services\MasterDataKoperasiService;

class KaryawanController extends Controller
{
    public function index()
    {
        $karyawan = Karyawan::query()
            ->with(['anggota', 'user', 'perusahaan'])
            ->latest('id')
            ->paginate(10);
            
        $perusahaan = \App\Models\Perusahaan::all();

        return view('pages.karyawan.index', compact('karyawan', 'perusahaan'));
    }

    public function store(StoreKaryawanRequest $request, MasterDataKoperasiService $service)
    {
        $service->createKaryawan($request->validated());

        return redirect()->route('karyawan.index')
            ->with('success', 'Data Karyawan berhasil ditambahkan.');
    }

    public function edit(Karyawan $karyawan)
    {
        $data = $karyawan->load(['anggota', 'user']);
        $karyawanList = Karyawan::query()
            ->with(['anggota', 'user', 'perusahaan'])
            ->latest('id')
            ->paginate(10);
            
        $perusahaan = \App\Models\Perusahaan::all();

        return view('pages.karyawan.index', [
            'data' => $data,
            'karyawan' => $karyawanList,
            'perusahaan' => $perusahaan
        ]);
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

    public function createAccount(
        StoreKaryawanAccountRequest $request,
        Karyawan $karyawan,
        KaryawanAccountService $service
    ) {
        $service->createAccount(
            $karyawan,
            $request->validated('temporary_password'),
            $request->validated('role'),
            $request->user()->id
        );

        return redirect()->route('karyawan.index')
            ->with('success', 'Akun Karyawan berhasil dibuat. Pengguna wajib mengganti password saat login pertama.');
    }

    public function resetAccountPassword(
        StoreKaryawanAccountRequest $request,
        Karyawan $karyawan,
        KaryawanAccountService $service
    ) {
        $service->resetPassword($karyawan, $request->validated('temporary_password'), $request->user()->id);

        return redirect()->route('karyawan.index')
            ->with('success', 'Password sementara akun Karyawan berhasil direset.');
    }

    public function activateAccount(Karyawan $karyawan, KaryawanAccountService $service)
    {
        $service->activateAccount($karyawan, auth()->id());

        return redirect()->route('karyawan.index')
            ->with('success', 'Akun Karyawan berhasil diaktifkan.');
    }

    public function deactivateAccount(Karyawan $karyawan, KaryawanAccountService $service)
    {
        $service->deactivateAccount($karyawan, auth()->id());

        return redirect()->route('karyawan.index')
            ->with('success', 'Akun Karyawan berhasil dinonaktifkan.');
    }

}
