<?php

namespace App\Http\Controllers;

use App\Models\Penjualan;
use App\Models\DetailPenjualan;
use App\Models\Karyawan;
use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PenjualanController extends Controller
{
    public function index()
    {
        $penjualan = Penjualan::with(['karyawan', 'details.produk'])->orderBy('id', 'desc')->paginate(10);
        $produk = Produk::where('stok', '>', 0)->orderBy('nama_produk')->get();

        $bulanIni = Carbon::now()->month;
        $tahunIni = Carbon::now()->year;

        // Hitung SISA SALDO masing-masing karyawan untuk bulan ini
        $karyawan = Karyawan::orderBy('nama')->get()->map(function ($k) use ($bulanIni, $tahunIni) {
            $pengeluaran = Penjualan::where('karyawan_id', $k->id)
                ->whereMonth('created_at', $bulanIni)
                ->whereYear('created_at', $tahunIni)
                ->sum('grand_total');

            $k->sisa_limit = 2000000 - $pengeluaran;
            return $k;
        });

        return view('pages.penjualan.index', compact('penjualan', 'karyawan', 'produk'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'karyawan_id' => 'required|exists:karyawan,id',
            'produk_id'   => 'required|exists:produk,id',
            'jumlah'      => 'required|integer|min:1',
            'diskon'      => 'nullable|numeric|min:0',
        ]);

        $produk = Produk::findOrFail($request->produk_id);
        
        if($request->jumlah > $produk->stok) {
            return back()->withErrors(['Stok kurang! Sisa: ' . $produk->stok])->withInput();
        }

        $total_harga = $produk->harga_jual * $request->jumlah;
        $diskon = $request->diskon ?? 0;
        $grand_total = $total_harga - $diskon;

        // CEK LIMIT SALDO
        $bulanIni = Carbon::now()->month;
        $tahunIni = Carbon::now()->year;

        $pengeluaran = Penjualan::where('karyawan_id', $request->karyawan_id)
            ->whereMonth('created_at', $bulanIni)
            ->whereYear('created_at', $tahunIni)
            ->sum('grand_total');

        if (($pengeluaran + $grand_total) > 2000000) {
            return back()->withErrors([
                'Ditolak! Saldo kasbon karyawan ini tidak mencukupi.',
                'Sisa saldo: Rp ' . number_format(2000000 - $pengeluaran, 0, ',', '.')
            ])->withInput();
        }

        // GENERATE KODE
        $latest = Penjualan::orderBy('id', 'desc')->first();
        if (!$latest) {
            $kode_transaksi = 'PJL-001';
        } else {
            $parts = explode('-', $latest->kode_transaksi);
            $kode_transaksi = 'PJL-' . str_pad((int)end($parts) + 1, 3, '0', STR_PAD_LEFT);
        }

        // SIMPAN KE 2 TABEL
        DB::beginTransaction();
        try {
            $penjualan = Penjualan::create([
                'kode_transaksi' => $kode_transaksi,
                'karyawan_id'    => $request->karyawan_id,
                'total_harga'    => $total_harga,
                'diskon'         => $diskon,
                'grand_total'    => $grand_total,
            ]);

            DetailPenjualan::create([
                'penjualan_id'   => $penjualan->id,
                'produk_id'      => $produk->id,
                'qty'            => $request->jumlah,
                'harga'          => $produk->harga_jual,
                'subtotal'       => $total_harga,
                'konsinyasi'     => $produk->konsinyasi,
                'reseller_id'    => $produk->reseller_id,
                'harga_setor'    => $produk->harga_setor,
                'subtotal_setor' => $produk->harga_setor * $request->jumlah,
            ]);

            $produk->decrement('stok', $request->jumlah);

            DB::commit();

            return redirect()->route('penjualan.index')
                ->with('success', 'Transaksi Sukses! Sisa Saldo: Rp ' . number_format(2000000 - ($pengeluaran + $grand_total), 0, ',', '.'));
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['Error: ' . $e->getMessage()])->withInput();
        }
    }

    public function destroy(Penjualan $penjualan)
    {
        DB::beginTransaction();
        try {
            foreach($penjualan->details as $detail) {
                $produk = Produk::find($detail->produk_id);
                if($produk) $produk->increment('stok', $detail->qty);
            }
            $penjualan->delete();
            DB::commit();
            return redirect()->route('penjualan.index')->with('success', 'Transaksi dibatalkan. Stok dikembalikan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['Gagal hapus: ' . $e->getMessage()]);
        }
    }
}