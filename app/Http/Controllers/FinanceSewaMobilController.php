<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveSewaMobilRequest;
use App\Http\Requests\PaySewaMobilRequest;
use App\Http\Requests\RejectSewaMobilRequest;
use App\Models\AsetKoperasi;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\PengurusKoperasi;
use App\Models\SewaMobil;
use App\Services\SewaMobilService;
use Illuminate\Http\Request;

class FinanceSewaMobilController extends Controller
{
    public function __construct(private readonly SewaMobilService $service)
    {
    }

    public function index(Request $request)
    {
        $query = SewaMobil::query()
            ->with([
                'aset.mobil',
                'karyawan',
                'pemohon',
                'pengurusPenyetuju.anggota.karyawan',
                'approvalRecorder',
                'pembayaran.dompet.akun',
                'jurnal.details',
            ]);

        if ($request->filled('status') && in_array($request->string('status')->toString(), SewaMobil::statuses(), true)) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('aset_koperasi_id')) {
            $query->where('aset_koperasi_id', $request->integer('aset_koperasi_id'));
        }

        if ($request->filled('karyawan_id')) {
            $query->where('karyawan_id', $request->integer('karyawan_id'));
        }

        if ($request->filled('periode')) {
            $query->whereBetween('mulai_at', [
                $request->date('periode')->startOfMonth(),
                $request->date('periode')->endOfMonth(),
            ]);
        }

        $sewaMobil = $query->latest()->paginate(10)->withQueryString();
        $mobilOptions = AsetKoperasi::query()->mobil()->with('mobil')->orderBy('kode_aset')->get();
        $karyawanOptions = Karyawan::query()->orderBy('nama')->get();
        $pengurusOptions = PengurusKoperasi::query()
            ->aktif()
            ->with('anggota.karyawan')
            ->whereHas('anggota', fn ($query) => $query->where('status', 'aktif')->whereHas('karyawan', fn ($k) => $k->aktif()))
            ->orderBy('jabatan')
            ->get();
        $dompetOptions = DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get();
        $statuses = SewaMobil::statusLabels();

        return view('pages.sewa-mobil.finance.index', compact(
            'sewaMobil',
            'mobilOptions',
            'karyawanOptions',
            'pengurusOptions',
            'dompetOptions',
            'statuses'
        ));
    }

    public function approve(ApproveSewaMobilRequest $request, SewaMobil $sewaMobil)
    {
        $this->service->approve($sewaMobil, $request->validated(), $request->user()->id);

        return redirect()->route('sewa-mobil.finance.index')
            ->with('success', 'Pengajuan Sewa Mobil berhasil disetujui.');
    }

    public function reject(RejectSewaMobilRequest $request, SewaMobil $sewaMobil)
    {
        $this->service->reject($sewaMobil, $request->validated('alasan'), $request->user()->id);

        return redirect()->route('sewa-mobil.finance.index')
            ->with('success', 'Pengajuan Sewa Mobil berhasil ditolak.');
    }

    public function pay(PaySewaMobilRequest $request, SewaMobil $sewaMobil)
    {
        $this->service->pay($sewaMobil, $request->validated(), $request->user()->id);

        return redirect()->route('sewa-mobil.finance.index')
            ->with('success', 'Pembayaran penuh Sewa Mobil berhasil dicatat.');
    }

    public function start(SewaMobil $sewaMobil)
    {
        $this->service->start($sewaMobil, auth()->id());

        return redirect()->route('sewa-mobil.finance.index')
            ->with('success', 'Kegiatan Sewa Mobil dimulai dan status mobil menjadi digunakan/disewa.');
    }

    public function complete(SewaMobil $sewaMobil)
    {
        $this->service->complete($sewaMobil, auth()->id());

        return redirect()->route('sewa-mobil.finance.index')
            ->with('success', 'Sewa Mobil selesai, mobil kembali tersedia, dan pendapatan diakui.');
    }

    public function cancel(RejectSewaMobilRequest $request, SewaMobil $sewaMobil)
    {
        $this->service->cancelByFinance($sewaMobil, $request->validated('alasan'), $request->user()->id);

        return redirect()->route('sewa-mobil.finance.index')
            ->with('success', 'Sewa Mobil berhasil dibatalkan/refund sesuai eligibility.');
    }
}
