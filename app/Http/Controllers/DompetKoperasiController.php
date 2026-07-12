<?php

namespace App\Http\Controllers;

use App\Models\Akun;
use App\Models\DompetKoperasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DompetKoperasiController extends Controller
{
    public function index()
    {
        $dompetKoperasi = DompetKoperasi::with('akun')->latest()->paginate(10);
        $akunAset = $this->akunAset();

        return view('pages.dompet-koperasi.index', compact('dompetKoperasi', 'akunAset'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_dompet' => 'required|string|max:100',
            'jenis_dompet' => ['required', Rule::in([DompetKoperasi::JENIS_KAS, DompetKoperasi::JENIS_BANK])],
            'is_default_penerimaan_payroll' => ['nullable', 'boolean'],
            'akun_id' => [
                'required',
                Rule::exists('akun', 'id')->where(fn ($query) => $query
                    ->where('is_aktif', true)
                    ->where('kategori', 'aset')
                    ->where('posisi_saldo', 'debit')),
            ],
        ], [
            'nama_dompet.required' => 'Nama dompet koperasi wajib diisi.',
            'jenis_dompet.required' => 'Jenis dompet wajib dipilih.',
            'akun_id.required' => 'Akun COA Dompet wajib dipilih.',
        ]);

        $isDefaultPayroll = (bool) ($validated['is_default_penerimaan_payroll'] ?? false);

        if ($isDefaultPayroll && $validated['jenis_dompet'] !== DompetKoperasi::JENIS_BANK) {
            return back()->withErrors([
                'is_default_penerimaan_payroll' => 'Default penerimaan payroll hanya boleh Dompet Bank.',
            ])->withInput();
        }

        DB::transaction(function () use ($validated, $isDefaultPayroll): void {
            if ($isDefaultPayroll) {
                DompetKoperasi::query()
                    ->where('is_default_penerimaan_payroll', true)
                    ->get()
                    ->each(fn (DompetKoperasi $dompet) => $dompet->update(['is_default_penerimaan_payroll' => false]));
            }

            DompetKoperasi::create([
                'akun_id' => $validated['akun_id'],
                'nama_dompet' => $validated['nama_dompet'],
                'jenis_dompet' => $validated['jenis_dompet'],
                'is_default_penerimaan_payroll' => $isDefaultPayroll,
                'saldo' => 0,
            ]);
        });

        return redirect()->route('dompet-koperasi.index')
            ->with('success', 'Dompet koperasi berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $data = DompetKoperasi::findOrFail($id);
        $dompetKoperasi = DompetKoperasi::with('akun')->latest()->paginate(10);
        $akunAset = $this->akunAset();

        return view('pages.dompet-koperasi.index', compact('data', 'dompetKoperasi', 'akunAset'));
    }

    public function update(Request $request, $id)
    {
        $data = DompetKoperasi::findOrFail($id);

        $validated = $request->validate([
            'nama_dompet' => 'required|string|max:100',
            'jenis_dompet' => ['required', Rule::in([DompetKoperasi::JENIS_KAS, DompetKoperasi::JENIS_BANK])],
            'is_default_penerimaan_payroll' => ['nullable', 'boolean'],
            'akun_id' => [
                'required',
                Rule::exists('akun', 'id')->where(fn ($query) => $query
                    ->where('is_aktif', true)
                    ->where('kategori', 'aset')
                    ->where('posisi_saldo', 'debit')),
            ],
        ], [
            'nama_dompet.required' => 'Nama dompet koperasi wajib diisi.',
            'jenis_dompet.required' => 'Jenis dompet wajib dipilih.',
            'akun_id.required' => 'Akun COA Dompet wajib dipilih.',
        ]);

        $isDefaultPayroll = (bool) ($validated['is_default_penerimaan_payroll'] ?? false);

        if ($isDefaultPayroll && $validated['jenis_dompet'] !== DompetKoperasi::JENIS_BANK) {
            return back()->withErrors([
                'is_default_penerimaan_payroll' => 'Default penerimaan payroll hanya boleh Dompet Bank.',
            ])->withInput();
        }

        DB::transaction(function () use ($data, $validated, $isDefaultPayroll): void {
            if ($isDefaultPayroll) {
                DompetKoperasi::query()
                    ->whereKeyNot($data->id)
                    ->where('is_default_penerimaan_payroll', true)
                    ->get()
                    ->each(fn (DompetKoperasi $dompet) => $dompet->update(['is_default_penerimaan_payroll' => false]));
            }

            $data->update([
                'akun_id' => $validated['akun_id'],
                'nama_dompet' => $validated['nama_dompet'],
                'jenis_dompet' => $validated['jenis_dompet'],
                'is_default_penerimaan_payroll' => $isDefaultPayroll,
            ]);
        });

        return redirect()->route('dompet-koperasi.index')
            ->with('success', 'Dompet koperasi berhasil diupdate.');
    }

    public function destroy($id)
    {
        $data = DompetKoperasi::findOrFail($id);

        $data->delete();

        return redirect()->route('dompet-koperasi.index')
            ->with('success', 'Dompet koperasi berhasil dihapus.');
    }

    private function akunAset()
    {
        return Akun::query()
            ->aktif()
            ->where('kategori', 'aset')
            ->where('posisi_saldo', 'debit')
            ->orderBy('kode_akun')
            ->get();
    }
}
