<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\StrukturKoperasi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StrukturKoperasiController extends Controller
{
    public function index()
    {
        return view('pages.struktur-koperasi.index', [
            'structures' => StrukturKoperasi::query()->with(['anggota.karyawan', 'creator'])->latest('tanggal_mulai')->paginate(20),
            'members' => Anggota::query()->with('karyawan')->orderBy('nomor_anggota')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'anggota_id' => ['nullable', 'exists:anggota,id', 'required_without:nama_penerima'],
            'nama_penerima' => ['nullable', 'string', 'max:150', 'required_without:anggota_id'],
            'kelompok' => ['required', Rule::in(StrukturKoperasi::KELOMPOK)],
            'jabatan' => ['required', 'string', 'max:120'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'dasar_keputusan' => ['required', 'string', 'max:255'],
        ]);
        StrukturKoperasi::query()->create([
            ...$data,
            'nama_penerima' => trim((string) ($data['nama_penerima'] ?? '')) ?: null,
            'status' => empty($data['tanggal_selesai']) ? StrukturKoperasi::STATUS_AKTIF : StrukturKoperasi::STATUS_NONAKTIF,
            'created_by' => $request->user()->id,
        ]);
        return back()->with('success', 'Versi Struktur Koperasi berhasil ditambahkan. Histori lama tetap dipertahankan.');
    }
}
