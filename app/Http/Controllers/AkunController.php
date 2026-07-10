<?php

namespace App\Http\Controllers;

use App\Models\Akun;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AkunController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $kategori = (string) $request->get('kategori', '');

        $akun = Akun::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('kode_akun', 'like', "%{$search}%")
                        ->orWhere('nama_akun', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists($kategori, Akun::KATEGORI), fn ($query) => $query->where('kategori', $kategori))
            ->orderBy('kode_akun')
            ->paginate(20)
            ->withQueryString();

        $ringkasan = Akun::query()
            ->selectRaw('kategori, COUNT(*) as total')
            ->groupBy('kategori')
            ->pluck('total', 'kategori');

        return view('pages.akun.index', [
            'akun' => $akun,
            'categories' => Akun::KATEGORI,
            'kategori' => $kategori,
            'search' => $search,
            'ringkasan' => $ringkasan,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_akun' => [
                'required',
                'string',
                'max:20',
                'regex:/^\d{3,10}$/',
                Rule::unique('akun', 'kode_akun'),
            ],
            'nama_akun' => ['required', 'string', 'max:150'],
            'kategori' => ['required', Rule::in(array_keys(Akun::KATEGORI))],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ], [
            'kode_akun.regex' => 'Kode akun harus berupa 3 sampai 10 digit angka.',
            'kode_akun.unique' => 'Kode akun tersebut sudah digunakan.',
            'nama_akun.required' => 'Nama akun wajib diisi.',
            'kategori.required' => 'Kategori akun wajib dipilih.',
        ]);

        Akun::query()->create([
            ...$validated,
            'posisi_saldo' => Akun::posisiSaldoUntuk($validated['kategori']),
            'is_aktif' => true,
            'is_sistem' => false,
        ]);

        return redirect()
            ->route('akun.index')
            ->with('success', 'Akun berhasil ditambahkan ke Chart of Accounts.');
    }
}
