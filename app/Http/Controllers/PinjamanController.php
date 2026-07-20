<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorepinjamanRequest;
use App\Http\Requests\UpdatepinjamanRequest;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\Pinjaman;
use App\Services\PinjamanKoperasiService;
use App\Services\PotongGajiBulananService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PinjamanController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Pinjaman::statusLabels()))],
            'anggota_id' => ['nullable', 'exists:anggota,id'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        $pinjaman = Pinjaman::with(['anggota.karyawan', 'karyawan', 'dompet', 'jadwalCicilan'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['anggota_id'] ?? null, fn ($query, $anggotaId) => $query->where('anggota_id', $anggotaId))
            ->when($validated['tanggal_mulai'] ?? null, fn ($query, $date) => $query->whereDate('tanggal_pengajuan', '>=', $date))
            ->when($validated['tanggal_selesai'] ?? null, fn ($query, $date) => $query->whereDate('tanggal_pengajuan', '<=', $date))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $anggotaFilter = Anggota::query()
            ->with('karyawan')
            ->orderBy('nomor_anggota')
            ->get();

        return view('pages.pinjaman.index', [
            'pinjaman' => $pinjaman,
            'anggotaFilter' => $anggotaFilter,
            'statusOptions' => Pinjaman::statusLabels(),
            'filters' => $validated,
        ]);
    }

    public function create()
    {
        return view('pages.pinjaman.form', [
            'pinjaman' => null,
            'anggota' => $this->availableAnggota(),
        ]);
    }

    public function store(StorepinjamanRequest $request, PinjamanKoperasiService $service)
    {
        $pinjaman = $service->createDraft($request->validated(), $request->user()->id);

        return redirect()
            ->route('pinjaman.show', $pinjaman)
            ->with('success', 'Draft pengajuan Pinjaman berhasil dibuat.');
    }

    public function edit(Pinjaman $pinjaman)
    {
        if ($pinjaman->status !== Pinjaman::STATUS_DRAFT) {
            return redirect()
                ->route('pinjaman.show', $pinjaman)
                ->withErrors(['pinjaman' => 'Hanya draft Pinjaman yang dapat diedit.']);
        }

        return view('pages.pinjaman.form', [
            'pinjaman' => $pinjaman->load('anggota.karyawan'),
            'anggota' => $this->availableAnggota($pinjaman),
        ]);
    }

    public function update(UpdatepinjamanRequest $request, Pinjaman $pinjaman, PinjamanKoperasiService $service)
    {
        $pinjaman = $service->updateDraft($pinjaman, $request->validated(), $request->user()->id);

        return redirect()
            ->route('pinjaman.show', $pinjaman)
            ->with('success', 'Draft pengajuan Pinjaman berhasil diperbarui.');
    }

    public function show(Pinjaman $pinjaman, PinjamanKoperasiService $service)
    {
        $pinjaman->load([
            'anggota.karyawan',
            'dompet.akun',
            'jadwalCicilan.cicilanPembayaran.dompet',
            'cicilan.dompet',
            'mutasiKas',
            'jurnal.details',
        ]);

        $dompet = DompetKoperasi::query()
            ->with('akun')
            ->orderBy('nama_dompet')
            ->get();

        $dompetKas = DompetKoperasi::query()
            ->with('akun')
            ->kas()
            ->orderBy('nama_dompet')
            ->get();

        $preview = [];
        if (in_array($pinjaman->status, [Pinjaman::STATUS_DRAFT, Pinjaman::STATUS_DIAJUKAN, Pinjaman::STATUS_DISETUJUI], true)) {
            $preview = $service->buildJadwalPreview(
                (int) $pinjaman->jumlah_pinjaman,
                (int) $pinjaman->tenor_bulan,
                now(config('app.timezone'))->toDateString()
            );
        }

        return view('pages.pinjaman.show', compact('pinjaman', 'dompet', 'dompetKas', 'preview'));
    }

    public function submit(Request $request, Pinjaman $pinjaman, PinjamanKoperasiService $service)
    {
        $pinjaman = $service->submit($pinjaman, $request->user()->id);

        return redirect()
            ->route('pinjaman.show', $pinjaman)
            ->with('success', 'Pengajuan Pinjaman berhasil diajukan.');
    }

    public function approve(Request $request, Pinjaman $pinjaman, PinjamanKoperasiService $service)
    {
        $pinjaman = $service->approve($pinjaman, $request->user()->id);

        return redirect()
            ->route('pinjaman.show', $pinjaman)
            ->with('success', 'Pengajuan Pinjaman berhasil disetujui.');
    }

    public function reject(Request $request, Pinjaman $pinjaman, PinjamanKoperasiService $service)
    {
        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $pinjaman = $service->reject($pinjaman, $validated['alasan'], $request->user()->id);

        return redirect()
            ->route('pinjaman.show', $pinjaman)
            ->with('success', 'Pengajuan Pinjaman berhasil ditolak.');
    }

    public function cancel(Request $request, Pinjaman $pinjaman, PinjamanKoperasiService $service)
    {
        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $pinjaman = $service->cancel($pinjaman, $validated['alasan'], $request->user()->id);

        return redirect()
            ->route('pinjaman.show', $pinjaman)
            ->with('success', 'Pengajuan Pinjaman berhasil dibatalkan.');
    }

    public function disburse(Request $request, Pinjaman $pinjaman, PinjamanKoperasiService $service)
    {
        $validated = $request->validate([
            'tanggal_pencairan' => ['required', 'date'],
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
        ]);

        $pinjaman = $service->disburse($pinjaman, $validated, $request->user()->id);

        return redirect()
            ->route('pinjaman.show', $pinjaman)
            ->with('success', 'Pinjaman berhasil dicairkan dan jadwal cicilan otomatis dibuat.');
    }

    public function payCashSchedule(Request $request, Pinjaman $pinjaman, PotongGajiBulananService $service)
    {
        $validated = $request->validate([
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
        ]);

        $service->payScheduledCash($pinjaman, DompetKoperasi::findOrFail($validated['dompet_id']), $request->user()->id);

        return redirect()
            ->route('pinjaman.show', $pinjaman)
            ->with('success', 'Cicilan terjadwal tunai berhasil dibayar.');
    }

    public function payCashFull(Request $request, Pinjaman $pinjaman, PotongGajiBulananService $service)
    {
        $validated = $request->validate([
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
        ]);

        $service->payFullCash($pinjaman, DompetKoperasi::findOrFail($validated['dompet_id']), $request->user()->id);

        return redirect()
            ->route('pinjaman.show', $pinjaman)
            ->with('success', 'Seluruh sisa Pinjaman berhasil dilunasi tunai.');
    }

    private function availableAnggota(?Pinjaman $pinjaman = null)
    {
        return Anggota::query()
            ->with('karyawan')
            ->aktif()
            ->whereHas('karyawan', fn ($query) => $query->aktif())
            ->where(function ($query) use ($pinjaman): void {
                $query->whereDoesntHave('pinjaman', fn ($loan) => $loan->whereIn('status', Pinjaman::openStatuses()));

                if ($pinjaman) {
                    $query->orWhereKey($pinjaman->anggota_id);
                }
            })
            ->orderBy('nomor_anggota')
            ->get();
    }
}
