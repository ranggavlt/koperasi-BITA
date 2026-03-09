<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Karyawan;

class KaryawanController extends Controller
{
    public function index()
    {
        $data = Karyawan::latest()->get();

        return view('pages.karyawan.index', compact('data'));
    }

    public function create()
    {
        return view('pages.karyawan.create');
    }

    public function store(Request $request)
    {
        Karyawan::create($request->all());

        return redirect()->route('karyawan.index')
            ->with('success','Data anggota berhasil ditambahkan');
    }

    public function edit($id)
    {
        $data = Karyawan::findOrFail($id);

        return view('pages.karyawan.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $data = Karyawan::findOrFail($id);

        $data->update($request->all());

        return redirect()->route('karyawan.index')
            ->with('success','Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $data = Karyawan::findOrFail($id);

        $data->delete();

        return redirect()->route('karyawan.index')
            ->with('success','Data berhasil dihapus');
    }
}