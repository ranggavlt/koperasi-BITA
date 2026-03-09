<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MutasiKas;
use App\Models\DompetKoperasi;

class MutasiKasController extends Controller
{

    public function index()
    {
        $data = MutasiKas::with('dompet')
        ->latest()
        ->get();

        return view('pages.mutasi-kas.index', compact('data'));
    }



    public function create()
    {
        $dompet = DompetKoperasi::all();

        return view('pages.mutasi-kas.create', compact('dompet'));
    }



    public function store(Request $request)
    {

        $request->validate([
            'dompet_id' => 'required',
            'tipe' => 'required',
            'jumlah' => 'required|numeric',
            'tanggal' => 'required'
        ]);

        MutasiKas::create($request->all());

        return redirect()
        ->route('mutasi-kas.index')
        ->with('success','Mutasi kas berhasil disimpan');

    }



    public function destroy($id)
    {
        $data = MutasiKas::findOrFail($id);

        $data->delete();

        return redirect()
        ->route('mutasi-kas.index')
        ->with('success','Data berhasil dihapus');
    }

}