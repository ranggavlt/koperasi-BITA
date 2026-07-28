<?php

namespace App\Http\Controllers;

use App\Models\DompetKoperasi;
use App\Models\KlaimDanaKhusus;
use App\Models\MutasiKas;
use App\Models\AkunAkuntansi;
use App\Services\AkuntansiService;
use App\Services\MutasiKasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KlaimDanaKhususController extends Controller
{
    public function __construct(
        private readonly AkuntansiService $akuntansiService,
        private readonly MutasiKasService $mutasiKasService
    ) {
    }

    public function index()
    {
        $klaims = KlaimDanaKhusus::with(['dompet', 'creator'])->latest()->paginate(20);
        $dompets = DompetKoperasi::where(function ($q) {
            $q->where('saldo_dana_sosial', '>', 0)
              ->orWhere('saldo_sumbangan_anggota', '>', 0);
        })->get();

        return view('pages.klaim-dana-khusus.index', [
            'klaims' => $klaims,
            'dompets' => $dompets
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'dompet_id' => 'required|exists:dompet_koperasi,id',
            'jenis_dana' => 'required|in:sosial,sumbangan',
            'kategori' => 'required|string|max:50',
            'nominal' => 'required|numeric|min:1',
            'tanggal' => 'required|date',
            'keterangan' => 'required|string',
        ]);

        try {
            DB::transaction(function () use ($validated, $request) {
                $dompet = DompetKoperasi::with('akun')->lockForUpdate()->findOrFail($validated['dompet_id']);
                
                $nominal = (float) $validated['nominal'];

                // Validate specific balance
                if ($validated['jenis_dana'] === 'sosial') {
                    if ($nominal > (float) $dompet->saldo_dana_sosial) {
                        throw ValidationException::withMessages(['nominal' => 'Nominal melebihi saldo Dana Sosial yang tersedia.']);
                    }
                    $dompet->saldo_dana_sosial -= $nominal;
                } else {
                    if ($nominal > (float) $dompet->saldo_sumbangan_anggota) {
                        throw ValidationException::withMessages(['nominal' => 'Nominal melebihi saldo Sumbangan Anggota yang tersedia.']);
                    }
                    $dompet->saldo_sumbangan_anggota -= $nominal;
                }

                // Decrease actual saldo
                if ($nominal > (float) $dompet->saldo) {
                    throw ValidationException::withMessages(['nominal' => 'Saldo dompet (kas real) tidak mencukupi untuk pencairan ini.']);
                }
                
                $dompet->saldo -= $nominal;
                $dompet->save();

                // Record Klaim
                $klaim = KlaimDanaKhusus::create([
                    'dompet_id' => $dompet->id,
                    'jenis_dana' => $validated['jenis_dana'],
                    'kategori' => $validated['kategori'],
                    'nominal' => $nominal,
                    'tanggal' => $validated['tanggal'],
                    'keterangan' => $validated['keterangan'],
                    'created_by' => $request->user()->id,
                ]);

                // Record Mutasi Kas
                $this->mutasiKasService->recordPengeluaran(
                    $dompet,
                    $nominal,
                    "Pencairan Dana " . ucfirst($validated['jenis_dana']) . " ({$validated['kategori']}): {$validated['keterangan']}"
                );

                // Record Jurnal Akuntansi
                // Asumsi: Kode beban operasional / dana sosial menggunakan akun tertentu
                // Jika tidak ada akun spesifik, gunakan beban lain-lain.
                $akunBebanKode = $validated['jenis_dana'] === 'sosial' ? config('account_map.beban_sosial', config('account_map.beban_lain_lain')) : config('account_map.beban_sumbangan', config('account_map.beban_lain_lain'));
                $akunBeban = AkunAkuntansi::where('kode', $akunBebanKode)->first() ?? AkunAkuntansi::where('kode', config('account_map.beban_lain_lain'))->first();

                if ($akunBeban && $dompet->akun) {
                    $this->akuntansiService->createJurnal(
                        $klaim->id,
                        'App\Models\KlaimDanaKhusus',
                        "Pencairan Dana " . ucfirst($validated['jenis_dana']) . " - {$validated['kategori']}",
                        [
                            ['akun_id' => $akunBeban->id, 'debit' => $nominal, 'kredit' => 0], // Beban Dana
                            ['akun_id' => $dompet->akun_id, 'debit' => 0, 'kredit' => $nominal], // Kas Keluar
                        ]
                    );
                }
            });

            return redirect()->route('klaim-dana-khusus.index')->with('success', 'Klaim dana berhasil dicairkan dan dicatat ke jurnal.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mencairkan dana: ' . $e->getMessage())->withInput();
        }
    }
}
