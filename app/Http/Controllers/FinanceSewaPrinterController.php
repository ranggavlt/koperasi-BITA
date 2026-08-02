<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelSewaPrinterRequest;
use App\Http\Requests\PaySewaPrinterRequest;
use App\Http\Requests\StoreSewaPrinterRequest;
use App\Http\Requests\UpdateSewaPrinterRequest;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\SewaPrinter;
use App\Services\SewaPrinterService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceSewaPrinterController extends Controller
{
    public function __construct(private readonly SewaPrinterService $service)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(SewaPrinter::statuses())],
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

        $query = SewaPrinter::query()
            ->with([
                'details',
                'karyawan',
                'recorder',
                'pembayaran.dompetPenerimaan.akun',
                'pembayaran.dompetVendor.akun',
                'pembayaranVendor.dompet.akun',
                'invoiceDetail.invoice',
                'perusahaan',
                'jurnal.details',
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

        $sewaPrinter = $query->latest()->paginate(10)->withQueryString();

        return view('pages.sewa-printer.index', [
            'sewaPrinter' => $sewaPrinter,
            'karyawanOptions' => Karyawan::query()->aktif()->orderBy('nama')->get(),
            'dompetOptions' => DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get(),
            'kasOperasionalOptions' => DompetKoperasi::query()
                ->where('jenis_dompet', DompetKoperasi::JENIS_KAS)
                ->where('is_kas_operasional', true)
                ->with('akun')
                ->orderBy('nama_dompet')
                ->get(),
            'statuses' => SewaPrinter::statusLabels(),
        ]);
    }

    public function create()
    {
        return view('pages.sewa-printer.form', $this->formOptions());
    }

    public function store(StoreSewaPrinterRequest $request)
    {
        $this->service->createDraft($request->validated(), $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Draft Sewa Hardware berhasil dibuat.');
    }

    public function edit(SewaPrinter $sewaPrinter)
    {
        abort_unless($sewaPrinter->status === SewaPrinter::STATUS_DRAFT, 404);

        return view('pages.sewa-printer.form', $this->formOptions($sewaPrinter->load('details')));
    }

    public function update(UpdateSewaPrinterRequest $request, SewaPrinter $sewaPrinter)
    {
        $this->service->updateDraft($sewaPrinter, $request->validated(), $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Draft Sewa Hardware berhasil diperbarui.');
    }

    public function confirm(Request $request, SewaPrinter $sewaPrinter)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $this->service->confirm($sewaPrinter, $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Kontrak Sewa Hardware berhasil dikonfirmasi.');
    }

    public function pay(PaySewaPrinterRequest $request, SewaPrinter $sewaPrinter)
    {
        $this->service->pay($sewaPrinter, $request->validated(), $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Pembayaran penuh Sewa Hardware berhasil dicatat.');
    }

    public function start(Request $request, SewaPrinter $sewaPrinter)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $this->service->start($sewaPrinter, $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Kontrak Sewa Hardware dimulai.');
    }

    public function complete(Request $request, SewaPrinter $sewaPrinter)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $this->service->complete($sewaPrinter, $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Kontrak Sewa Hardware selesai dan margin koperasi diakui.');
    }

    public function cancel(CancelSewaPrinterRequest $request, SewaPrinter $sewaPrinter)
    {
        $this->service->cancelByFinance($sewaPrinter, $request->validated('alasan'), $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Kontrak Sewa Hardware berhasil dibatalkan sebelum pembayaran.');
    }

    private function formOptions(?SewaPrinter $editData = null): array
    {
        return [
            'editData' => $editData,
            'karyawanOptions' => Karyawan::query()
                ->aktif()
                ->with('perusahaan')
                ->whereHas('perusahaan', fn ($query) => $query->whereIn('kode', ['BEE', 'BBS', 'BKM']))
                ->orderBy('nama')
                ->get(),
            'dompetOptions' => DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get(),
        ];
    }
}
