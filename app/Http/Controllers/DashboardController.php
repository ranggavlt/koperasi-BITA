<?php

namespace App\Http\Controllers;

use App\Models\Penjualan;
use App\Models\DetailPenjualan;
use App\Models\Pembayaran;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        if (auth()->user()->role === 'kasir') {
            return view('pages.dashboard-kasir', $this->kasirDashboardData());
        }

        // =============================
        // 1. Inisialisasi waktu
        // =============================
        $hariIni  = Carbon::today();
        $bulanIni = Carbon::now()->month;
        $tahunIni = Carbon::now()->year;

        // =============================
        // 2. DATA KARTU ATAS
        // =============================

        $pendapatanHariIni = Penjualan::whereDate('created_at', $hariIni)
            ->sum('grand_total') ?? 0;

        $transaksiHariIni = Penjualan::whereDate('created_at', $hariIni)
            ->count() ?? 0;
            
        $itemTerjualHariIni = DetailPenjualan::whereHas('penjualan', function ($q) use ($hariIni) {
            $q->whereDate('created_at', $hariIni);
        })->sum('qty') ?? 0;

        $rataRataTransaksi = $transaksiHariIni > 0 
            ? $pendapatanHariIni / $transaksiHariIni 
            : 0;

        $konsinyasiBulanIni = DetailPenjualan::where('konsinyasi', 1)
            ->whereMonth('created_at', $bulanIni)
            ->whereYear('created_at', $tahunIni)
            ->sum('qty') ?? 0;

        $pendapatanBulanIni = Penjualan::whereMonth('created_at', $bulanIni)
            ->whereYear('created_at', $tahunIni)
            ->sum('grand_total') ?? 0;

        // =============================
        // 3. GRAFIK PENDAPATAN BULANAN
        // =============================

        // Grafik pendapatan per bulan dari Database
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', created_at) AS INTEGER)"
            : 'MONTH(created_at)';

        $dataGrafik = DB::table('penjualan')
            ->selectRaw("{$monthExpression} as bulan, SUM(grand_total) as total")
            ->whereYear('created_at', date('Y'))
            ->groupBy('bulan')
            ->pluck('total', 'bulan')
            ->toArray();

        $grafikBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        $grafikPendapatan = [];

        for ($i = 1; $i <= 12; $i++) {
            $grafikPendapatan[] = (int) ($dataGrafik[$i] ?? 0);
        }

        // =============================
        // 4. PRODUK TERLARIS
        // =============================

        $produkTerlaris = DetailPenjualan::select(
                'produk_id',
                DB::raw('SUM(qty) as total_qty'),
                DB::raw('SUM(subtotal) as total_revenue')
            )
            ->whereMonth('created_at', $bulanIni)
            ->whereYear('created_at', $tahunIni)
            ->groupBy('produk_id')
            ->orderByDesc('total_qty')
            ->with('produk.kategori')
            ->limit(5)
            ->get();

        // =============================
        // RETURN VIEW
        // =============================

        return view('pages.dashboard', compact(
            'pendapatanHariIni',
            'transaksiHariIni',
            'konsinyasiBulanIni',
            'pendapatanBulanIni',
            'grafikBulan',
            'grafikPendapatan',
            'produkTerlaris'
        ));
    }

    private function kasirDashboardData(): array
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $tanggalHariIni = Carbon::now($timezone)->toDateString();

        $validPenjualanIds = $this->validPenjualanKasirHariIniQuery($tanggalHariIni)
            ->pluck('id');

        $transaksiHariIni = $validPenjualanIds->count();

        $pendapatanHariIni = (int) Penjualan::query()
            ->whereIn('id', $validPenjualanIds)
            ->sum('grand_total');

        $itemTerjualHariIni = (int) DetailPenjualan::query()
            ->whereIn('penjualan_id', $validPenjualanIds)
            ->sum('qty');

        $rataRataTransaksi = $transaksiHariIni > 0
            ? (int) round($pendapatanHariIni / $transaksiHariIni)
            : 0;

        $metodePembayaranHariIni = $this->metodePembayaranHariIni($validPenjualanIds);

        $produkTerlarisHariIni = DetailPenjualan::query()
            ->select(
                'produk_id',
                DB::raw('SUM(qty) as total_qty'),
                DB::raw('SUM(subtotal) as total_revenue')
            )
            ->whereIn('penjualan_id', $validPenjualanIds)
            ->with('produk')
            ->groupBy('produk_id')
            ->orderByDesc('total_qty')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        return [
            'tanggalDashboard' => Carbon::parse($tanggalHariIni, $timezone),
            'kasirName' => auth()->user()->karyawan?->nama ?? auth()->user()->name ?? auth()->user()->username,
            'transaksiHariIni' => $transaksiHariIni,
            'pendapatanHariIni' => $pendapatanHariIni,
            'itemTerjualHariIni' => $itemTerjualHariIni,
            'rataRataTransaksi' => $rataRataTransaksi,
            'metodePembayaranHariIni' => $metodePembayaranHariIni,
            'produkTerlarisHariIni' => $produkTerlarisHariIni,
        ];
    }

    private function validPenjualanKasirHariIniQuery(string $tanggalHariIni)
    {
        return Penjualan::query()
            ->whereDate('tanggal_transaksi', $tanggalHariIni)
            ->where(function ($query): void {
                $query
                    ->whereNull('status')
                    ->orWhereNotIn('status', [
                        Penjualan::STATUS_CANCELLED,
                        Penjualan::STATUS_REVERSED,
                        Penjualan::STATUS_REFUNDED,
                    ]);
            })
            ->whereNull('reversal_transaksi_id')
            ->whereNull('reversed_at');
    }

    private function metodePembayaranHariIni($validPenjualanIds)
    {
        $paymentRows = Pembayaran::query()
            ->select('metode_pembayaran')
            ->selectRaw('COUNT(DISTINCT penjualan_id) as total_transaksi')
            ->selectRaw('SUM(jumlah_bayar) as total_nominal')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_payroll', [Pembayaran::STATUS_PENDING_PAYROLL])
            ->whereIn('penjualan_id', $validPenjualanIds)
            ->groupBy('metode_pembayaran')
            ->get()
            ->keyBy('metode_pembayaran');

        return collect([
            Pembayaran::METODE_TUNAI => [
                'label' => 'Tunai',
                'hint' => 'Diterima langsung',
                'accent' => 'green',
            ],
            Pembayaran::METODE_POTONG_GAJI => [
                'label' => 'Potong Gaji',
                'hint' => 'Menunggu payroll bila belum confirmed',
                'accent' => 'gold',
            ],
            Pembayaran::METODE_TRANSFER_BANK => [
                'label' => 'Transfer Bank',
                'hint' => 'Masuk rekening koperasi',
                'accent' => 'navy',
            ],
            Pembayaran::METODE_QRIS => [
                'label' => 'QRIS',
                'hint' => 'Pembayaran digital',
                'accent' => 'green',
            ],
        ])->map(function (array $meta, string $metode) use ($paymentRows): array {
            $row = $paymentRows->get($metode);
            $pendingPayroll = (int) ($row->pending_payroll ?? 0);

            return [
                'metode' => $metode,
                'label' => $meta['label'],
                'hint' => $pendingPayroll > 0
                    ? $pendingPayroll . ' transaksi menunggu payroll'
                    : $meta['hint'],
                'accent' => $meta['accent'],
                'total_transaksi' => (int) ($row->total_transaksi ?? 0),
                'total_nominal' => (int) round((float) ($row->total_nominal ?? 0)),
                'pending_payroll' => $pendingPayroll,
            ];
        })->values();
    }
}
