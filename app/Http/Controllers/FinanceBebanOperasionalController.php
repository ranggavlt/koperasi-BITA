<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostBebanOperasionalRequest;
use App\Http\Requests\ReverseBebanOperasionalRequest;
use App\Http\Requests\StoreBebanOperasionalRequest;
use App\Http\Requests\UpdateBebanOperasionalRequest;
use App\Models\Akun;
use App\Models\BebanOperasional;
use App\Models\DompetKoperasi;
use App\Services\BebanOperasionalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceBebanOperasionalController extends Controller
{
    public function __construct(private readonly BebanOperasionalService $service)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(BebanOperasional::statuses())],
            'dompet_id' => ['nullable', 'integer', 'exists:dompet_koperasi,id'],
            'akun_id' => ['nullable', 'integer', 'exists:akun,id'],
            'tanggal_dari' => ['nullable', 'date'],
            'tanggal_sampai' => ['nullable', 'date'],
        ]);

        if ($request->filled('tanggal_dari') && $request->filled('tanggal_sampai')
            && $request->date('tanggal_sampai')->lt($request->date('tanggal_dari'))) {
            throw ValidationException::withMessages([
                'tanggal_sampai' => 'Tanggal sampai tidak boleh sebelum tanggal mulai.',
            ]);
        }

        $query = BebanOperasional::query()
            ->with([
                'details.akun',
                'dompet.akun',
                'creator',
                'postedBy',
                'reversedBy',
                'reversal',
                'mutasiKas',
                'jurnal.details',
            ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['dompet_id'])) {
            $query->where('dompet_id', $filters['dompet_id']);
        }

        if (! empty($filters['akun_id'])) {
            $query->whereHas('details', fn ($detail) => $detail->where('akun_id', $filters['akun_id']));
        }

        if (! empty($filters['tanggal_dari'])) {
            $query->whereDate('tanggal_beban', '>=', $filters['tanggal_dari']);
        }

        if (! empty($filters['tanggal_sampai'])) {
            $query->whereDate('tanggal_beban', '<=', $filters['tanggal_sampai']);
        }

        $bebanOperasional = $query
            ->latest('tanggal_beban')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.beban-operasional.index', $this->viewData([
            'bebanOperasional' => $bebanOperasional,
        ]));
    }

    public function create()
    {
        return view('pages.beban-operasional.form', $this->viewData([
            'editData' => null,
        ]));
    }

    public function store(StoreBebanOperasionalRequest $request)
    {
        $this->service->createDraft($request->validated(), $request->user()->id);

        return redirect()->route('beban-operasional.index')
            ->with('success', 'Draft Beban Operasional berhasil dibuat.');
    }

    public function edit(BebanOperasional $bebanOperasional)
    {
        abort_unless($bebanOperasional->status === BebanOperasional::STATUS_DRAFT, 404);

        return view('pages.beban-operasional.form', $this->viewData([
            'editData' => $bebanOperasional->load(['details.akun', 'dompet.akun']),
        ]));
    }

    public function update(UpdateBebanOperasionalRequest $request, BebanOperasional $bebanOperasional)
    {
        $this->service->updateDraft($bebanOperasional, $request->validated(), $request->user()->id);

        return redirect()->route('beban-operasional.index')
            ->with('success', 'Draft Beban Operasional berhasil diperbarui.');
    }

    public function post(PostBebanOperasionalRequest $request, BebanOperasional $bebanOperasional)
    {
        $validated = $request->validated();

        $this->service->post($bebanOperasional, isset($validated['dompet_id']) ? (int) $validated['dompet_id'] : null, $request->user()->id);

        return redirect()->route('beban-operasional.index')
            ->with('success', 'Beban Operasional berhasil diposting, saldo Dompet berkurang, Mutasi Kas dan Jurnal dibuat.');
    }

    public function cancelDraft(Request $request, BebanOperasional $bebanOperasional)
    {
        abort_unless($request->user()?->role === 'keuangan', 403);

        $this->service->cancelDraft($bebanOperasional, $request->user()->id);

        return redirect()->route('beban-operasional.index')
            ->with('success', 'Draft Beban Operasional berhasil dibatalkan.');
    }

    public function reverse(ReverseBebanOperasionalRequest $request, BebanOperasional $bebanOperasional)
    {
        $this->service->reverse($bebanOperasional, $request->validated('alasan'), $request->user()->id);

        return redirect()->route('beban-operasional.index')
            ->with('success', 'Reversal penuh Beban Operasional berhasil diproses.');
    }

    private function viewData(array $overrides): array
    {
        return $overrides + [
            'akunOptions' => Akun::query()
                ->aktif()
                ->where('kategori', 'beban')
                ->where('posisi_saldo', 'debit')
                ->where('is_beban_operasional', true)
                ->when(config('account_map.accounts.harga_pokok_penjualan.kode_akun'), fn ($query, $kode) => $query->where('kode_akun', '!=', $kode))
                ->orderBy('kode_akun')
                ->get(),
            'dompetOptions' => DompetKoperasi::query()
                ->with('akun')
                ->whereIn('jenis_dompet', [DompetKoperasi::JENIS_KAS, DompetKoperasi::JENIS_BANK])
                ->whereHas('akun', fn ($query) => $query
                    ->where('is_aktif', true)
                    ->where('kategori', 'aset')
                    ->where('posisi_saldo', 'debit'))
                ->orderBy('nama_dompet')
                ->get(),
            'statuses' => BebanOperasional::statusLabels(),
        ];
    }
}
