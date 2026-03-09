<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Simpanan;
use App\Models\Karyawan;
use App\Models\JenisSimpanan;

class SimpananController extends Controller
{

    public function index()
    {
        $data = Simpanan::with(['karyawan','jenisSimpanan'])
        ->latest()
        ->get();

        return view('pages.simpanan.index', compact('data'));
    }



    public function create()
    {
        $karyawan = Karyawan::all();
        $jenis = JenisSimpanan::all();

        return view('pages.simpanan.create', compact('karyawan','jenis'));
    }



    public function store(Request $request)
    {

        $request->validate([
            'karyawan_id' => 'required',
            'jenis_simpanan_id' => 'required',
            'jumlah' => 'required|numeric',
            'tanggal' => 'required'
        ]);

        Simpanan::create($request->all());

        return redirect()
        ->route('simpanan.index')
        ->with('success','Transaksi simpanan berhasil disimpan');

    }



    public function destroy($id)
    {
        $data = Simpanan::findOrFail($id);

        $data->delete();

        return redirect()
        ->route('simpanan.index')
        ->with('success','Data berhasil dihapus');
    }

}