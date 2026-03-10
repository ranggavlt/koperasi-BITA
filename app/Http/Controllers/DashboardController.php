<?php

namespace App\Http\Controllers;

use App\Models\Penjualan;
use App\Models\DetailPenjualan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Inisialisasi Waktu
        $hariIni = Carbon::today();
        $bulanIni = Carbon::now()->month;
        $tahunIni = Carbon::now()->year;

        // 2. Data 4 Kartu Atas (Gunakan null coalescing ?? 0 agar tidak error jika data kosong)
        $pendapatanHariIni = Penjualan::whereDate('created_at', $hariIni)->sum('grand_total') ?? 0;
        $transaksiHariIni  = Penjualan::whereDate('created_at', $hariIni)->count() ?? 0;
        
        // Barang konsinyasi yang laku bulan ini
        $konsinyasiBulanIni = DetailPenjualan::where('konsinyasi', 1)
                                ->whereMonth('created_at', $bulanIni)
                                ->whereYear('created_at', $tahunIni)
                                ->sum('qty') ?? 0;

        $pendapatanBulanIni = Penjualan::whereMonth('created_at', $bulanIni)
                                ->whereYear('created_at', $tahunIni)
                                ->sum('grand_total') ?? 0;

        // 3. Data Grafik Penjualan Bulanan (Tahun Ini)
        $grafikBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $grafikPendapatan = [];
        for ($i = 1; $i <= 12; $i++) {
            $totalBulan = Penjualan::whereMonth('created_at', $i)
                            ->whereYear('created_at', $tahunIni)
                            ->sum('grand_total');
            $grafikPendapatan[] = (int)$totalBulan;
        }

        // 4. Tabel Produk Terlaris Bulan Ini (Top 5)
        $produkTerlaris = DetailPenjualan::select('produk_id', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(subtotal) as total_revenue'))
                            ->whereMonth('created_at', $bulanIni)
                            ->whereYear('created_at', $tahunIni)
                            ->groupBy('produk_id')
                            ->orderByDesc('total_qty')
                            ->limit(5)
                            ->with('produk.kategori') 
                            ->get();

        // 5. Timeline Transaksi Terakhir (6 terbaru)
        $transaksiTerakhir = Penjualan::with('karyawan')
                                ->orderBy('created_at', 'desc')
                                ->limit(6)
                                ->get();

        // Mengirim data ke view
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