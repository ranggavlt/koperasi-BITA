<?php

namespace App\Http\Controllers;

use App\Models\Akun;
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

        $akunList = Akun::query()
            ->aktif()
            ->orderBy('kode_akun')
            ->get();

        if ($akun === '' && $akunList->isNotEmpty()) {
            $akun = (string) $akunList->first()->kode_akun;
        }

        $lines = collect();
        $saldoAwal = 0.0;
        $saldoAkhir = 0.0;
        $totalDebit = 0.0;
        $totalKredit = 0.0;
        $akunModel = null;

        if ($akun !== '') {
            $akunModel = Akun::query()
                ->aktif()
                ->where('kode_akun', $akun)
                ->first();

            if (! $akunModel) {
                $akunModel = $akunList->first();
                $akun = (string) ($akunModel?->kode_akun ?? '');
            }

            $normalSide = (string) ($akunModel?->posisi_saldo ?? 'debit');

            $saldoSebelumPeriode = JurnalUmumDetail::query()
                ->join('jurnal_umum as ju', 'ju.id', '=', 'jurnal_umum_detail.jurnal_umum_id')
                ->where('jurnal_umum_detail.akun_kode', $akun)
                ->where('ju.tanggal', '<', $mulai->toDateString())
                ->selectRaw('COALESCE(SUM(jurnal_umum_detail.debit), 0) as total_debit')
                ->selectRaw('COALESCE(SUM(jurnal_umum_detail.kredit), 0) as total_kredit')
                ->first();

            $debitSebelum = (float) ($saldoSebelumPeriode->total_debit ?? 0);
            $kreditSebelum = (float) ($saldoSebelumPeriode->total_kredit ?? 0);
            $saldoAwal = $normalSide === 'kredit'
                ? $kreditSebelum - $debitSebelum
                : $debitSebelum - $kreditSebelum;

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

            $lines = $this->buildRunningBalance($raw, $normalSide, $saldoAwal);

            $totalDebit = (float) $raw->sum(fn ($item) => (float) $item->debit);
            $totalKredit = (float) $raw->sum(fn ($item) => (float) $item->kredit);
            $saldoAkhir = $lines->isNotEmpty()
                ? (float) $lines->last()->saldo
                : $saldoAwal;
        }

        return view('pages.akuntansi.buku-besar', [
            'periode' => $periode,
            'mulai' => $mulai,
            'akhir' => $akhir,
            'akun' => $akun,
            'akunModel' => $akunModel,
            'akunList' => $akunList,
            'lines' => $lines,
            'saldoAwal' => $saldoAwal,
            'totalDebit' => $totalDebit,
            'totalKredit' => $totalKredit,
            'saldoAkhir' => $saldoAkhir,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\JurnalUmumDetail>  $raw
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function buildRunningBalance(Collection $raw, string $normalSide, float $saldoAwal = 0): Collection
    {
        $saldo = $saldoAwal;

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

}
