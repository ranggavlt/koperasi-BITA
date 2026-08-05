<?php

namespace App\Http\Controllers;

use App\Models\Akun;
use App\Models\RiwayatAkunBebanOperasional;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AkunController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $kategori = (string) $request->get('kategori', '');
        $status = (string) $request->get('status', '');

        $akun = Akun::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('kode_akun', 'like', "%{$search}%")
                        ->orWhere('nama_akun', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists($kategori, Akun::KATEGORI), fn ($query) => $query->where('kategori', $kategori))
            ->when(in_array($status, ['aktif', 'nonaktif'], true), fn ($query) => $query->where('is_aktif', $status === 'aktif'))
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
            'status' => $status,
            'ringkasan' => $ringkasan,
        ]);
    }

    public function create()
    {
        return view('pages.akun.form', [
            'akun' => new Akun(['is_aktif' => true]),
            'categories' => Akun::KATEGORI,
            'isEdit' => false,
            'isCoreLocked' => false,
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
            'is_beban_operasional' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ], [
            'kode_akun.regex' => 'Kode akun harus berupa 3 sampai 10 digit angka.',
            'kode_akun.unique' => 'Kode akun tersebut sudah digunakan.',
            'nama_akun.required' => 'Nama akun wajib diisi.',
            'kategori.required' => 'Kategori akun wajib dipilih.',
        ]);

        $isBebanOperasional = (bool) ($validated['is_beban_operasional'] ?? false);
        if ($isBebanOperasional && $validated['kategori'] !== 'beban') {
            throw ValidationException::withMessages([
                'is_beban_operasional' => 'Eligibility Beban Operasional hanya boleh untuk akun kategori Beban.',
            ]);
        }

        $akun = Akun::query()->create([
            ...$validated,
            'posisi_saldo' => Akun::posisiSaldoUntuk($validated['kategori']),
            'is_aktif' => true,
            'is_sistem' => false,
            'is_beban_operasional' => $isBebanOperasional,
            'beban_operasional_updated_by' => $isBebanOperasional ? $request->user()?->id : null,
        ]);

        if ($isBebanOperasional) {
            RiwayatAkunBebanOperasional::query()->create([
                'akun_id' => $akun->id,
                'nilai_sebelum' => false,
                'nilai_sesudah' => true,
                'alasan' => 'Eligibility ditetapkan saat akun dibuat.',
                'changed_by' => $request->user()?->id,
                'changed_at' => now(config('app.timezone', 'Asia/Jakarta')),
            ]);
        }

        return redirect()
            ->route('akun.index')
            ->with('success', 'Akun berhasil ditambahkan ke Chart of Accounts.');
    }

    public function edit(Akun $akun)
    {
        return view('pages.akun.form', [
            'akun' => $akun,
            'categories' => Akun::KATEGORI,
            'isEdit' => true,
            'isCoreLocked' => $akun->is_sistem || $akun->jurnalDetails()->exists(),
        ]);
    }

    public function update(Request $request, Akun $akun)
    {
        $coreLocked = $akun->is_sistem || $akun->jurnalDetails()->exists();
        $validated = $request->validate([
            'kode_akun' => [Rule::requiredIf(! $coreLocked), 'nullable', 'string', 'max:20', 'regex:/^\d{3,10}$/', Rule::unique('akun', 'kode_akun')->ignore($akun->id)],
            'nama_akun' => ['required', 'string', 'max:150'],
            'kategori' => [Rule::requiredIf(! $coreLocked), 'nullable', Rule::in(array_keys(Akun::KATEGORI))],
            'is_beban_operasional' => ['nullable', 'boolean'],
            'is_aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $category = $coreLocked ? $akun->kategori : $validated['kategori'];
        $operational = (bool) ($validated['is_beban_operasional'] ?? false);
        if ($operational && $category !== 'beban') {
            throw ValidationException::withMessages([
                'is_beban_operasional' => 'Hanya akun kategori Beban yang dapat digunakan untuk Beban Operasional.',
            ]);
        }
        if ($akun->is_sistem && ! ($validated['is_aktif'] ?? true)) {
            throw ValidationException::withMessages(['is_aktif' => 'Akun sistem tidak dapat dinonaktifkan.']);
        }

        $akun->update([
            'kode_akun' => $coreLocked ? $akun->kode_akun : $validated['kode_akun'],
            'nama_akun' => $validated['nama_akun'],
            'kategori' => $category,
            'posisi_saldo' => Akun::posisiSaldoUntuk($category),
            'is_beban_operasional' => $operational,
            'is_aktif' => $validated['is_aktif'] ?? false,
            'keterangan' => $validated['keterangan'] ?? null,
        ]);

        return redirect()->route('akun.index')->with('success', 'Akun berhasil diperbarui.');
    }

    public function updateBebanOperasionalEligibility(Request $request, Akun $akun)
    {
        $validated = $request->validate([
            'is_beban_operasional' => ['required', 'boolean'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'alasan.required' => 'Alasan perubahan eligibility wajib diisi.',
            'alasan.min' => 'Alasan perubahan eligibility terlalu singkat.',
        ]);

        if ($validated['is_beban_operasional'] && (! $akun->is_aktif || $akun->kategori !== 'beban' || $akun->posisi_saldo !== 'debit')) {
            throw ValidationException::withMessages([
                'is_beban_operasional' => 'Hanya akun Beban aktif dengan saldo normal Debit yang boleh eligible untuk Beban Operasional.',
            ]);
        }

        $sebelum = (bool) $akun->is_beban_operasional;
        $sesudah = (bool) $validated['is_beban_operasional'];

        if ($sebelum !== $sesudah) {
            $akun->update([
                'is_beban_operasional' => $sesudah,
                'beban_operasional_updated_by' => $request->user()?->id,
            ]);

            RiwayatAkunBebanOperasional::query()->create([
                'akun_id' => $akun->id,
                'nilai_sebelum' => $sebelum,
                'nilai_sesudah' => $sesudah,
                'alasan' => trim((string) $validated['alasan']),
                'changed_by' => $request->user()?->id,
                'changed_at' => now(config('app.timezone', 'Asia/Jakarta')),
            ]);
        }

        return redirect()
            ->route('akun.index')
            ->with('success', 'Eligibility Beban Operasional berhasil diperbarui.');
    }
}
