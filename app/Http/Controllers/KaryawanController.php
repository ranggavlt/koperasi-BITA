<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use Illuminate\Http\Request;

class KaryawanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $karyawan = Karyawan::orderBy('id', 'desc')->paginate(10);
        
        return view('pages.karyawan.index', compact('karyawan'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama'    => 'required|string|max:255',
            'email'   => 'required|email|max:255|unique:karyawan,email',
            'telepon' => 'nullable|string|max:50',
            'jabatan' => 'required|string|max:255',
            'is_anggota' => 'nullable|boolean',
        ]);

        Karyawan::create([
            'nama' => $request->nama,
            'email' => $request->email,
            'telepon' => $request->telepon,
            'jabatan' => $request->jabatan,
            'is_anggota' => (bool) $request->boolean('is_anggota'),
        ]);

        return redirect()->route('karyawan.index')
            ->with('success', 'Data karyawan berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Karyawan $karyawan)
    {
        $data = $karyawan;
        
        // Load ulang tabel agar form edit muncul berdampingan dengan tabel yang tidak error
        $karyawanList = Karyawan::orderBy('id', 'desc')->paginate(10);

        return view('pages.karyawan.index', [
            'data'     => $data,
            'karyawan' => $karyawanList
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Karyawan $karyawan)
    {
        $request->validate([
            'nama'    => 'required|string|max:255',
            // Validasi email agar mengabaikan email milik karyawan yang sedang diedit
            'email'   => 'required|email|max:255|unique:karyawan,email,' . $karyawan->id,
            'telepon' => 'nullable|string|max:50',
            'jabatan' => 'required|string|max:255',
            'is_anggota' => 'nullable|boolean',
        ]);

        $karyawan->update([
            'nama' => $request->nama,
            'email' => $request->email,
            'telepon' => $request->telepon,
            'jabatan' => $request->jabatan,
            'is_anggota' => (bool) $request->boolean('is_anggota'),
        ]);

        return redirect()->route('karyawan.index')
            ->with('success', 'Data karyawan berhasil diupdate.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Karyawan $karyawan)
    {
        $karyawan->delete();

        return redirect()->route('karyawan.index')
            ->with('success', 'Data karyawan berhasil dihapus.');
    }
}
