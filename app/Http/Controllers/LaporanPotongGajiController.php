<?php

namespace App\Http\Controllers;

use App\Models\CicilanPinjaman;
use App\Models\Karyawan;
use App\Models\Penjualan;
use App\Models\Pinjaman;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LaporanPotongGajiController extends Controller
{
    public function index(Request $request)
    {
        $periode = $request->get('periode', now()->format('Y-m'));

        try {
            $mulai = Carbon::createFromFormat('Y-m', $periode)->startOfMonth();
        } catch (\Throwable $e) {
            $periode = now()->format('Y-m');
            $mulai = Carbon::createFromFormat('Y-m', $periode)->startOfMonth();
        }

        $akhir = $mulai->copy()->endOfMonth();

        $karyawan = Karyawan::orderBy('nama')->get();

        $penjualanPeriode = Penjualan::with('details.produk')
            ->whereBetween('created_at', [$mulai->copy()->startOfDay(), $akhir->copy()->endOfDay()])
            ->get()
            ->groupBy('karyawan_id');

        $pinjamanPeriode = Pinjaman::query()
            ->whereBetween('tanggal_pinjaman', [$mulai->toDateString(), $akhir->toDateString()])
            ->get()
            ->groupBy('karyawan_id');

        $pinjamanAktif = Pinjaman::query()
            ->where('sisa_pinjaman', '>', 0)
            ->get()
            ->groupBy('karyawan_id');

        $cicilanPeriode = CicilanPinjaman::with('pinjaman')
            ->whereNotNull('tanggal_bayar')
            ->whereBetween('tanggal_bayar', [$mulai->toDateString(), $akhir->toDateString()])
            ->get()
            ->filter(fn (CicilanPinjaman $cicilan) => $cicilan->pinjaman !== null)
            ->groupBy(fn (CicilanPinjaman $cicilan) => $cicilan->pinjaman->karyawan_id);

        $laporan = $karyawan
            ->map(function (Karyawan $item) use ($penjualanPeriode, $pinjamanPeriode, $pinjamanAktif, $cicilanPeriode) {
                $penjualan = $penjualanPeriode->get($item->id, collect());
                $pinjamanBaru = $pinjamanPeriode->get($item->id, collect());
                $pinjamanAktifKaryawan = $pinjamanAktif->get($item->id, collect());
                $cicilan = $cicilanPeriode->get($item->id, collect());

                $rincianBelanja = $this->buildRingkasanBelanja($penjualan);
                $totalBelanja = (float) $penjualan->sum('grand_total');
                $totalPinjamanBaru = (float) $pinjamanBaru->sum('jumlah_pinjaman');
                $totalCicilan = (float) $cicilan->sum('jumlah_cicilan');
                $sisaPinjamanAktif = (float) $pinjamanAktifKaryawan->sum('sisa_pinjaman');
                $totalPenggunaan = $totalBelanja + $totalPinjamanBaru;

                return (object) [
                    'karyawan' => $item,
                    'rincian_belanja' => $rincianBelanja,
                    'jumlah_transaksi' => $penjualan->count(),
                    'total_belanja' => $totalBelanja,
                    'total_pinjaman_baru' => $totalPinjamanBaru,
                    'total_cicilan' => $totalCicilan,
                    'sisa_pinjaman_aktif' => $sisaPinjamanAktif,
                    'sisa_limit_bulan' => max(0, 2000000 - $totalBelanja),
                    'total_penggunaan' => $totalPenggunaan,
                ];
            })
            ->filter(fn ($item) => $item->total_penggunaan > 0 || $item->sisa_pinjaman_aktif > 0 || $item->total_cicilan > 0)
            ->values();

        $summary = [
            'total_karyawan' => $laporan->count(),
            'total_belanja' => $laporan->sum('total_belanja'),
            'total_pinjaman_baru' => $laporan->sum('total_pinjaman_baru'),
            'total_penggunaan' => $laporan->sum('total_penggunaan'),
            'total_sisa_pinjaman' => $laporan->sum('sisa_pinjaman_aktif'),
        ];

        return view('pages.laporan.potong-gaji', [
            'periode' => $periode,
            'mulai' => $mulai,
            'akhir' => $akhir,
            'laporan' => $laporan,
            'summary' => $summary,
        ]);
    }

    private function buildRingkasanBelanja(Collection $penjualan): Collection
    {
        return $penjualan
            ->flatMap(fn (Penjualan $item) => $item->details)
            ->groupBy('produk_id')
            ->map(function (Collection $details) {
                $produk = optional($details->first()->produk)->nama_produk ?? 'Produk';

                return $produk . ' x' . $details->sum('qty');
            })
            ->values();
    }
}
