<?php

namespace App\Http\Controllers;

use App\Models\DompetKoperasi;
use App\Models\PembayaranKonsinyasi;
use App\Models\Reseller;
use App\Services\AkuntansiService;
use App\Services\MutasiKasService;
use App\Services\PembayaranKonsinyasiService;
use Illuminate\Http\Request;

class PembayaranKonsinyasiController extends Controller
{
    public function index(Request $request, PembayaranKonsinyasiService $pembayaranKonsinyasiService)
    {
        $pembayaranKonsinyasiService->syncOutstandingFromSales();

        $reseller = Reseller::query()
            ->where(function ($query) {
                $query->whereHas('produk', fn ($produk) => $produk->where('konsinyasi', true))
                    ->orWhereHas('hutangReseller');
            })
            ->orderBy('nama_reseller')
            ->get();

        $selectedResellerId = (int) $request->query('reseller_id', optional($reseller->first())->id);
        $selectedReseller = $reseller->firstWhere('id', $selectedResellerId);
        $dompet = DompetKoperasi::query()->orderBy('nama_dompet')->get();

        $ringkasan = [
            'baris_hutang' => 0,
            'total_qty' => 0,
            'total_bayar' => 0,
            'total_jual' => 0,
            'total_margin' => 0,
        ];
        $produkRingkasan = collect();

        if ($selectedReseller) {
            $ringkasan = $pembayaranKonsinyasiService->getOutstandingSummary($selectedReseller->id);
            $produkRingkasan = $pembayaranKonsinyasiService->getProductSummaries($selectedReseller->id);
        }

        $riwayatPembayaran = PembayaranKonsinyasi::query()
            ->with(['reseller', 'dompet'])
            ->when($selectedReseller, fn ($query) => $query->where('reseller_id', $selectedReseller->id))
            ->latest('tanggal_bayar')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.pembayaran-konsinyasi.index', [
            'dompet' => $dompet,
            'hasDompet' => $dompet->isNotEmpty(),
            'produkRingkasan' => $produkRingkasan,
            'reseller' => $reseller,
            'riwayatPembayaran' => $riwayatPembayaran,
            'ringkasan' => $ringkasan,
            'selectedReseller' => $selectedReseller,
            'selectedResellerId' => $selectedReseller?->id,
            'totalStokTersisa' => (int) $produkRingkasan->sum('stok'),
        ]);
    }

    public function store(
        Request $request,
        PembayaranKonsinyasiService $pembayaranKonsinyasiService,
        MutasiKasService $mutasiKasService,
        AkuntansiService $akuntansiService
    ) {
        $validated = $request->validate([
            'reseller_id' => 'required|exists:reseller,id',
            'dompet_id' => 'required|exists:dompet_koperasi,id',
            'tanggal_bayar' => 'required|date',
            'keterangan' => 'nullable|string|max:1000',
        ], [
            'reseller_id.required' => 'Reseller wajib dipilih.',
            'dompet_id.required' => 'Dompet pembayaran wajib dipilih.',
            'tanggal_bayar.required' => 'Tanggal pembayaran wajib diisi.',
        ]);

        $pembayaran = $pembayaranKonsinyasiService->createPayment($validated, $mutasiKasService);
        $akuntansiService->recordPembayaranKonsinyasi($pembayaran);

        return redirect()
            ->route('pembayaran-konsinyasi.index', ['reseller_id' => $validated['reseller_id']])
            ->with('success', 'Pembayaran konsinyasi ' . $pembayaran->kode_pembayaran . ' berhasil disimpan.');
    }
}
