<?php

namespace App\Http\Controllers;

use App\Models\JenisSimpanan;
use Illuminate\Http\Request;

class JenisSimpananController extends Controller
{
    public function index()
    {
        $data = JenisSimpanan::all();
        return view('pages.jenis-simpanan.index', compact('data'));
    }

    public function store(Request $request)
    {
        JenisSimpanan::create($request->all());
        return redirect()->route('jenis-simpanan.index');
    }

    public function edit($id)
    {
        $data = JenisSimpanan::findOrFail($id);
        return view('pages.jenis-simpanan.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $data = JenisSimpanan::findOrFail($id);
        $data->update($request->all());

        return redirect()->route('jenis-simpanan.index');
    }

    public function destroy($id)
    {
        $data = JenisSimpanan::findOrFail($id);
        $data->delete();

        return redirect()->route('jenis-simpanan.index');
    }
}