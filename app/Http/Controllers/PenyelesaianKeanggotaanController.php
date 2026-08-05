<?php

namespace App\Http\Controllers;

use App\Models\DompetKoperasi;
use App\Models\PenyelesaianKeanggotaan;
use App\Services\KeanggotaanLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PenyelesaianKeanggotaanController extends Controller
{
    public function __construct(private readonly KeanggotaanLifecycleService $service)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(PenyelesaianKeanggotaan::statuses())],
            'anggota' => ['nullable', 'string', 'max:120'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        $query = PenyelesaianKeanggotaan::query()
            ->with(['anggota.karyawan', 'siklus', 'details.source', 'dompetRefund'])
            ->latest('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['anggota'])) {
            $keyword = trim((string) $filters['anggota']);
            $query->whereHas('anggota', function ($anggotaQuery) use ($keyword): void {
                $anggotaQuery->where('nomor_anggota', 'like', "%{$keyword}%")
                    ->orWhereHas('karyawan', function ($karyawanQuery) use ($keyword): void {
                        $karyawanQuery->where('nama', 'like', "%{$keyword}%");
                    });
            });
        }

        if (! empty($filters['tanggal_mulai'])) {
            $query->whereDate('tanggal_keluar', '>=', $filters['tanggal_mulai']);
        }

        if (! empty($filters['tanggal_selesai'])) {
            $query->whereDate('tanggal_keluar', '<=', $filters['tanggal_selesai']);
        }

        $summaryRows = (clone $query)->get(['total_hak_anggota', 'total_kewajiban_awal', 'total_offset', 'total_refund']);

        return view('pages.penyelesaian-keanggotaan.index', [
            'penyelesaianList' => $query->paginate(15)->withQueryString(),
            'statuses' => PenyelesaianKeanggotaan::statuses(),
            'summary' => [
                'total_hak' => $summaryRows->sum(fn (PenyelesaianKeanggotaan $item): int => $this->decimalToRupiahInt($item->total_hak_anggota)),
                'total_kewajiban' => $summaryRows->sum(fn (PenyelesaianKeanggotaan $item): int => $this->decimalToRupiahInt($item->total_kewajiban_awal)),
                'total_offset' => $summaryRows->sum(fn (PenyelesaianKeanggotaan $item): int => $this->decimalToRupiahInt($item->total_offset)),
                'total_refund' => $summaryRows->sum(fn (PenyelesaianKeanggotaan $item): int => $this->decimalToRupiahInt($item->total_refund)),
            ],
            'dompetOptions' => DompetKoperasi::query()
                ->with('akun')
                ->whereIn('jenis_dompet', [DompetKoperasi::JENIS_KAS, DompetKoperasi::JENIS_BANK])
                ->orderBy('nama_dompet')
                ->get(),
            'filters' => $filters,
        ]);
    }

    public function show(PenyelesaianKeanggotaan $penyelesaian): View
    {
        $loaded = $penyelesaian->load(['anggota.karyawan', 'siklus', 'details.source', 'details.akun', 'dompetRefund', 'mutasiKas', 'jurnal.details', 'siklusDaftarUlang']);

        return view('pages.penyelesaian-keanggotaan.show', [
            'penyelesaian' => $loaded,
            'cancelDeactivationEligibility' => $this->service->deactivationCancellationEligibility($loaded),
            'reRegistrationEligibility' => $this->service->reRegistrationEligibility($loaded),
            'dompetOptions' => DompetKoperasi::query()
                ->with('akun')
                ->whereIn('jenis_dompet', [DompetKoperasi::JENIS_KAS, DompetKoperasi::JENIS_BANK])
                ->orderBy('nama_dompet')
                ->get(),
        ]);
    }

    public function refresh(PenyelesaianKeanggotaan $penyelesaian): RedirectResponse
    {
        $this->service->refreshSnapshot($penyelesaian);

        return back()->with('success', 'Perhitungan hak dan kewajiban berhasil diperbarui.');
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
            'metode_refund' => ['required', Rule::in([
                PenyelesaianKeanggotaan::METODE_TUNAI,
                PenyelesaianKeanggotaan::METODE_TRANSFER_BANK,
            ])],
            'alasan' => ['required', 'string', 'min:5'],
        ]);

        $dompet = DompetKoperasi::query()->findOrFail($validated['dompet_id']);
        $this->service->processRefund($penyelesaian, $dompet, (int) auth()->id(), $validated['metode_refund']);

        return back()->with('success', 'Refund penyelesaian berhasil diproses.');
    }

    public function complete(PenyelesaianKeanggotaan $penyelesaian): RedirectResponse
    {
        $this->service->complete($penyelesaian, (int) auth()->id());

        return back()->with('success', 'Penyelesaian keanggotaan selesai dan immutable.');
    }

    public function cancelDeactivation(Request $request, PenyelesaianKeanggotaan $penyelesaian): RedirectResponse
    {
        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $this->service->cancelDeactivation($penyelesaian, $validated['alasan'], (int) auth()->id());

        return back()->with('success', 'Penonaktifan dibatalkan. Siklus lama dipulihkan tanpa membuat Simpanan Wajib baru.');
    }

    public function reRegister(Request $request, PenyelesaianKeanggotaan $penyelesaian): RedirectResponse
    {
        $validated = $request->validate([
            'tanggal_bergabung' => ['required', 'date', 'before_or_equal:today'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
            'konfirmasi_siklus_baru' => ['accepted'],
            'simpanan_wajib_metode_pembayaran' => ['nullable', Rule::in(['potong_gaji', 'tunai', 'transfer_bank'])],
            'simpanan_wajib_dompet_id' => [
                'nullable',
                'required_if:simpanan_wajib_metode_pembayaran,tunai,transfer_bank',
                Rule::exists('dompet_koperasi', 'id')->where(function ($query) use ($request): void {
                    $method = $request->input('simpanan_wajib_metode_pembayaran', 'potong_gaji');

                    if ($method === 'tunai') {
                        $query->where('jenis_dompet', DompetKoperasi::JENIS_KAS);
                    } elseif ($method === 'transfer_bank') {
                        $query->where('jenis_dompet', DompetKoperasi::JENIS_BANK);
                    }
                }),
            ],
        ], [
            'konfirmasi_siklus_baru.accepted' => 'Konfirmasi bahwa pendaftaran kembali membuat siklus baru wajib dicentang.',
            'simpanan_wajib_dompet_id.required_if' => 'Dompet wajib dipilih untuk pembayaran Simpanan Wajib Tunai/Transfer Bank.',
            'simpanan_wajib_dompet_id.exists' => 'Dompet yang dipilih tidak sesuai dengan metode pembayaran Simpanan Wajib.',
        ]);

        $this->service->reRegisterMember(
            $penyelesaian,
            $validated['tanggal_bergabung'],
            $validated['alasan'],
            (int) auth()->id(),
            $validated['simpanan_wajib_metode_pembayaran'] ?? 'potong_gaji',
            $validated['simpanan_wajib_dompet_id'] ?? null
        );

        return back()->with('success', 'Anggota berhasil didaftarkan kembali dengan siklus baru, Simpanan Wajib baru, dan saldo Manasuka Rp0.');
    }

    private function decimalToRupiahInt(int|string|null $value): int
    {
        $normalized = trim((string) ($value ?? '0'));
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole] = array_pad(explode('.', $normalized, 2), 1, '0');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $rupiah = (int) $whole;

        return $negative ? -1 * $rupiah : $rupiah;
    }
}
