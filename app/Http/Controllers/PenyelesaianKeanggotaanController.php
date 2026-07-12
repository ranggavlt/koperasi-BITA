<?php

namespace App\Http\Controllers;

use App\Models\DompetKoperasi;
use App\Models\PenyelesaianKeanggotaan;
use App\Services\KeanggotaanLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PenyelesaianKeanggotaanController extends Controller
{
    public function __construct(private readonly KeanggotaanLifecycleService $service)
    {
    }

    public function index(Request $request): View
    {
        $query = PenyelesaianKeanggotaan::query()
            ->with(['anggota.karyawan', 'siklus', 'details.source', 'dompetRefund'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('anggota')) {
            $keyword = trim((string) $request->input('anggota'));
            $query->whereHas('anggota', function ($anggotaQuery) use ($keyword): void {
                $anggotaQuery->where('nomor_anggota', 'like', "%{$keyword}%")
                    ->orWhereHas('karyawan', function ($karyawanQuery) use ($keyword): void {
                        $karyawanQuery->where('nama', 'like', "%{$keyword}%");
                    });
            });
        }

        return view('pages.penyelesaian-keanggotaan.index', [
            'penyelesaianList' => $query->paginate(15)->withQueryString(),
            'statuses' => PenyelesaianKeanggotaan::statuses(),
            'dompetOptions' => DompetKoperasi::query()
                ->with('akun')
                ->whereIn('jenis_dompet', [DompetKoperasi::JENIS_KAS, DompetKoperasi::JENIS_BANK])
                ->orderBy('nama_dompet')
                ->get(),
            'filters' => $request->only(['status', 'anggota']),
        ]);
    }

    public function refresh(PenyelesaianKeanggotaan $penyelesaian): RedirectResponse
    {
        $this->service->refreshSnapshot($penyelesaian);

        return back()->with('success', 'Snapshot penyelesaian berhasil diperbarui.');
    }

    public function processOffset(PenyelesaianKeanggotaan $penyelesaian): RedirectResponse
    {
        $this->service->processOffset($penyelesaian, (int) auth()->id());

        return back()->with('success', 'Offset hak Anggota terhadap kewajiban berhasil diproses.');
    }

    public function refund(Request $request, PenyelesaianKeanggotaan $penyelesaian): RedirectResponse
    {
        $validated = $request->validate([
            'dompet_id' => ['required', 'integer', 'exists:dompet_koperasi,id'],
            'alasan' => ['required', 'string', 'min:5'],
        ]);

        $dompet = DompetKoperasi::query()->findOrFail($validated['dompet_id']);
        $this->service->processRefund($penyelesaian, $dompet, (int) auth()->id());

        return back()->with('success', 'Refund penyelesaian berhasil diproses.');
    }

    public function complete(PenyelesaianKeanggotaan $penyelesaian): RedirectResponse
    {
        $this->service->complete($penyelesaian, (int) auth()->id());

        return back()->with('success', 'Penyelesaian keanggotaan selesai dan immutable.');
    }
}
