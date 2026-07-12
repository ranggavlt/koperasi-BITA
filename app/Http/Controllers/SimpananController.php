<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\JenisSimpanan;
use App\Models\Simpanan;
use App\Services\AkuntansiService;
use App\Services\MutasiKasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SimpananController extends Controller
{
    public function index(): View
    {
        $simpanan = Simpanan::query()
            ->with(['anggota.karyawan', 'karyawan', 'jenisSimpanan', 'ledger'])
            ->latest()
            ->paginate(10);

        $anggota = Anggota::query()
            ->with('karyawan')
            ->aktif()
            ->orderBy('nomor_anggota')
            ->get();

        $jenis = JenisSimpanan::query()
            ->aktif()
            ->where(fn ($query) => $query
                ->whereNull('kode')
                ->orWhere('kode', '!=', JenisSimpanan::KODE_SIMPANAN_POKOK))
            ->orderBy('nama_jenis')
            ->get();

        $dompet = DompetKoperasi::query()
            ->with('akun')
            ->orderBy('nama_dompet')
            ->get();

        return view('pages.simpanan.index', compact('simpanan', 'anggota', 'jenis', 'dompet'));
    }

    public function store(Request $request, MutasiKasService $mutasiKasService, AkuntansiService $akuntansiService): RedirectResponse
    {
        $validated = $request->validate([
            'anggota_id' => 'required|exists:anggota,id',
            'jenis_simpanan_id' => [
                'required',
                Rule::exists('jenis_simpanan', 'id')->where(fn ($query) => $query->where('aktif', true)),
            ],
            'dompet_id' => 'required|exists:dompet_koperasi,id',
            'jumlah' => 'required|numeric|min:1',
            'tanggal' => 'required|date',
            'keterangan' => 'nullable|string',
        ], [
            'anggota_id.required' => 'Anggota wajib dipilih.',
            'jenis_simpanan_id.required' => 'Jenis simpanan wajib dipilih.',
            'dompet_id.required' => 'Dompet penerimaan wajib dipilih.',
            'jumlah.required' => 'Jumlah simpanan wajib diisi.',
            'tanggal.required' => 'Tanggal simpanan wajib diisi.',
        ]);

        $anggota = Anggota::query()->with('karyawan')->findOrFail($validated['anggota_id']);
        $jenis = JenisSimpanan::query()->findOrFail($validated['jenis_simpanan_id']);

        if ($jenis->kode === JenisSimpanan::KODE_SIMPANAN_POKOK) {
            return back()
                ->withErrors(['jenis_simpanan_id' => 'Simpanan Pokok dibuat otomatis saat Anggota dibuat dan tidak boleh diinput manual.'])
                ->withInput();
        }

        try {
            DB::transaction(function () use ($validated, $anggota, $jenis, $mutasiKasService, $akuntansiService): void {
                $simpanan = Simpanan::query()->create([
                    'idempotency_key' => 'simpanan:manual:' . uniqid('', true),
                    'anggota_id' => $anggota->id,
                    'karyawan_id' => $anggota->karyawan_id,
                    'jenis_simpanan_id' => $jenis->id,
                    'kode_jenis_snapshot' => $jenis->kode,
                    'nama_jenis_snapshot' => $jenis->nama_jenis,
                    'nominal_snapshot' => $validated['jumlah'],
                    'jumlah' => $validated['jumlah'],
                    'metode_pembayaran' => Simpanan::METODE_TUNAI,
                    'status' => Simpanan::STATUS_SETTLED,
                    'settled_at' => now(),
                    'tanggal' => $validated['tanggal'],
                    'keterangan' => $validated['keterangan'] ?? null,
                    'created_by' => auth()->id(),
                ]);

                $mutasiKasService->record([
                    'dompet_id' => $validated['dompet_id'],
                    'tipe' => 'masuk',
                    'jumlah' => $validated['jumlah'],
                    'keterangan' => 'Penerimaan simpanan anggota',
                    'referensi_tipe' => Simpanan::class,
                    'referensi_id' => $simpanan->id,
                    'tanggal' => $validated['tanggal'],
                ]);

                $akuntansiService->recordSimpanan($simpanan);
            });

            return redirect()
                ->route('simpanan.index')
                ->with('success', 'Transaksi simpanan berhasil disimpan.');
        } catch (\Throwable $exception) {
            return back()
                ->withErrors(['simpanan' => $exception->getMessage()])
                ->withInput();
        }
    }
}
