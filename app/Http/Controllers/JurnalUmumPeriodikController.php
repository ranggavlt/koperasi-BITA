<?php

namespace App\Http\Controllers;

use App\Models\JurnalUmum;
use Carbon\Carbon;
use Illuminate\Http\Request;

class JurnalUmumPeriodikController extends Controller
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

        $jurnal = JurnalUmum::query()
            ->with('details')
            ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('pages.akuntansi.jurnal-umum', [
            'periode' => $periode,
            'mulai' => $mulai,
            'akhir' => $akhir,
            'jurnal' => $jurnal,
        ]);
    }
}

