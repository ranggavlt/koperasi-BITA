<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\DanaSosialSumber;
use App\Models\DompetKoperasi;
use App\Models\JenisManfaatDanaSosial;
use App\Models\KebijakanManfaatDanaSosial;
use App\Models\KlaimDanaSosial;
use App\Services\SocialFundService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DanaSosialController extends Controller
{
    public function __construct(private readonly SocialFundService $service) {}

    public function index()
    {
        $activeSources = DanaSosialSumber::query()->with(['shu.periode', 'config', 'allocationJournal', 'creator', 'approver'])
            ->where('jenis', DanaSosialSumber::JENIS_SHU)->where('is_legacy', false)->latest('tanggal')->get();
        $legacySources = DanaSosialSumber::query()->where('is_legacy', true)->latest('tanggal')->get();
        $reserved = (int) KlaimDanaSosial::query()->whereIn('status', [KlaimDanaSosial::STATUS_APPROVED, KlaimDanaSosial::STATUS_WAITING_FUNDS])->sum('nominal_disetujui');

        return view('pages.dana-sosial.index', [
            'sources' => $activeSources,
            'legacySources' => $legacySources,
            'claims' => KlaimDanaSosial::query()->with(['anggota.karyawan', 'kebijakan.jenisManfaat', 'creator', 'approver', 'dompet'])->latest()->paginate(15),
            'benefits' => JenisManfaatDanaSosial::query()->where('is_active', true)->orderBy('kode')->get(),
            'policies' => KebijakanManfaatDanaSosial::query()->with('jenisManfaat')->latest('berlaku_mulai')->get(),
            'members' => Anggota::query()->with('karyawan')->orderBy('nomor_anggota')->get(),
            'wallets' => DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get(),
            'socialSummary' => [
                'allocation' => (int) $activeSources->sum('jumlah'),
                'fund_balance' => (int) $activeSources->sum('saldo_tersedia'),
                'reserved' => $reserved,
                'available' => $this->service->availableBalance(),
                'paid' => (int) KlaimDanaSosial::query()->where('status', KlaimDanaSosial::STATUS_PAID)->sum('nominal_disetujui'),
            ],
        ]);
    }

    public function policy(Request $request)
    {
        $data = $request->validate([
            'jenis_manfaat_id' => ['required', 'exists:jenis_manfaat_dana_sosial,id'],
            'batas_maksimal' => ['required', 'integer', 'min:1'],
            'berlaku_mulai' => ['required', 'date'],
            'dasar_keputusan' => ['required', 'string', 'max:255'],
            'dokumen_diperlukan' => ['nullable', 'string', 'max:2000'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->service->saveBenefitPolicy($data, (int) $request->user()->id);
        return back()->with('success', 'Versi kebijakan manfaat berhasil disimpan.');
    }

    public function claim(Request $request)
    {
        $data = $request->validate([
            'anggota_id' => ['required', 'exists:anggota,id'],
            'penerima_manfaat' => ['required', 'string', 'max:150'],
            'hubungan_penerima' => ['required', 'string', 'max:150'],
            'jenis_manfaat_id' => ['required', 'exists:jenis_manfaat_dana_sosial,id'],
            'tanggal_kejadian' => ['required', 'date'],
            'nominal_diajukan' => ['required', 'integer', 'min:1'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        if ($request->hasFile('dokumen')) {
            $data['dokumen_path'] = $request->file('dokumen')->store('dana-sosial', 'public');
        }
        unset($data['dokumen']);
        $this->service->createClaim($data, (int) $request->user()->id);
        return back()->with('success', 'Klaim diajukan dan menunggu persetujuan Admin lain.');
    }

    public function approveClaim(Request $request, KlaimDanaSosial $klaim)
    {
        $data = $request->validate([
            'nominal_disetujui' => ['required', 'integer', 'min:1'],
            'catatan_persetujuan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $this->service->approveClaim($klaim, (int) $data['nominal_disetujui'], $data['catatan_persetujuan'], (int) $request->user()->id);
        return back()->with('success', 'Klaim Dana Sosial disetujui dan dananya telah direservasi.');
    }

    public function rejectClaim(Request $request, KlaimDanaSosial $klaim)
    {
        $data = $request->validate(['alasan_penolakan' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->service->rejectClaim($klaim, $data['alasan_penolakan'], (int) $request->user()->id);
        return back()->with('success', 'Klaim Dana Sosial ditolak.');
    }

    public function payClaim(Request $request, KlaimDanaSosial $klaim)
    {
        $data = $request->validate([
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
            'metode_pembayaran' => ['required', Rule::in(['tunai', 'transfer_bank'])],
            'tanggal_bayar' => ['required', 'date'],
            'nomor_referensi' => ['nullable', 'string', 'max:120'],
            'catatan_pencairan' => ['nullable', 'string', 'max:1000'],
        ]);
        $paid = $this->service->payClaim($klaim, $data, (int) $request->user()->id);
        if ($paid->status === KlaimDanaSosial::STATUS_WAITING_FUNDS) {
            return back()->with('warning', 'Saldo Kas/Bank belum cukup. Klaim dipindahkan ke status Menunggu Dana tanpa mengurangi saldo Dana Sosial.');
        }
        return back()->with('success', 'Klaim Dana Sosial berhasil dibayar penuh.');
    }

    public function reverseClaim(Request $request, KlaimDanaSosial $klaim)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'reversal_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $this->service->reversePayment($klaim, $data['tanggal'], $data['reversal_reason'], (int) $request->user()->id);
        return back()->with('success', 'Pencairan klaim direversal melalui saldo sumber, mutasi Kas/Bank, dan jurnal lawan.');
    }
}
