<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectSewaMobilRequest;
use App\Http\Requests\StoreSewaMobilRequest;
use App\Http\Requests\UpdateSewaMobilRequest;
use App\Models\AsetKoperasi;
use App\Models\SewaMobil;
use App\Services\SewaMobilService;

class KaryawanSewaMobilController extends Controller
{
    public function __construct(private readonly SewaMobilService $service)
    {
    }

    public function index()
    {
        $user = auth()->user();
        $sewaMobil = SewaMobil::query()
            ->with(['aset.mobil', 'pembayaran.dompet', 'pengurusPenyetuju'])
            ->ownedByUser($user->id)
            ->latest()
            ->paginate(10);

        $mobilOptions = $this->mobilOptions();

        return view('pages.sewa-mobil.karyawan.index', compact('sewaMobil', 'mobilOptions'));
    }

    public function store(StoreSewaMobilRequest $request)
    {
        $this->service->createDraft($request->validated(), $request->user());

        return redirect()
            ->route('sewa-mobil.karyawan.index')
            ->with('success', 'Draft pengajuan Sewa Mobil berhasil dibuat.');
    }

    public function edit(SewaMobil $sewaMobil)
    {
        $this->abortUnlessOwner($sewaMobil);

        $data = $sewaMobil->load(['aset.mobil', 'pembayaran.dompet']);
        $sewaMobil = SewaMobil::query()
            ->with(['aset.mobil', 'pembayaran.dompet', 'pengurusPenyetuju'])
            ->ownedByUser(auth()->id())
            ->latest()
            ->paginate(10);
        $mobilOptions = $this->mobilOptions();

        return view('pages.sewa-mobil.karyawan.index', compact('data', 'sewaMobil', 'mobilOptions'));
    }

    public function update(UpdateSewaMobilRequest $request, SewaMobil $sewaMobil)
    {
        $this->abortUnlessOwner($sewaMobil);

        $this->service->updateDraft($sewaMobil, $request->validated(), $request->user());

        return redirect()
            ->route('sewa-mobil.karyawan.index')
            ->with('success', 'Draft pengajuan Sewa Mobil berhasil diperbarui.');
    }

    public function submit(SewaMobil $sewaMobil)
    {
        $this->abortUnlessOwner($sewaMobil);

        $this->service->submit($sewaMobil, auth()->user());

        return redirect()
            ->route('sewa-mobil.karyawan.index')
            ->with('success', 'Pengajuan Sewa Mobil berhasil diajukan ke Finance.');
    }

    public function cancel(RejectSewaMobilRequest $request, SewaMobil $sewaMobil)
    {
        $this->abortUnlessOwner($sewaMobil);

        $this->service->cancelByEmployee($sewaMobil, $request->user(), $request->validated('alasan'));

        return redirect()
            ->route('sewa-mobil.karyawan.index')
            ->with('success', 'Pengajuan Sewa Mobil berhasil dibatalkan.');
    }

    private function mobilOptions()
    {
        return AsetKoperasi::query()
            ->mobil()
            ->whereNotIn('status', [AsetKoperasi::STATUS_NONAKTIF, AsetKoperasi::STATUS_PERAWATAN])
            ->where('harga_dasar_vendor', '>', 0)
            ->orderBy('kode_aset')
            ->get();
    }

    private function abortUnlessOwner(SewaMobil $sewaMobil): void
    {
        abort_unless((int) $sewaMobil->pemohon_user_id === (int) auth()->id(), 403);
    }
}
