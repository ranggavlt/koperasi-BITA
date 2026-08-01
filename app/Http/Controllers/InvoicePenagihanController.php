<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\InvoicePenagihan;
use App\Models\InvoicePenagihanDetail;
use App\Models\Perusahaan;
use App\Models\SewaMobil;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoicePenagihanController extends Controller
{
    public function index()
    {
        $invoices = InvoicePenagihan::with('perusahaan')->latest()->paginate(10);
        $perusahaan = Perusahaan::all();
        
        return view('pages.invoice-penagihan.index', compact('invoices', 'perusahaan'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'perusahaan_id' => 'required|exists:perusahaan,id',
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2020',
        ]);

        $perusahaan = Perusahaan::findOrFail($request->perusahaan_id);
        
        // Find sewa mobil that finished in this month
        $sewaMobil = SewaMobil::whereHas('karyawan', function($q) use ($perusahaan) {
                $q->where('perusahaan_id', $perusahaan->id);
            })
            ->whereIn('status', [SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN, SewaMobil::STATUS_SELESAI])
            ->whereMonth('created_at', $request->bulan)
            ->whereYear('created_at', $request->tahun)
            ->get();
            
        // Find sewa hardware that finished in this month
        $sewaHardware = \App\Models\SewaHardware::whereHas('karyawan', function($q) use ($perusahaan) {
                $q->where('perusahaan_id', $perusahaan->id);
            })
            ->whereIn('status', [\App\Models\SewaHardware::STATUS_DIKONFIRMASI, \App\Models\SewaHardware::STATUS_BERJALAN, \App\Models\SewaHardware::STATUS_SELESAI])
            ->whereMonth('created_at', $request->bulan)
            ->whereYear('created_at', $request->tahun)
            ->get();

        if ($sewaMobil->isEmpty() && $sewaHardware->isEmpty()) {
            return back()->withErrors(['message' => 'Tidak ada transaksi sewa untuk perusahaan ini pada periode tersebut.']);
        }

        DB::transaction(function() use ($perusahaan, $sewaMobil, $sewaHardware, $request) {
            $nomor = 'INV-' . $perusahaan->kode . '-' . $request->tahun . str_pad($request->bulan, 2, '0', STR_PAD_LEFT) . '-' . rand(100,999);
            
            $totalSewaMobil = $sewaMobil->sum('total_sewa');
            $totalSewaHardware = $sewaHardware->sum('total_tagihan_perusahaan');
            
            $invoice = InvoicePenagihan::create([
                'nomor_invoice' => $nomor,
                'perusahaan_id' => $perusahaan->id,
                'tanggal_invoice' => Carbon::now(),
                'jatuh_tempo' => Carbon::now()->addDays(14),
                'total_tagihan' => $totalSewaMobil + $totalSewaHardware,
                'status' => 'unpaid',
            ]);

            foreach ($sewaMobil as $sewa) {
                InvoicePenagihanDetail::create([
                    'invoice_penagihan_id' => $invoice->id,
                    'deskripsi' => 'Sewa Mobil ' . ($sewa->aset->kode_aset ?? '') . ' (' . $sewa->jumlah_hari . ' hari)',
                    'nominal' => $sewa->total_sewa,
                    'referensi_type' => SewaMobil::class,
                    'referensi_id' => $sewa->id,
                ]);
            }
            
            foreach ($sewaHardware as $sewa) {
                InvoicePenagihanDetail::create([
                    'invoice_penagihan_id' => $invoice->id,
                    'deskripsi' => 'Sewa Hardware (' . $sewa->kode_sewa . ')',
                    'nominal' => $sewa->total_tagihan_perusahaan,
                    'referensi_type' => \App\Models\SewaHardware::class,
                    'referensi_id' => $sewa->id,
                ]);
            }
        });

        return back()->with('success', 'Invoice penagihan berhasil digenerate.');
    }
}
