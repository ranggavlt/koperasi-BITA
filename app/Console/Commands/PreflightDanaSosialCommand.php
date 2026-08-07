<?php

namespace App\Console\Commands;

use App\Models\JenisManfaatDanaSosial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightDanaSosialCommand extends Command
{
    protected $signature = 'koperasi:preflight-dana-sosial';
    protected $description = 'Audit read-only sumber SHU, kebijakan, reservasi, klaim, payout, reversal, dan maker-checker Dana Sosial.';

    public function handle(): int
    {
        if (! Schema::hasTable('kebijakan_manfaat_dana_sosial')) { $this->error('Skema final Dana Sosial belum tersedia.'); return self::FAILURE; }
        $sourceBalance = (int) DB::table('dana_sosial_sumber')->where('jenis','alokasi_shu')->where('is_legacy',false)->where('status','approved')->sum('saldo_tersedia');
        $reserved = (int) DB::table('klaim_dana_sosial')->whereIn('status',['approved','waiting_funds'])->sum('nominal_disetujui');
        $checks = [
            ['sumber_aktif', 'Sumber final nonlegacy bukan alokasi SHU atau kehilangan tautan audit', DB::table('dana_sosial_sumber')->where('is_legacy',false)->where(fn($q) => $q->where('jenis','!=','alokasi_shu')->orWhereNull('shu_koperasi_id')->orWhereNull('periode_akuntansi_id')->orWhereNull('shu_config_id')->orWhereNull('allocation_journal_id'))->count()],
            ['saldo_sumber', 'Saldo sumber SHU negatif atau melebihi alokasi', DB::table('dana_sosial_sumber')->where('jenis','alokasi_shu')->where('is_legacy',false)->where(fn($q) => $q->where('saldo_tersedia','<',0)->orWhereColumn('saldo_tersedia','>','jumlah'))->count()],
            ['lima_manfaat', 'Master lima manfaat final belum lengkap', max(0, 5 - DB::table('jenis_manfaat_dana_sosial')->whereIn('kode', JenisManfaatDanaSosial::KODE)->distinct()->count('kode'))],
            ['kebijakan', 'Jenis manfaat aktif tanpa versi kebijakan', DB::table('jenis_manfaat_dana_sosial as j')->select('j.id')->selectRaw('COUNT(k.id) total_kebijakan')->leftJoin('kebijakan_manfaat_dana_sosial as k','k.jenis_manfaat_id','=','j.id')->whereIn('j.kode',JenisManfaatDanaSosial::KODE)->groupBy('j.id')->havingRaw('COUNT(k.id)=0')->get()->count()],
            ['maker_checker', 'Approver klaim sama dengan maker', DB::table('klaim_dana_sosial')->whereNotNull('approved_by')->whereColumn('approved_by','created_by')->count()],
            ['nominal_approval', 'Nominal approved invalid atau melampaui pengajuan/batas', DB::table('klaim_dana_sosial')->whereIn('status',['approved','waiting_funds','paid','corrected'])->where(fn($q) => $q->whereNull('nominal_disetujui')->orWhere('nominal_disetujui','<=',0)->orWhereColumn('nominal_disetujui','>','nominal_diajukan')->orWhereColumn('nominal_disetujui','>','batas_nominal_snapshot'))->count()],
            ['reservasi', 'Reservasi klaim melebihi saldo sumber', $reserved > $sourceBalance ? 1 : 0],
            ['alokasi_paid', 'Payout paid tidak sama dengan nominal approved', DB::table('klaim_dana_sosial as k')->select('k.id','k.nominal_disetujui')->selectRaw('COALESCE(SUM(a.jumlah),0) total_alokasi')->leftJoin('alokasi_klaim_dana_sosial as a','a.klaim_dana_sosial_id','=','k.id')->where('k.status','paid')->groupBy('k.id','k.nominal_disetujui')->havingRaw('COALESCE(SUM(a.jumlah),0) <> k.nominal_disetujui')->get()->count()],
            ['atribut_paid', 'Payout paid kehilangan dompet/tanggal/payer/idempotency/jurnal', DB::table('klaim_dana_sosial as k')->leftJoin('jurnal_umum as j',fn($join) => $join->on('j.referensi_id','=','k.id')->where('j.referensi_tipe','=', 'App\\Models\\KlaimDanaSosial')->where('j.idempotency_key','like','dana-sosial:klaim:jurnal:%'))->where('k.status','paid')->where(fn($q) => $q->whereNull('k.dompet_id')->orWhereNull('k.tanggal_bayar')->orWhereNull('k.paid_by')->orWhereNull('k.payout_idempotency_key')->orWhereNull('j.id'))->count()],
        ];
        $this->info('Ringkasan preflight Dana Sosial final (read-only)'); $this->table(['Kode','Pemeriksaan','Count'],$checks);
        if (collect($checks)->contains(fn($row) => $row[2] > 0)) { $this->error('Preflight Dana Sosial menemukan konflik; tidak ada data diubah.'); return self::FAILURE; }
        $this->info('Preflight Dana Sosial bersih.'); return self::SUCCESS;
    }
}
