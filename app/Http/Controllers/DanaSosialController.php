<?php

namespace App\Http\Controllers;

use App\Models\DanaSosialSumber;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\KlaimDanaSosial;
use App\Models\ShuKoperasi;
use App\Services\DanaSosialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DanaSosialController extends Controller
{
    public function index(): View
    {
        return view('pages.klaim-dana-sosial.index', [
            'sources' => DanaSosialSumber::query()->with(['shuKoperasi', 'mutations'])->latest()->get(),
            'claims' => KlaimDanaSosial::query()->with(['karyawan', 'sumber', 'dompet', 'creator', 'approver', 'payer', 'mutasiDana', 'mutasiKas', 'jurnal.details'])->latest()->paginate(15),
            'employees' => Karyawan::query()->aktif()->orderBy('nama')->get(),
            'activeSources' => DanaSosialSumber::query()->where('status', DanaSosialSumber::STATUS_ACTIVE)->where('saldo_tersedia', '>', 0)->orderBy('kode_sumber')->get(),
            'closedShu' => ShuKoperasi::query()->where('status', 'closed')->where('nominal_dana_sosial', '>', 0)->orderByDesc('tanggal_selesai')->get(),
            'wallets' => DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get(),
        ]);
    }

    public function storeSource(Request $request, DanaSosialService $service): RedirectResponse
    {
        $data = $request->validate(['nama_sumber' => ['required','string','max:150'], 'jenis_sumber' => ['required', Rule::in([DanaSosialSumber::JENIS_SHU, DanaSosialSumber::JENIS_TAMBAHAN])], 'shu_koperasi_id' => ['nullable','exists:shu_koperasi,id'], 'nominal_awal' => ['required','integer','min:1'], 'keterangan' => ['nullable','string','max:1000']]);
        $service->createSource($data, $request->user()->id);
        return back()->with('success', 'Sumber Dana Sosial dibuat sebagai draft dan belum menambah saldo.');
    }

    public function approveSource(DanaSosialSumber $source, Request $request, DanaSosialService $service): RedirectResponse
    {
        $service->approveSource($source, $request->user()->id);
        return back()->with('success', 'Sumber disetujui; saldo, mutasi, dan jurnal alokasi sudah terbentuk.');
    }

    public function storeClaim(Request $request, DanaSosialService $service): RedirectResponse
    {
        $data = $request->validate(['karyawan_id' => ['required','exists:karyawan,id'], 'kategori' => ['required', Rule::in(KlaimDanaSosial::KATEGORI)], 'nominal' => ['required','integer','min:1'], 'tanggal_pengajuan' => ['required','date'], 'keterangan' => ['required','string','max:1000']]);
        $service->createClaim($data, $request->user()->id);
        return back()->with('success', 'Draft Klaim Dana Sosial berhasil dibuat.');
    }

    public function submit(KlaimDanaSosial $claim, DanaSosialService $service): RedirectResponse { $service->submit($claim); return back()->with('success', 'Klaim diajukan untuk approval.'); }

    public function approve(KlaimDanaSosial $claim, Request $request, DanaSosialService $service): RedirectResponse
    {
        $data = $request->validate(['sumber_dana_sosial_id' => ['required','exists:dana_sosial_sumber,id']]);
        $service->approve($claim, (int) $data['sumber_dana_sosial_id'], $request->user()->id);
        return back()->with('success', 'Klaim disetujui dan sumber dananya dicadangkan.');
    }

    public function reject(KlaimDanaSosial $claim, Request $request, DanaSosialService $service): RedirectResponse
    {
        $reason = $request->validate(['alasan_penolakan' => ['required','string','max:1000']])['alasan_penolakan'];
        $service->reject($claim, $reason, $request->user()->id);
        return back()->with('success', 'Klaim ditolak dengan alasan tercatat.');
    }

    public function pay(KlaimDanaSosial $claim, Request $request, DanaSosialService $service): RedirectResponse
    {
        $data = $request->validate(['dompet_id' => ['required','exists:dompet_koperasi,id'], 'metode_pembayaran' => ['required','in:tunai,transfer_bank'], 'tanggal_bayar' => ['required','date']]);
        $service->pay($claim, $data, $request->user()->id);
        return back()->with('success', 'Klaim dibayar; saldo sumber, Mutasi Kas, jurnal, dan audit telah diperbarui.');
    }
}
