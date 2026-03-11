<?php

namespace App\Services;

use App\Models\DetailPenjualan;
use App\Models\HutangReseller;
use App\Models\PembayaranKonsinyasi;
use App\Models\Reseller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PembayaranKonsinyasiService
{
    public function syncOutstandingFromSales(): void
    {
        DetailPenjualan::query()
            ->with('penjualan')
            ->where('konsinyasi', true)
            ->whereNotNull('reseller_id')
            ->doesntHave('hutangReseller')
            ->orderBy('id')
            ->chunkById(100, function ($details): void {
                foreach ($details as $detail) {
                    HutangReseller::create([
                        'reseller_id' => $detail->reseller_id,
                        'detail_penjualan_id' => $detail->id,
                        'jumlah' => (int) $detail->subtotal_setor,
                        'status' => 'belum_dibayar',
                        'tanggal' => optional($detail->penjualan?->created_at)->toDateString()
                            ?? optional($detail->created_at)->toDateString()
                            ?? now()->toDateString(),
                    ]);
                }
            });
    }

    public function getOutstandingSummary(int $resellerId): array
    {
        $hutang = HutangReseller::query()
            ->with('detailPenjualan')
            ->where('reseller_id', $resellerId)
            ->where('status', 'belum_dibayar')
            ->get();

        $totalQty = (int) $hutang->sum(fn (HutangReseller $item) => (int) ($item->detailPenjualan->qty ?? 0));
        $totalBayar = (int) round($hutang->sum('jumlah'));
        $totalJual = (int) $hutang->sum(fn (HutangReseller $item) => (int) ($item->detailPenjualan->subtotal ?? 0));

        return [
            'baris_hutang' => $hutang->count(),
            'total_qty' => $totalQty,
            'total_bayar' => $totalBayar,
            'total_jual' => $totalJual,
            'total_margin' => $totalJual - $totalBayar,
        ];
    }

    public function getProductSummaries(int $resellerId): Collection
    {
        return DB::table('produk as pr')
            ->leftJoin('detail_penjualan as dp', function ($join) use ($resellerId) {
                $join->on('pr.id', '=', 'dp.produk_id')
                    ->where('dp.konsinyasi', '=', 1)
                    ->where('dp.reseller_id', '=', $resellerId);
            })
            ->leftJoin('hutang_reseller as hr', 'hr.detail_penjualan_id', '=', 'dp.id')
            ->where('pr.konsinyasi', 1)
            ->where('pr.reseller_id', $resellerId)
            ->groupBy('pr.id', 'pr.nama_produk', 'pr.stok', 'pr.harga_jual', 'pr.harga_setor')
            ->orderBy('pr.nama_produk')
            ->selectRaw("
                pr.id,
                pr.nama_produk,
                pr.stok,
                pr.harga_jual,
                pr.harga_setor,
                COALESCE(SUM(dp.qty), 0) as total_laku,
                COALESCE(SUM(CASE WHEN hr.status = 'belum_dibayar' THEN dp.qty ELSE 0 END), 0) as qty_belum_dibayar,
                COALESCE(SUM(CASE WHEN hr.status = 'sudah_dibayar' THEN dp.qty ELSE 0 END), 0) as qty_sudah_dibayar,
                COALESCE(SUM(CASE WHEN hr.status = 'belum_dibayar' THEN hr.jumlah ELSE 0 END), 0) as total_bayar_belum_dibayar,
                COALESCE(SUM(CASE WHEN hr.status = 'belum_dibayar' THEN (dp.subtotal - dp.subtotal_setor) ELSE 0 END), 0) as total_margin_belum_dibayar
            ")
            ->get();
    }

    public function createPayment(array $validated, MutasiKasService $mutasiKasService): PembayaranKonsinyasi
    {
        $this->syncOutstandingFromSales();

        $reseller = Reseller::query()->findOrFail($validated['reseller_id']);

        $hutang = HutangReseller::query()
            ->with('detailPenjualan')
            ->where('reseller_id', $reseller->id)
            ->where('status', 'belum_dibayar')
            ->orderBy('id')
            ->get();

        if ($hutang->isEmpty()) {
            throw ValidationException::withMessages([
                'reseller_id' => 'Tidak ada tagihan konsinyasi yang belum dibayar untuk reseller tersebut.',
            ]);
        }

        $totalQty = (int) $hutang->sum(fn (HutangReseller $item) => (int) ($item->detailPenjualan->qty ?? 0));
        $totalBayar = (int) round($hutang->sum('jumlah'));
        $totalJual = (int) $hutang->sum(fn (HutangReseller $item) => (int) ($item->detailPenjualan->subtotal ?? 0));
        $totalMargin = $totalJual - $totalBayar;
        $kodePembayaran = $this->nextPaymentCode();

        return DB::transaction(function () use (
            $validated,
            $mutasiKasService,
            $reseller,
            $hutang,
            $totalQty,
            $totalBayar,
            $totalJual,
            $totalMargin,
            $kodePembayaran
        ): PembayaranKonsinyasi {
            $payment = PembayaranKonsinyasi::create([
                'kode_pembayaran' => $kodePembayaran,
                'reseller_id' => $reseller->id,
                'dompet_id' => $validated['dompet_id'],
                'tanggal_bayar' => $validated['tanggal_bayar'],
                'total_qty' => $totalQty,
                'total_jual' => $totalJual,
                'total_bayar' => $totalBayar,
                'total_margin' => $totalMargin,
                'keterangan' => $validated['keterangan'] ?? null,
            ]);

            HutangReseller::query()
                ->whereIn('id', $hutang->pluck('id'))
                ->update([
                    'status' => 'sudah_dibayar',
                    'tanggal_bayar' => $validated['tanggal_bayar'],
                    'pembayaran_konsinyasi_id' => $payment->id,
                    'updated_at' => now(),
                ]);

            $mutasiKasService->record([
                'dompet_id' => $validated['dompet_id'],
                'tipe' => 'keluar',
                'jumlah' => $totalBayar,
                'keterangan' => $validated['keterangan']
                    ?: 'Pembayaran konsinyasi ke reseller ' . $reseller->nama_reseller . ' (' . $kodePembayaran . ')',
                'referensi_tipe' => PembayaranKonsinyasi::class,
                'referensi_id' => $payment->id,
                'tanggal' => $validated['tanggal_bayar'],
            ]);

            return $payment;
        });
    }

    protected function nextPaymentCode(): string
    {
        $latest = PembayaranKonsinyasi::query()->latest('id')->first();

        if (! $latest) {
            return 'PKS-001';
        }

        $number = (int) substr((string) $latest->kode_pembayaran, 4);

        return 'PKS-' . str_pad((string) ($number + 1), 3, '0', STR_PAD_LEFT);
    }
}
