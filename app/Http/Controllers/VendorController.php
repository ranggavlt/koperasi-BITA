<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $vendors = \App\Models\Vendor::orderBy('nama')->paginate(10);
        return view('pages.vendor.index', compact('vendors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:100',
            'kontak' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:255',
        ]);

        \App\Models\Vendor::create($request->all());

        return back()->with('success', 'Data Vendor berhasil ditambahkan.');
    }

    public function edit(\App\Models\Vendor $vendor)
    {
        $vendors = \App\Models\Vendor::orderBy('nama')->paginate(10);
        return view('pages.vendor.index', ['vendors' => $vendors, 'data' => $vendor]);
    }

    public function update(Request $request, \App\Models\Vendor $vendor)
    {
        $request->validate([
            'nama' => 'required|string|max:100',
            'kontak' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:255',
        ]);

        $vendor->update($request->all());

        return redirect()->route('vendor.index')->with('success', 'Data Vendor berhasil diperbarui.');
    }

    public function destroy(\App\Models\Vendor $vendor)
    {
        $vendor->delete();
        return back()->with('success', 'Data Vendor berhasil dihapus.');
    }
}
