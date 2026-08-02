<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveSewaMobilRequest;
use App\Http\Requests\PaySewaMobilRequest;
use App\Http\Requests\RejectSewaMobilRequest;
use App\Http\Requests\StoreSewaMobilRequest;
use App\Http\Requests\UpdateSewaMobilRequest;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\PengurusKoperasi;
use App\Models\SewaMobil;
use App\Services\SewaMobilService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FinanceSewaMobilController extends Controller
{
    public function __construct(private readonly SewaMobilService $service)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'karyawan_id' => ['nullable', 'integer', 'exists:karyawan,id'],
            'tanggal_dari' => ['nullable', 'date'],
            'tanggal_sampai' => ['nullable', 'date'],
        ]);

        if ($request->filled('tanggal_dari') && $request->filled('tanggal_sampai')
            && $request->date('tanggal_sampai')->lt($request->date('tanggal_dari'))) {
            throw ValidationException::withMessages([
                'tanggal_sampai' => 'Tanggal sampai tidak boleh sebelum tanggal mulai.',
            ]);
        }

        $query = SewaMobil::query()
            ->with([
                'perusahaan',
                'karyawan',
                'pemohon',
                'recorder',
                'pengurusPenyetuju.anggota.karyawan',
                'approvalRecorder',
                'pembayaran.dompet.akun',
                'pembayaranVendor.dompet.akun',
                'invoiceDetail.invoice',
                'jurnal.details',
            ]);

        if (! empty($filters['karyawan_id'])) {
            $query->where('karyawan_id', $filters['karyawan_id']);
        }

        if (! empty($filters['tanggal_dari'])) {
            $query->whereDate('tanggal_selesai', '>=', $filters['tanggal_dari']);
        }

        if (! empty($filters['tanggal_sampai'])) {
            $query->whereDate('tanggal_mulai', '<=', $filters['tanggal_sampai']);
        }

        $sewaMobil = $query->latest()->paginate(10)->withQueryString();
        $karyawanOptions = Karyawan::query()->orderBy('nama')->get();
        $pengurusOptions = PengurusKoperasi::query()
            ->aktif()
            ->with('anggota.karyawan')
            ->whereHas('anggota', fn ($query) => $query->where('status', 'aktif')->whereHas('karyawan', fn ($k) => $k->aktif()))
            ->orderBy('jabatan')
            ->get();
        $dompetOptions = DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get();
        $kasOperasionalOptions = DompetKoperasi::query()
            ->where('jenis_dompet', DompetKoperasi::JENIS_KAS)
            ->where('is_kas_operasional', true)
            ->with('akun')
            ->orderBy('nama_dompet')
            ->get();

        return view('pages.sewa-mobil.finance.index', compact(
            'sewaMobil',
            'karyawanOptions',
            'pengurusOptions',
            'dompetOptions',
            'kasOperasionalOptions'
        ));
    }

    public function create()
    {
        return view('pages.sewa-mobil.finance.form', $this->formOptions());
    }

    public function store(StoreSewaMobilRequest $request)
    {
        $this->service->createDraft($request->validated(), $request->user()->id);

        return redirect()->route('sewa-mobil.finance.index')
            ->with('success', 'Draft Sewa Mobil berhasil dibuat oleh Finance.');
    }

    public function edit(SewaMobil $sewaMobil)
    {
        abort_unless($sewaMobil->status === SewaMobil::STATUS_DRAFT, 404);

        return view('pages.sewa-mobil.finance.form', $this->formOptions($sewaMobil->load(['karyawan.perusahaan'])));
    }

    public function update(UpdateSewaMobilRequest $request, SewaMobil $sewaMobil)
    {
        $this->service->updateDraft($sewaMobil, $request->validated(), $request->user()->id);

        return redirect()->route('sewa-mobil.finance.index')
            ->with('success', 'Draft Sewa Mobil berhasil diperbarui.');
    }

    public function submit(Request $request, SewaMobil $sewaMobil)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $this->service->submit($sewaMobil, $request->user()->id);

        return redirect()->route('sewa-mobil.finance.index')
            ->with('success', 'Draft Sewa Mobil berhasil diajukan untuk pencatatan approval.');
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

    private function formOptions(?SewaMobil $editData = null): array
    {
        return [
            'editData' => $editData,
            'karyawanOptions' => Karyawan::query()
                ->aktif()
                ->with('perusahaan')
                ->whereHas('perusahaan', fn ($query) => $query->whereIn('kode', ['BEE', 'BBS', 'BKM']))
                ->orderBy('nama')
                ->get(),
        ];
    }
}
