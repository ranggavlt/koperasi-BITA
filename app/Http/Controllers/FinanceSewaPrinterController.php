<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelSewaPrinterRequest;
use App\Http\Requests\PaySewaPrinterRequest;
use App\Http\Requests\StoreSewaPrinterRequest;
use App\Http\Requests\UpdateSewaPrinterRequest;
use App\Models\AsetKoperasi;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\SewaPrinter;
use App\Services\SewaPrinterService;
use Illuminate\Http\Request;

class FinanceSewaPrinterController extends Controller
{
    public function __construct(private readonly SewaPrinterService $service)
    {
    }

    public function index(Request $request)
    {
        $query = SewaPrinter::query()
            ->with([
                'details.aset.printer',
                'karyawanPic',
                'pembayaran.dompet.akun',
                'jurnal.details',
            ]);

        if ($request->filled('status') && in_array($request->string('status')->toString(), SewaPrinter::statuses(), true)) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('karyawan_pic_id')) {
            $query->where('karyawan_pic_id', $request->integer('karyawan_pic_id'));
        }

        if ($request->filled('periode')) {
            $periode = $request->date('periode');
            $query->whereBetween('mulai_tanggal', [
                $periode->copy()->startOfMonth()->toDateString(),
                $periode->copy()->endOfMonth()->toDateString(),
            ]);
        }

        $sewaPrinter = $query->latest()->paginate(10)->withQueryString();

        return view('pages.sewa-printer.index', [
            'sewaPrinter' => $sewaPrinter,
            'printerOptions' => AsetKoperasi::query()->printer()->with('printer')->orderBy('kode_aset')->get(),
            'karyawanOptions' => Karyawan::query()->aktif()->orderBy('nama')->get(),
            'dompetOptions' => DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get(),
            'statuses' => SewaPrinter::statusLabels(),
            'editData' => null,
        ]);
    }

    public function store(StoreSewaPrinterRequest $request)
    {
        $this->service->createDraft($request->validated(), $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Draft Sewa Printer berhasil dibuat.');
    }

    public function edit(SewaPrinter $sewaPrinter)
    {
        abort_unless($sewaPrinter->status === SewaPrinter::STATUS_DRAFT, 404);

        $sewaPrinterList = SewaPrinter::query()
            ->with(['details.aset.printer', 'karyawanPic', 'pembayaran.dompet.akun', 'jurnal.details'])
            ->latest()
            ->paginate(10);

        return view('pages.sewa-printer.index', [
            'sewaPrinter' => $sewaPrinterList,
            'printerOptions' => AsetKoperasi::query()->printer()->with('printer')->orderBy('kode_aset')->get(),
            'karyawanOptions' => Karyawan::query()->aktif()->orderBy('nama')->get(),
            'dompetOptions' => DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get(),
            'statuses' => SewaPrinter::statusLabels(),
            'editData' => $sewaPrinter->load('details'),
        ]);
    }

    public function update(UpdateSewaPrinterRequest $request, SewaPrinter $sewaPrinter)
    {
        $this->service->updateDraft($sewaPrinter, $request->validated(), $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Draft Sewa Printer berhasil diperbarui.');
    }

    public function confirm(Request $request, SewaPrinter $sewaPrinter)
    {
        abort_unless($request->user()?->role === 'keuangan', 403);

        $this->service->confirm($sewaPrinter, $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Kontrak Sewa Printer berhasil dikonfirmasi.');
    }

    public function pay(PaySewaPrinterRequest $request, SewaPrinter $sewaPrinter)
    {
        $this->service->pay($sewaPrinter, $request->validated(), $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Pembayaran penuh Sewa Printer berhasil dicatat.');
    }

    public function start(Request $request, SewaPrinter $sewaPrinter)
    {
        abort_unless($request->user()?->role === 'keuangan', 403);

        $this->service->start($sewaPrinter, $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Kontrak Sewa Printer dimulai dan seluruh Printer berubah menjadi digunakan/disewa.');
    }

    public function complete(Request $request, SewaPrinter $sewaPrinter)
    {
        abort_unless($request->user()?->role === 'keuangan', 403);

        $this->service->complete($sewaPrinter, $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Kontrak Sewa Printer selesai dan pendapatan dasar/margin diakui.');
    }

    public function cancel(CancelSewaPrinterRequest $request, SewaPrinter $sewaPrinter)
    {
        $this->service->cancelByFinance($sewaPrinter, $request->validated('alasan'), $request->user()->id);

        return redirect()->route('sewa-printer.index')
            ->with('success', 'Kontrak Sewa Printer berhasil dibatalkan/refund sesuai eligibility.');
    }
}
