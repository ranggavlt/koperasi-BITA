<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelSewaHardwareRequest;
use App\Http\Requests\RefundSewaHardwareRequest;
use App\Http\Requests\StoreSewaHardwareRequest;
use App\Http\Requests\UpdateSewaHardwareRequest;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\SewaHardware;
use App\Models\SewaHardwareDetail;
use App\Services\SewaHardwareService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceSewaHardwareController extends Controller
{
    public function __construct(private readonly SewaHardwareService $service)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(SewaHardware::statuses())],
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

        $query = SewaHardware::query()
            ->with([
                'details',
                'karyawan',
                'recorder',
                'pembayaran.dompetPenerimaan.akun',
                'pembayaran.dompetVendor.akun',
                'pembayaran.mutasiKas',
                'pembayaran.jurnal.details',
                'jurnal.details',
                'reversal',
                'pembayaranVendor.dompet.akun',
                'invoiceDetail.invoice',
            ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['karyawan_id'])) {
            $query->where('karyawan_id', $filters['karyawan_id']);
        }

        if (! empty($filters['tanggal_dari'])) {
            $query->whereDate('selesai_tanggal', '>=', $filters['tanggal_dari']);
        }

        if (! empty($filters['tanggal_sampai'])) {
            $query->whereDate('mulai_tanggal', '<=', $filters['tanggal_sampai']);
        }

        $sewaHardware = $query->latest()->paginate(10)->withQueryString();

        return view('pages.sewa-hardware.index', [
            'sewaHardware' => $sewaHardware,
            'karyawanOptions' => $this->officialKaryawanQuery()->get(),
            'dompetOptions' => DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get(),
            'kasOperasionalOptions' => DompetKoperasi::query()
                ->where('jenis_dompet', DompetKoperasi::JENIS_KAS)
                ->where('is_kas_operasional', true)
                ->with('akun')
                ->orderBy('nama_dompet')
                ->get(),
            'statuses' => SewaHardware::statusLabels(),
            'paymentStatuses' => SewaHardware::paymentStatuses(),
            'jenisHardwareOptions' => SewaHardwareDetail::jenisOptions(),
        ]);
    }

    public function create()
    {
        return view('pages.sewa-hardware.form', $this->formOptions());
    }

    public function store(StoreSewaHardwareRequest $request)
    {
        $this->service->createDraft($request->validated(), $request->user()->id);

        return redirect()->route('sewa-hardware.index')
            ->with('success', 'Draft Sewa Hardware berhasil dibuat.');
    }

    public function edit(SewaHardware $sewaHardware)
    {
        abort_unless($sewaHardware->status === SewaHardware::STATUS_DRAFT, 404);

        return view('pages.sewa-hardware.form', $this->formOptions($sewaHardware->load('details')));
    }

    public function update(UpdateSewaHardwareRequest $request, SewaHardware $sewaHardware)
    {
        $this->service->updateDraft($sewaHardware, $request->validated(), $request->user()->id);

        return redirect()->route('sewa-hardware.index')
            ->with('success', 'Draft Sewa Hardware berhasil diperbarui.');
    }

    public function confirm(Request $request, SewaHardware $sewaHardware)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $this->service->confirm($sewaHardware, $request->user()->id);

        return redirect()->route('sewa-hardware.index')
            ->with('success', 'Kontrak Sewa Hardware berhasil dikonfirmasi.');
    }

    public function start(Request $request, SewaHardware $sewaHardware)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $this->service->start($sewaHardware, $request->user()->id);

        return redirect()->route('sewa-hardware.index')
            ->with('success', 'Kontrak Sewa Hardware dimulai.');
    }

    public function complete(Request $request, SewaHardware $sewaHardware)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $this->service->complete($sewaHardware, $request->user()->id);

        return redirect()->route('sewa-hardware.index')
            ->with('success', 'Kontrak Sewa Hardware selesai dan margin koperasi diakui.');
    }

    public function cancel(CancelSewaHardwareRequest $request, SewaHardware $sewaHardware)
    {
        $this->service->cancelByFinance($sewaHardware, $request->validated('alasan'), $request->user()->id);

        return redirect()->route('sewa-hardware.index')
            ->with('success', 'Kontrak Sewa Hardware berhasil dibatalkan sebelum pembayaran.');
    }

    public function refund(RefundSewaHardwareRequest $request, SewaHardware $sewaHardware)
    {
        $this->service->refundByFinance($sewaHardware, $request->validated('alasan'), $request->user()->id);

        return redirect()->route('sewa-hardware.index')
            ->with('success', 'Refund penuh Sewa Hardware berhasil dicatat.');
    }

    private function formOptions(?SewaHardware $editData = null): array
    {
        return [
            'editData' => $editData,
            'karyawanOptions' => $this->officialKaryawanQuery()->get(),
            'dompetOptions' => DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get(),
            'jenisHardwareOptions' => SewaHardwareDetail::jenisOptions(),
        ];
    }

    private function officialKaryawanQuery()
    {
        return Karyawan::query()
            ->aktif()
            ->whereHas('perusahaan', fn ($query) => $query->whereIn('kode', ['BEE', 'BBS', 'BKM']))
            ->with('perusahaan')
            ->orderBy('nama');
    }
}
