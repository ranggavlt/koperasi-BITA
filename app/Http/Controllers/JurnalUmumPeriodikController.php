<?php

namespace App\Http\Controllers;

use App\Models\JurnalUmum;
use Carbon\Carbon;
use Illuminate\Http\Request;

class JurnalUmumPeriodikController extends Controller
{
    public function index(Request $request)
    {
        $mulai = now()->startOfMonth();
        $akhir = now()->endOfMonth();

        if ($request->filled('tanggal_mulai') || $request->filled('tanggal_akhir')) {
            try {
                $tanggalMulai = (string) $request->input('tanggal_mulai');
                $tanggalAkhir = (string) $request->input('tanggal_akhir');

                $rentangMulai = Carbon::createFromFormat('!Y-m-d', $tanggalMulai);
                $rentangAkhir = Carbon::createFromFormat('!Y-m-d', $tanggalAkhir);

                if (
                    $rentangMulai->format('Y-m-d') !== $tanggalMulai
                    || $rentangAkhir->format('Y-m-d') !== $tanggalAkhir
                    || $rentangMulai->gt($rentangAkhir)
                ) {
                    throw new \InvalidArgumentException('Rentang tanggal tidak valid.');
                }

                $mulai = $rentangMulai->startOfDay();
                $akhir = $rentangAkhir->endOfDay();
            } catch (\Throwable $e) {
                $mulai = now()->startOfMonth();
                $akhir = now()->endOfMonth();
            }
        }

        $jurnal = JurnalUmum::query()
            ->with('details')
            ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('pages.akuntansi.jurnal-umum', [
            'mulai' => $mulai,
            'akhir' => $akhir,
            'jurnal' => $jurnal,
        ]);
    }
}
