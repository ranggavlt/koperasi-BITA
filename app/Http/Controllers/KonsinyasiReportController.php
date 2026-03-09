<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Reseller;

class KonsinyasiReportController extends Controller
{
    public function index(Request $request)
    {
        $mulai = $request->get('mulai', now()->startOfMonth()->toDateString());
        $akhir = $request->get('akhir', now()->toDateString());
        $resellerId = $request->get('reseller_id');

        $reseller = Reseller::orderBy('nama_reseller')->get();

        $q = DB::table('detail_penjualan as dp')
            ->join('penjualan as p', 'p.id', '=', 'dp.penjualan_id')
            ->leftJoin('reseller as r', 'r.id', '=', 'dp.reseller_id')
            ->whereBetween('p.created_at', [$mulai.' 00:00:00', $akhir.' 23:59:59'])
            ->where('dp.konsinyasi', 1);

        if ($resellerId) {
            $q->where('dp.reseller_id', $resellerId);
        }

        $rekap = $q->selectRaw('
                dp.reseller_id,
                COALESCE(r.nama_reseller, "Tanpa Reseller") as nama_reseller,
                SUM(dp.qty) as total_qty,
                SUM(dp.subtotal) as total_jual,
                SUM(dp.subtotal_setor) as total_setor,
                (SUM(dp.subtotal) - SUM(dp.subtotal_setor)) as laba_koperasi
            ')
            ->groupBy('dp.reseller_id', 'r.nama_reseller')
            ->orderBy('nama_reseller')
            ->get();

        return view('pages.konsinyasi.report', compact('mulai', 'akhir', 'resellerId', 'reseller', 'rekap'));
    }
}