<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pinjaman;
use App\Models\Karyawan;

class PinjamanController extends Controller
{

    public function index()
    {
        $data = Pinjaman::with('karyawan')
        ->latest()
        ->get();

        return view('pages.pinjaman.index', compact('data'));
    }



    public function create()
    {
        $karyawan = Karyawan::all();

        return view('pages.pinjaman.create', compact('karyawan'));
    }



    public function store(Request $request)
    {

        $request->validate([
            'karyawan_id' => 'required',
            'jumlah_pinjaman' => 'required|numeric',
            'tenor_bulan' => 'required|numeric',
            'tanggal_pinjaman' => 'required'
        ]);

        Pinjaman::create([
            'karyawan_id' => $request->karyawan_id,
            'jumlah_pinjaman' => $request->jumlah_pinjaman,
            'bunga_persen' => $request->bunga_persen ?? 0,
            'tenor_bulan' => $request->tenor_bulan,
            'sisa_pinjaman' => $request->jumlah_pinjaman,
            'status' => 'aktif',
            'tanggal_pinjaman' => $request->tanggal_pinjaman,
            'keterangan' => $request->keterangan
        ]);

        return redirect()
        ->route('pinjaman.index')
        ->with('success','Pinjaman berhasil dibuat');

    }



    public function destroy($id)
    {
        $data = Pinjaman::findOrFail($id);

        $data->delete();

        return redirect()
        ->route('pinjaman.index')
        ->with('success','Data berhasil dihapus');
    }

}