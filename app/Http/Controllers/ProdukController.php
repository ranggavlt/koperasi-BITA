<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\KategoriProduk;
use App\Models\Reseller;
use App\Http\Requests\StoreProdukRequest;
use App\Http\Requests\UpdateProdukRequest;

class ProdukController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Ubah get() jadi paginate(10)
        $produk = Produk::with(['kategori', 'reseller'])
            ->latest()
            ->paginate(10);

        $kategori = KategoriProduk::orderBy('nama_kategori')->get();
        $reseller = Reseller::orderBy('nama_reseller')->get();

        return view('pages.produk.index', compact('produk', 'kategori', 'reseller'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProdukRequest $request)
    {
        $request->validate([
            'nama_produk' => 'required|string|max:255',
            'kategori_id' => 'required|exists:kategori_produk,id',
            'harga_beli'  => 'nullable|numeric|min:0',
            'harga_jual'  => 'required|numeric|min:0',
            'stok'        => 'required|integer|min:0',

            'konsinyasi'  => 'required|in:0,1',
            'reseller_id' => 'nullable|exists:reseller,id',
            'harga_setor' => 'nullable|numeric|min:0',
        ], [
            'nama_produk.required' => 'Nama produk wajib diisi.',
            'kategori_id.required' => 'Kategori wajib dipilih.',
            'kategori_id.exists'   => 'Kategori tidak valid.',
            'harga_jual.required'  => 'Harga jual wajib diisi.',
            'stok.required'        => 'Stok wajib diisi.',
            'konsinyasi.required'  => 'Status konsinyasi wajib dipilih.',
        ]);

        $isKonsinyasi = (int)$request->konsinyasi === 1;

        if ($isKonsinyasi) {
            $request->validate([
                'reseller_id' => 'required|exists:reseller,id',
                'harga_setor' => 'required|numeric|min:0',
            ], [
                'reseller_id.required' => 'Reseller wajib dipilih untuk produk konsinyasi.',
                'harga_setor.required' => 'Harga setor wajib diisi untuk produk konsinyasi.',
            ]);
        }

        Produk::create([
            'nama_produk' => $request->nama_produk,
            'kategori_id' => $request->kategori_id,
            'harga_beli'  => $request->harga_beli ?? 0,
            'harga_jual'  => $request->harga_jual,
            'stok'        => $request->stok,

            'konsinyasi'  => $isKonsinyasi,
            'reseller_id' => $isKonsinyasi ? $request->reseller_id : null,
            'harga_setor' => $isKonsinyasi ? ($request->harga_setor ?? 0) : 0,
        ]);

        return redirect()->route('produk.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Produk $produk)
    {
        $data = $produk->load(['kategori', 'reseller']);

        // Ubah get() jadi paginate(10) agar view tidak error
        $produk = Produk::with(['kategori', 'reseller'])
            ->latest()
            ->paginate(10);

        $kategori = KategoriProduk::orderBy('nama_kategori')->get();
        $reseller = Reseller::orderBy('nama_reseller')->get();

        return view('pages.produk.index', compact('data', 'produk', 'kategori', 'reseller'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProdukRequest $request, Produk $produk)
    {
        $request->validate([
            'nama_produk' => 'required|string|max:255',
            'kategori_id' => 'required|exists:kategori_produk,id',
            'harga_beli'  => 'nullable|numeric|min:0',
            'harga_jual'  => 'required|numeric|min:0',
            'stok'        => 'required|integer|min:0',

            'konsinyasi'  => 'required|in:0,1',
            'reseller_id' => 'nullable|exists:reseller,id',
            'harga_setor' => 'nullable|numeric|min:0',
        ]);

        $isKonsinyasi = (int)$request->konsinyasi === 1;

        if ($isKonsinyasi) {
            $request->validate([
                'reseller_id' => 'required|exists:reseller,id',
                'harga_setor' => 'required|numeric|min:0',
            ]);
        }

        $produk->update([
            'nama_produk' => $request->nama_produk,
            'kategori_id' => $request->kategori_id,
            'harga_beli'  => $request->harga_beli ?? 0,
            'harga_jual'  => $request->harga_jual,
            'stok'        => $request->stok,

            'konsinyasi'  => $isKonsinyasi,
            'reseller_id' => $isKonsinyasi ? $request->reseller_id : null,
            'harga_setor' => $isKonsinyasi ? ($request->harga_setor ?? 0) : 0,
        ]);

        return redirect()->route('produk.index')
            ->with('success', 'Produk berhasil diupdate.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Produk $produk)
    {
        $produk->delete();

        return redirect()->route('produk.index')
            ->with('success', 'Produk berhasil dihapus.');
    }
}