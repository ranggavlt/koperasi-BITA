<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAsetPrinterRequest;
use App\Http\Requests\UpdateAsetPrinterRequest;
use App\Http\Requests\UpdateStatusAsetRequest;
use App\Models\AsetKoperasi;
use App\Services\AsetKoperasiService;
use Illuminate\Http\Request;

class AsetPrinterController extends Controller
{
    public function __construct(private readonly AsetKoperasiService $service)
    {
    }

    public function index(Request $request)
    {
        $status = in_array($request->string('status')->toString(), AsetKoperasi::statuses(), true)
            ? $request->string('status')->toString()
            : null;
        $search = trim($request->string('q')->toString());

        $asetPrinter = AsetKoperasi::query()
            ->printer()
            ->with(['printer', 'creator', 'updater', 'nonaktifBy'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('kode_aset', 'like', "%{$search}%")
                        ->orWhere('merek', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhereHas('printer', function ($detail) use ($search): void {
                            $detail->where('nomor_seri', 'like', "%{$search}%")
                                ->orWhere('lokasi', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $deleteGuards = $asetPrinter
            ->getCollection()
            ->mapWithKeys(fn (AsetKoperasi $aset) => [$aset->id => $this->service->canDelete($aset)])
            ->all();

        $vendors = \App\Models\Vendor::orderBy('nama')->get();

        return view('pages.aset-printer.index', [
            'asetPrinter' => $asetPrinter,
            'statuses' => AsetKoperasi::statusLabels(),
            'deleteGuards' => $deleteGuards,
            'vendors' => $vendors,
        ]);
    }

    public function store(StoreAsetPrinterRequest $request)
    {
        $this->service->createPrinter($request->validated(), $request->user()->id);

        return redirect()
            ->route('aset-printer.index')
            ->with('success', 'Printer koperasi berhasil ditambahkan.');
    }

    public function edit(AsetKoperasi $aset)
    {
        $this->abortIfWrongType($aset);

        $asetPrinter = AsetKoperasi::query()
            ->printer()
            ->with(['printer', 'creator', 'updater', 'nonaktifBy'])
            ->latest()
            ->paginate(10);

        $deleteGuards = $asetPrinter
            ->getCollection()
            ->mapWithKeys(fn (AsetKoperasi $item) => [$item->id => $this->service->canDelete($item)])
            ->all();

        $vendors = \App\Models\Vendor::orderBy('nama')->get();

        return view('pages.aset-printer.index', [
            'data' => $aset->load('printer'),
            'asetPrinter' => $asetPrinter,
            'statuses' => AsetKoperasi::statusLabels(),
            'deleteGuards' => $deleteGuards,
            'vendors' => $vendors,
        ]);
    }

    public function update(UpdateAsetPrinterRequest $request, AsetKoperasi $aset)
    {
        $this->abortIfWrongType($aset);

        $this->service->updatePrinter($aset, $request->validated(), $request->user()->id);

        return redirect()
            ->route('aset-printer.index')
            ->with('success', 'Printer koperasi berhasil diperbarui.');
    }

    public function updateStatus(UpdateStatusAsetRequest $request, AsetKoperasi $aset)
    {
        $this->abortIfWrongType($aset);

        $this->service->updateStatus($aset, $request->validated('status'), $request->user()->id);

        return redirect()
            ->route('aset-printer.index')
            ->with('success', 'Status printer koperasi berhasil diperbarui.');
    }

    public function nonaktifkan(Request $request, AsetKoperasi $aset)
    {
        $this->abortIfWrongType($aset);

        $this->service->nonaktifkan($aset, $request->user()->id);

        return redirect()
            ->route('aset-printer.index')
            ->with('success', 'Printer koperasi berhasil dinonaktifkan.');
    }

    public function aktifkan(Request $request, AsetKoperasi $aset)
    {
        $this->abortIfWrongType($aset);

        $this->service->aktifkan($aset, $request->user()->id);

        return redirect()
            ->route('aset-printer.index')
            ->with('success', 'Printer koperasi berhasil diaktifkan kembali.');
    }

    public function destroy(Request $request, AsetKoperasi $aset)
    {
        $this->abortIfWrongType($aset);

        $validated = $request->validate([
            'confirm_delete' => ['accepted'],
        ], [
            'confirm_delete.accepted' => 'Konfirmasi penghapusan aset wajib dicentang.',
        ]);

        $this->service->delete($aset, (bool) ($validated['confirm_delete'] ?? false), $request->user()->id);

        return redirect()
            ->route('aset-printer.index')
            ->with('success', 'Printer koperasi berhasil dihapus.');
    }

    private function abortIfWrongType(AsetKoperasi $aset): void
    {
        abort_unless($aset->jenis_aset === AsetKoperasi::JENIS_PRINTER, 404);
    }
}
