<?php

namespace App\Http\Controllers;

use App\Models\BatasKlaimDanaSosial;
use App\Models\DanaSosialSumber;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\KlaimDanaSosial;
use App\Services\DanaSosialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DanaSosialController extends Controller
{
    public function index(): View
    {
        return view('pages.klaim-dana-sosial.index', ['sources' => DanaSosialSumber::query()->with(['mutations', 'dompet', 'creator', 'approver', 'reverser'])->latest()->get(), 'claims' => KlaimDanaSosial::query()->with(['karyawan', 'sumber', 'dompet', 'creator', 'approver', 'payer', 'reverser', 'mutasiDana', 'mutasiKas', 'jurnal.details', 'batasKlaim'])->latest()->paginate(15), 'employees' => Karyawan::query()->aktif()->orderBy('nama')->get(), 'activeSources' => DanaSosialSumber::query()->where('status', DanaSosialSumber::STATUS_ACTIVE)->where('saldo_tersedia', '>', 0)->orderBy('kode_sumber')->get(), 'wallets' => DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get(), 'limits' => BatasKlaimDanaSosial::query()->with('creator')->latest('berlaku_mulai')->latest('id')->get()]);
    }

    public function storeLimit(Request $request, DanaSosialService $service): RedirectResponse
    {
        $data = $request->validate(['kategori' => ['required', Rule::in(KlaimDanaSosial::KATEGORI)], 'nominal_maksimal' => ['required', 'integer', 'min:1'], 'berlaku_mulai' => ['required', 'date'], 'alasan' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->createLimit($data, $request->user()->id); return back()->with('success', 'Versi batas klaim baru berhasil disimpan tanpa mengubah histori lama.');
    }

    public function storeSource(Request $request, DanaSosialService $service): RedirectResponse
    {
        $data = $request->validate(['nama_sumber' => ['required','string','max:150'], 'jenis_sumber' => ['required', Rule::in([DanaSosialSumber::JENIS_DONASI])], 'dompet_id' => ['required','exists:dompet_koperasi,id'], 'metode_penerimaan' => ['required','in:tunai,transfer_bank'], 'tanggal_diterima' => ['required','date'], 'nomor_referensi' => ['nullable','string','max:100'], 'bukti_penerimaan' => ['nullable','string','max:255'], 'nominal_awal' => ['required','integer','min:1'], 'keterangan' => ['required','string','max:1000']]);
        $service->createSource($data, $request->user()->id); return back()->with('success', 'Donasi dibuat sebagai draft; Dompet dan jurnal belum berubah sampai checker menyetujui.');
    }

    public function approveSource(DanaSosialSumber $source, Request $request, DanaSosialService $service): RedirectResponse
    {
        $reason = $request->validate(['approval_reason' => ['required','string','min:5','max:1000']])['approval_reason']; $service->approveSource($source, $request->user()->id, $reason); return back()->with('success', 'Donasi disetujui checker; penerimaan Kas/Bank dan jurnal COA 210 sudah terbentuk.');
    }

    public function reverseSource(DanaSosialSumber $source, Request $request, DanaSosialService $service): RedirectResponse
    {
        $reason = $request->validate(['reversal_reason' => ['required','string','min:5','max:1000']])['reversal_reason']; $service->reverseSource($source, $reason, $request->user()->id); return back()->with('success', 'Donasi direversal dengan counter-entry; transaksi asli tetap tersimpan.');
    }

    public function storeClaim(Request $request, DanaSosialService $service): RedirectResponse { $data = $request->validate(['karyawan_id' => ['required','exists:karyawan,id'], 'kategori' => ['required', Rule::in(KlaimDanaSosial::KATEGORI)], 'nominal' => ['required','integer','min:1'], 'tanggal_pengajuan' => ['required','date'], 'keterangan' => ['required','string','max:1000']]); $service->createClaim($data, $request->user()->id); return back()->with('success', 'Draft klaim dibuat dengan snapshot batas kategori yang berlaku.'); }
    public function submit(KlaimDanaSosial $claim, DanaSosialService $service): RedirectResponse { $service->submit($claim); return back()->with('success', 'Klaim diajukan untuk checker.'); }
    public function approve(KlaimDanaSosial $claim, Request $request, DanaSosialService $service): RedirectResponse { $data = $request->validate(['sumber_dana_sosial_id' => ['required','exists:dana_sosial_sumber,id'], 'approval_reason' => ['required','string','min:5','max:1000']]); $service->approve($claim, (int) $data['sumber_dana_sosial_id'], $request->user()->id, $data['approval_reason']); return back()->with('success', 'Klaim disetujui checker dan sumber dananya dicadangkan.'); }
    public function reject(KlaimDanaSosial $claim, Request $request, DanaSosialService $service): RedirectResponse { $reason = $request->validate(['alasan_penolakan' => ['required','string','max:1000']])['alasan_penolakan']; $service->reject($claim, $reason, $request->user()->id); return back()->with('success', 'Klaim ditolak dengan alasan tercatat.'); }
    public function pay(KlaimDanaSosial $claim, Request $request, DanaSosialService $service): RedirectResponse { $data = $request->validate(['dompet_id' => ['required','exists:dompet_koperasi,id'], 'metode_pembayaran' => ['required','in:tunai,transfer_bank'], 'tanggal_bayar' => ['required','date']]); $service->pay($claim, $data, $request->user()->id); return back()->with('success', 'Klaim dibayar tanpa membuat saldo Dana Sosial negatif.'); }
    public function reverse(KlaimDanaSosial $claim, Request $request, DanaSosialService $service): RedirectResponse { $reason = $request->validate(['reversal_reason' => ['required','string','min:5','max:1000']])['reversal_reason']; $service->reversePayment($claim, $reason, $request->user()->id); return back()->with('success', 'Pembayaran klaim direversal dengan counter-entry.'); }
}
