<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JenisPinjaman;

class JenisPinjamanController extends Controller
{

    public function index()
    {
        $data = JenisPinjaman::latest()->get();

        return view('pages.jenis-pinjaman.index', compact('data'));
    }



    public function create()
    {
        return view('pages.jenis-pinjaman.create');
    }



    public function store(Request $request)
    {
        $request->validate([
            'nama_pinjaman' => 'required',
            'bunga_persen' => 'nullable|numeric',
            'tenor_bulan' => 'nullable|numeric'
        ]);

        JenisPinjaman::create($request->all());

        return redirect()->route('jenis-pinjaman.index')
            ->with('success','Jenis pinjaman berhasil ditambahkan');
    }



    public function edit($id)
    {
        $data = JenisPinjaman::findOrFail($id);

        return view('pages.jenis-pinjaman.edit', compact('data'));
    }



    public function update(Request $request, $id)
    {

        $data = JenisPinjaman::findOrFail($id);

        $request->validate([
            'nama_pinjaman' => 'required',
            'bunga_persen' => 'nullable|numeric',
            'tenor_bulan' => 'nullable|numeric'
        ]);

        $data->update($request->all());

        return redirect()->route('jenis-pinjaman.index')
            ->with('success','Data berhasil diupdate');
    }



    public function destroy($id)
    {
        $data = JenisPinjaman::findOrFail($id);

        $data->delete();

        return redirect()->route('jenis-pinjaman.index')
            ->with('success','Data berhasil dihapus');
    }

}