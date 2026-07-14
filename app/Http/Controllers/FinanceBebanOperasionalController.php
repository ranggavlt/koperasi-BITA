<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostBebanOperasionalRequest;
use App\Http\Requests\ReverseBebanOperasionalRequest;
use App\Http\Requests\StoreBebanOperasionalRequest;
use App\Http\Requests\UpdateBebanOperasionalRequest;
use App\Models\Akun;
use App\Models\AsetKoperasi;
use App\Models\BebanOperasional;
use App\Models\DompetKoperasi;
use App\Services\BebanOperasionalService;
use Illuminate\Http\Request;

class FinanceBebanOperasionalController extends Controller
{
    public function __construct(private readonly BebanOperasionalService $service)
    {
    }

    public function index(Request $request)
    {
        $query = BebanOperasional::query()
            ->with([
                'details.akun',
                'details.aset',
                'dompet.akun',
                'creator',
                'postedBy',
                'reversedBy',
                'reversal',
                'mutasiKas',
                'jurnal.details',
            ]);

        if ($request->filled('status') && in_array($request->string('status')->toString(), BebanOperasional::statuses(), true)) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('dompet_id')) {
            $query->where('dompet_id', $request->integer('dompet_id'));
        }

        if ($request->filled('akun_id')) {
            $query->whereHas('details', fn ($detail) => $detail->where('akun_id', $request->integer('akun_id')));
        }

        if ($request->filled('aset_koperasi_id')) {
            $query->whereHas('details', fn ($detail) => $detail->where('aset_koperasi_id', $request->integer('aset_koperasi_id')));
        }

        if ($request->filled('tanggal_dari')) {
            $query->whereDate('tanggal_beban', '>=', $request->date('tanggal_dari')->toDateString());
        }

        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('tanggal_beban', '<=', $request->date('tanggal_sampai')->toDateString());
        }

        $bebanOperasional = $query
            ->latest('tanggal_beban')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.beban-operasional.index', $this->viewData([
            'bebanOperasional' => $bebanOperasional,
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

        $bebanOperasionalList = BebanOperasional::query()
            ->with(['details.akun', 'details.aset', 'dompet.akun', 'creator', 'postedBy', 'reversedBy', 'reversal', 'mutasiKas', 'jurnal.details'])
            ->latest('tanggal_beban')
            ->latest('id')
            ->paginate(10);

        return view('pages.beban-operasional.index', $this->viewData([
            'bebanOperasional' => $bebanOperasionalList,
            'editData' => $bebanOperasional->load('details'),
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
        $this->service->post($bebanOperasional, (int) $request->validated('dompet_id'), $request->user()->id);

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
                ->orderBy('kode_akun')
                ->get(),
            'asetOptions' => AsetKoperasi::query()
                ->with(['mobil', 'printer'])
                ->orderBy('kode_aset')
                ->get(),
            'dompetOptions' => DompetKoperasi::query()
                ->with('akun')
                ->whereIn('jenis_dompet', [DompetKoperasi::JENIS_KAS, DompetKoperasi::JENIS_BANK])
                ->orderBy('nama_dompet')
                ->get(),
            'statuses' => BebanOperasional::statusLabels(),
        ];
    }
}
