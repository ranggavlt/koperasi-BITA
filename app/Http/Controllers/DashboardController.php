<?php

namespace App\Http\Controllers;

use App\Models\Penjualan;
use App\Models\DetailPenjualan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
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
        // 5. AKTIVITAS TERAKHIR
        // =============================

        $transaksiTerakhir = Penjualan::with('karyawan')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(function ($trx) {
                $trx->grand_total = (int) ($trx->grand_total ?? 0);
                return $trx;
            });

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
            'produkTerlaris',
            'transaksiTerakhir'
        ));
    }
}
