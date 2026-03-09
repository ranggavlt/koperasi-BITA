<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DompetKoperasi;

class DompetKoperasiController extends Controller
{

    public function index()
    {
        $data = DompetKoperasi::latest()->get();

        return view('pages.dompet-koperasi.index', compact('data'));
    }



    public function create()
    {
        return view('pages.dompet-koperasi.create');
    }



    public function store(Request $request)
    {
        $request->validate([
            'nama_dompet' => 'required'
        ]);

        DompetKoperasi::create([
            'nama_dompet' => $request->nama_dompet,
            'saldo' => 0
        ]);

        return redirect()->route('dompet-koperasi.index')
        ->with('success','Dompet koperasi berhasil ditambahkan');
    }



    public function edit($id)
    {
        $data = DompetKoperasi::findOrFail($id);

        return view('pages.dompet-koperasi.edit', compact('data'));
    }



    public function update(Request $request, $id)
    {

        $data = DompetKoperasi::findOrFail($id);

        $request->validate([
            'nama_dompet' => 'required'
        ]);

        $data->update([
            'nama_dompet' => $request->nama_dompet
        ]);

        return redirect()->route('dompet-koperasi.index')
        ->with('success','Data berhasil diupdate');
    }



    public function destroy($id)
    {

        $data = DompetKoperasi::findOrFail($id);

        $data->delete();

        return redirect()->route('dompet-koperasi.index')
        ->with('success','Data berhasil dihapus');
    }

}