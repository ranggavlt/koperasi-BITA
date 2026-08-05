<?php

namespace App\Http\Controllers;

use App\Models\DompetKoperasi;
use App\Models\PeriodeAkuntansi;
use App\Models\ShuKoperasi;
use App\Models\ShuPenerima;
use App\Services\AnnualShuService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShuKoperasiController extends Controller
{
    public function __construct(private readonly AnnualShuService $service) {}

    public function index()
    {
        $availablePeriods = PeriodeAkuntansi::query()
            ->where('status', PeriodeAkuntansi::STATUS_CLOSED)
            ->whereDoesntHave('shu')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $processes = ShuKoperasi::query()
            ->with('periode')
            ->latest('tanggal_mulai')
            ->paginate(15);

        return view('pages.shu-koperasi.index', compact('availablePeriods', 'processes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['periode_id' => 'required|exists:periode_akuntansi,id']);
        $shu = $this->service->applyPeriod(
            PeriodeAkuntansi::query()->findOrFail($data['periode_id']),
            (int) $request->user()->id
        );

        return redirect()->route('shu-koperasi.show', $shu)
            ->with('success', 'Periode diterapkan. Laba, konfigurasi, penerima, dan rancangan pembagian telah dimuat.');
    }

    public function show(ShuKoperasi $shuKoperasi)
    {
        $shuKoperasi->load([
            'periode', 'config', 'creator', 'calculator', 'submitter', 'approver',
            'recipients' => fn ($query) => $query->with(['anggota.karyawan', 'pengurus', 'pembayaran.dompet', 'pembayaran.creator'])
                ->orderBy('jenis_penerima')->orderByDesc('nominal_hak'),
            'socialFund',
        ]);

        return view('pages.shu-koperasi.show', [
            'shu' => $shuKoperasi,
            'wallets' => DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get(),
            'alternativePeriods' => PeriodeAkuntansi::query()
                ->where('status', PeriodeAkuntansi::STATUS_CLOSED)
                ->where(fn ($query) => $query->whereDoesntHave('shu')->orWhere('id', $shuKoperasi->periode_akuntansi_id))
                ->orderByDesc('tanggal_mulai')
                ->get(),
        ]);
    }

    public function changePeriod(Request $request, ShuKoperasi $shuKoperasi)
    {
        $data = $request->validate(['periode_id' => 'required|exists:periode_akuntansi,id']);
        $this->service->changePeriod(
            $shuKoperasi,
            PeriodeAkuntansi::query()->findOrFail($data['periode_id']),
            (int) $request->user()->id
        );

        return back()->with('success', 'Periode diganti dan seluruh preview pembagian sudah dihitung ulang.');
    }

    public function calculate(ShuKoperasi $shuKoperasi)
    {
        $this->service->calculate($shuKoperasi, (int) auth()->id());

        return back()->with('success', 'Rancangan pembagian berhasil diterapkan ulang dari data periode.');
    }

    public function weights(Request $request, ShuKoperasi $shuKoperasi)
    {
        $data = $request->validate([
            'bobot' => ['required', 'array'],
            'bobot.*' => ['required', 'numeric', 'gt:0', 'max:99999'],
        ]);
        $this->service->applyWeights($shuKoperasi, $data['bobot'], (int) $request->user()->id);

        return back()->with('success', 'Bobot RAT diterapkan dan preview nominal setiap penerima sudah diperbarui.');
    }

    public function resetWeights(ShuKoperasi $shuKoperasi)
    {
        $this->service->resetWeights($shuKoperasi, (int) auth()->id());

        return back()->with('success', 'Semua bobot jabatan dikembalikan ke 1 dan pool dibagi sama rata.');
    }

    public function submit(ShuKoperasi $shuKoperasi)
    {
        $this->service->submit($shuKoperasi, (int) auth()->id());

        return back()->with('success', 'SHU diajukan untuk persetujuan Admin lain.');
    }

    public function approve(ShuKoperasi $shuKoperasi)
    {
        $this->service->approve($shuKoperasi, (int) auth()->id());

        return back()->with('success', 'SHU disetujui dan nominal historis dikunci.');
    }

    public function pay(Request $request, ShuPenerima $penerima)
    {
        $data = $request->validate([
            'metode' => ['required', Rule::in(['tunai', 'transfer_bank'])],
            'dompet_id' => 'required|exists:dompet_koperasi,id',
            'tanggal_bayar' => 'required|date',
            'nomor_referensi' => 'nullable|string|max:120',
        ]);
        $this->service->pay($penerima, $data, (int) $request->user()->id);

        return back()->with('success', 'Pembayaran SHU penerima berhasil dicatat.');
    }
}
