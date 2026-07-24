<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAsetMobilRequest;
use App\Http\Requests\UpdateAsetMobilRequest;
use App\Http\Requests\UpdateStatusAsetRequest;
use App\Models\AsetKoperasi;
use App\Services\AsetKoperasiService;
use Illuminate\Http\Request;

class AsetMobilController extends Controller
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

        $asetMobil = AsetKoperasi::query()
            ->mobil()
            ->with(['mobil', 'creator', 'updater', 'nonaktifBy'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('kode_aset', 'like', "%{$search}%")
                        ->orWhere('merek', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhereHas('mobil', function ($detail) use ($search): void {
                            $detail->where('plat_nomor', 'like', "%{$search}%")
                                ->orWhere('warna', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $deleteGuards = $asetMobil
            ->getCollection()
            ->mapWithKeys(fn (AsetKoperasi $aset) => [$aset->id => $this->service->canDelete($aset)])
            ->all();

        $vendors = \App\Models\Vendor::orderBy('nama')->get();

        return view('pages.aset-mobil.index', [
            'asetMobil' => $asetMobil,
            'statuses' => AsetKoperasi::statusLabels(),
            'deleteGuards' => $deleteGuards,
            'vendors' => $vendors,
        ]);
    }

    public function store(StoreAsetMobilRequest $request)
    {
        $this->service->createMobil($request->validated(), $request->user()->id);

        return redirect()
            ->route('aset-mobil.index')
            ->with('success', 'Mobil koperasi berhasil ditambahkan.');
    }

    public function edit(AsetKoperasi $aset)
    {
        $this->abortIfWrongType($aset);

        $asetMobil = AsetKoperasi::query()
            ->mobil()
            ->with(['mobil', 'creator', 'updater', 'nonaktifBy'])
            ->latest()
            ->paginate(10);

        $deleteGuards = $asetMobil
            ->getCollection()
            ->mapWithKeys(fn (AsetKoperasi $item) => [$item->id => $this->service->canDelete($item)])
            ->all();

        $vendors = \App\Models\Vendor::orderBy('nama')->get();

        return view('pages.aset-mobil.index', [
            'data' => $aset->load('mobil'),
            'asetMobil' => $asetMobil,
            'statuses' => AsetKoperasi::statusLabels(),
            'deleteGuards' => $deleteGuards,
            'vendors' => $vendors,
        ]);
    }

    public function update(UpdateAsetMobilRequest $request, AsetKoperasi $aset)
    {
        $this->abortIfWrongType($aset);

        $this->service->updateMobil($aset, $request->validated(), $request->user()->id);

        return redirect()
            ->route('aset-mobil.index')
            ->with('success', 'Mobil koperasi berhasil diperbarui.');
    }

    public function updateStatus(UpdateStatusAsetRequest $request, AsetKoperasi $aset)
    {
        $this->abortIfWrongType($aset);

        $this->service->updateStatus($aset, $request->validated('status'), $request->user()->id);

        return redirect()
            ->route('aset-mobil.index')
            ->with('success', 'Status mobil koperasi berhasil diperbarui.');
    }

    public function nonaktifkan(Request $request, AsetKoperasi $aset)
    {
        $this->abortIfWrongType($aset);

        $this->service->nonaktifkan($aset, $request->user()->id);

        return redirect()
            ->route('aset-mobil.index')
            ->with('success', 'Mobil koperasi berhasil dinonaktifkan.');
    }

    public function aktifkan(Request $request, AsetKoperasi $aset)
    {
        $this->abortIfWrongType($aset);

        $this->service->aktifkan($aset, $request->user()->id);

        return redirect()
            ->route('aset-mobil.index')
            ->with('success', 'Mobil koperasi berhasil diaktifkan kembali.');
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
            ->route('aset-mobil.index')
            ->with('success', 'Mobil koperasi berhasil dihapus.');
    }

    private function abortIfWrongType(AsetKoperasi $aset): void
    {
        abort_unless($aset->jenis_aset === AsetKoperasi::JENIS_MOBIL, 404);
    }
}
