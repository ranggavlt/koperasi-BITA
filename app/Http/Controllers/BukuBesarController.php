<?php

namespace App\Http\Controllers;

use App\Models\JurnalUmumDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BukuBesarController extends Controller
{
    public function index(Request $request)
    {
        $periode = $request->get('periode', now()->format('Y-m'));
        $akun = (string) $request->get('akun', '');

        try {
            $mulai = Carbon::createFromFormat('Y-m', $periode)->startOfMonth();
        } catch (\Throwable $e) {
            $periode = now()->format('Y-m');
            $mulai = Carbon::createFromFormat('Y-m', $periode)->startOfMonth();
        }

        $akhir = $mulai->copy()->endOfMonth();

        $akunList = JurnalUmumDetail::query()
            ->select('akun_kode', 'akun_nama')
            ->distinct()
            ->orderBy('akun_kode')
            ->get();

        if ($akun === '' && $akunList->isNotEmpty()) {
            $akun = (string) $akunList->first()->akun_kode;
        }

        $lines = collect();
        $saldoAkhir = 0.0;
        $totalDebit = 0.0;
        $totalKredit = 0.0;

        if ($akun !== '') {
            $normalSide = $this->resolveNormalSide($akun);
            $raw = JurnalUmumDetail::query()
                ->with('jurnal')
                ->join('jurnal_umum as ju', 'ju.id', '=', 'jurnal_umum_detail.jurnal_umum_id')
                ->where('jurnal_umum_detail.akun_kode', $akun)
                ->whereBetween('ju.tanggal', [$mulai->toDateString(), $akhir->toDateString()])
                ->orderBy('ju.tanggal')
                ->orderBy('ju.id')
                ->orderBy('jurnal_umum_detail.id')
                ->select('jurnal_umum_detail.*')
                ->get();

            $lines = $this->buildRunningBalance($raw, $normalSide);

            $totalDebit = (float) $raw->sum(fn ($item) => (float) $item->debit);
            $totalKredit = (float) $raw->sum(fn ($item) => (float) $item->kredit);
            $saldoAkhir = (float) ($lines->last()->saldo ?? 0);
        }

        return view('pages.akuntansi.buku-besar', [
            'periode' => $periode,
            'mulai' => $mulai,
            'akhir' => $akhir,
            'akun' => $akun,
            'akunList' => $akunList,
            'lines' => $lines,
            'totalDebit' => $totalDebit,
            'totalKredit' => $totalKredit,
            'saldoAkhir' => $saldoAkhir,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\JurnalUmumDetail>  $raw
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function buildRunningBalance(Collection $raw, string $normalSide): Collection
    {
        $saldo = 0.0;

        return $raw->map(function ($detail) use (&$saldo, $normalSide) {
            $debit = (float) $detail->debit;
            $kredit = (float) $detail->kredit;

            // Saldo mengikuti "saldo normal" akun.
            // - Akun normal debit: saldo = debit - kredit
            // - Akun normal kredit: saldo = kredit - debit
            $delta = $normalSide === 'kredit'
                ? ($kredit - $debit)
                : ($debit - $kredit);

            $saldo += $delta;

            return (object) [
                'tanggal' => optional($detail->jurnal?->tanggal)->toDateString(),
                'nomor_bukti' => $detail->jurnal?->nomor_bukti,
                'keterangan' => $detail->jurnal?->keterangan,
                'debit' => $debit,
                'kredit' => $kredit,
                'saldo' => $saldo,
            ];
        });
    }

    private function resolveNormalSide(string $akunKode): string
    {
        $kode = trim($akunKode);
        $prefix = $kode !== '' ? (int) substr($kode, 0, 1) : 0;

        // Konvensi umum:
        // 1xxx Aset -> debit
        // 2xxx Kewajiban -> kredit
        // 3xxx Ekuitas -> kredit
        // 4xxx Pendapatan -> kredit
        // 5xxx Beban -> debit
        return match ($prefix) {
            2, 3, 4 => 'kredit',
            default => 'debit',
        };
    }
}
