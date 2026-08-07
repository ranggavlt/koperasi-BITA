<?php

namespace App\Http\Controllers;

use App\Models\DompetKoperasi;
use App\Models\PeriodeAkuntansi;
use App\Models\ShuKoperasi;
use App\Models\ShuPenerima;
use App\Models\PembayaranShu;
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

        $summary = [
            'periods' => ShuKoperasi::query()->count(),
            'approved' => ShuKoperasi::query()->where('status', ShuKoperasi::STATUS_APPROVED)->count(),
            'total_shu' => (int) ShuKoperasi::query()->where('status', ShuKoperasi::STATUS_APPROVED)->sum('shu_total'),
            'unpaid' => (int) ShuKoperasi::query()->where('status', ShuKoperasi::STATUS_APPROVED)->sum('total_belum_dibayar'),
        ];

        return view('pages.shu-koperasi.index', compact('availablePeriods', 'processes', 'summary'));
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
                ->with('struktur.anggota.karyawan')->orderBy('jenis_penerima')->orderByDesc('hak_final'),
            'socialFund',
        ]);

        return view('pages.shu-koperasi.show', [
            'shu' => $shuKoperasi,
            'wallets' => DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get(),
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

        return back()->with('success', 'SHU berstatus Siap Disetujui dan menunggu Admin lain.');
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
            'catatan' => 'nullable|string|max:1000',
        ]);
        $this->service->pay($penerima, $data, (int) $request->user()->id);

        return back()->with('success', 'Pembayaran SHU penerima berhasil dicatat.');
    }

    public function eligibility(Request $request, ShuPenerima $penerima)
    {
        $data = $request->validate([
            'diikutkan' => ['required', 'boolean'],
            'alasan_eligibility' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $this->service->setEligibility($penerima, (bool) $data['diikutkan'], $data['alasan_eligibility'], (int) $request->user()->id);
        return back()->with('success', 'Keikutsertaan anggota diperbarui dan pembagian dihitung ulang.');
    }

    public function finalRight(Request $request, ShuPenerima $penerima)
    {
        $data = $request->validate([
            'hak_final' => ['required', 'integer', 'min:0'],
            'alasan_hak_final' => ['required', Rule::in(ShuPenerima::ALASAN_HAK_FINAL)],
            'detail_alasan_hak_final' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->service->setFinalRight($penerima, (int) $data['hak_final'], $data['alasan_hak_final'], $data['detail_alasan_hak_final'] ?? null, (int) $request->user()->id);
        return back()->with('success', 'Hak Final penerima disimpan. Pastikan total kelompok tetap seimbang sebelum persetujuan.');
    }

    public function reversePayment(Request $request, PembayaranShu $pembayaran)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'reversal_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $this->service->reversePayment($pembayaran, $data['tanggal'], $data['reversal_reason'], (int) $request->user()->id);
        return back()->with('success', 'Pembayaran SHU berhasil direversal melalui mutasi dan jurnal lawan.');
    }
}
