<?php

namespace App\Http\Controllers;

use App\Models\KategoriProduk;
use Illuminate\Http\Request;

class KategoriProdukController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Menampilkan data dengan pagination (10 data per halaman)
        $kategori = KategoriProduk::orderBy('id', 'desc')->paginate(10);
        
        return view('pages.kategori_produk.index', compact('kategori'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama_kategori' => 'required|string|max:255',
            'deskripsi'     => 'nullable|string|max:500',
        ]);

        KategoriProduk::create($request->only('nama_kategori', 'deskripsi'));

        return redirect()->route('kategori-produk.index')
            ->with('success', 'Kategori produk berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(KategoriProduk $kategoriProduk)
    {
        // Masukkan data yang mau diedit ke variabel $data
        $data = $kategoriProduk;
        
        // Tetap load data tabel dengan pagination agar view tidak error
        $kategori = KategoriProduk::orderBy('id', 'desc')->paginate(10);

        return view('pages.kategori_produk.index', compact('data', 'kategori'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, KategoriProduk $kategoriProduk)
    {
        $request->validate([
            'nama_kategori' => 'required|string|max:255',
            'deskripsi'     => 'nullable|string|max:500',
        ]);

        $kategoriProduk->update($request->only('nama_kategori', 'deskripsi'));

        return redirect()->route('kategori-produk.index')
            ->with('success', 'Kategori produk berhasil diupdate.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KategoriProduk $kategoriProduk)
    {
        $kategoriProduk->delete();

        return redirect()->route('kategori-produk.index')
            ->with('success', 'Kategori produk berhasil dihapus.');
    }
}