<?php

namespace App\Http\Controllers;

use App\Models\Reseller;
use Illuminate\Http\Request;

class ResellerController extends Controller
{
    public function index()
    {
        // Cukup pakai paginate, yang ->get() dihapus saja biar rapi dan enteng
        $reseller = Reseller::orderBy('id', 'desc')->paginate(15);
        return view('pages.reseller.index', compact('reseller'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_reseller' => 'required|string|max:255',
            'telepon' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:255',
        ]);

        Reseller::create($request->only('nama_reseller', 'telepon', 'alamat'));

        return redirect()->route('reseller.index')->with('success', 'Reseller berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $data = Reseller::findOrFail($id);
        
        // UBAH DISINI JUGA: Harus pakai paginate juga agar tidak error saat membuka halaman edit
        $reseller = Reseller::orderBy('id', 'desc')->paginate(10); 
        
        return view('pages.reseller.index', compact('data', 'reseller'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_reseller' => 'required|string|max:255',
            'telepon' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:255',
        ]);

        $res = Reseller::findOrFail($id);
        $res->update($request->only('nama_reseller', 'telepon', 'alamat'));

        return redirect()->route('reseller.index')->with('success', 'Reseller berhasil diupdate.');
    }

    public function destroy($id)
    {
        Reseller::findOrFail($id)->delete();
        return redirect()->route('reseller.index')->with('success', 'Reseller berhasil dihapus.');
    }
}