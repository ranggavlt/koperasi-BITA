<?php

namespace App\Console\Commands;

use App\Models\DanaSosialSumber;
use App\Models\KlaimDanaSosial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightDanaSosialCommand extends Command
{
    protected $signature = 'koperasi:preflight-dana-sosial';
    protected $description = 'Audit read-only Donasi Resmi, batas klaim, maker-checker, saldo, Mutasi Kas, jurnal, dan reversal Dana Sosial.';

    public function handle(): int
    {
        if (! Schema::hasTable('dana_sosial_sumber') || ! Schema::hasTable('batas_klaim_dana_sosial')) return self::FAILURE;
        $issues = [];
        $issues['feature_dependency_invalid'] = (bool) config('features.dana_sosial_enabled', false) && ! (bool) config('features.shu_enabled', false) ? 1 : 0;
        $issues['source_type_invalid'] = DB::table('dana_sosial_sumber')->whereNotIn('jenis_sumber', [DanaSosialSumber::JENIS_SHU, DanaSosialSumber::JENIS_DONASI])->count();
        $issues['coa_209_or_210_invalid'] = DB::table('akun')->where(fn ($q) => $q->where('kode_akun', '209')->where('nama_akun', 'Utang Vendor Sewa Mobil')->where('kategori', 'kewajiban')->where('posisi_saldo', 'kredit'))->count() === 1 && DB::table('akun')->where(fn ($q) => $q->where('kode_akun', '210')->where('nama_akun', 'Dana Sosial Tersedia')->where('kategori', 'kewajiban')->where('posisi_saldo', 'kredit'))->count() === 1 ? 0 : 1;
        $issues['donation_audit_invalid'] = DB::table('dana_sosial_sumber')->where('jenis_sumber', DanaSosialSumber::JENIS_DONASI)->whereIn('status', [DanaSosialSumber::STATUS_ACTIVE, DanaSosialSumber::STATUS_CLOSED])->where(fn ($q) => $q->whereNull('dompet_id')->orWhereNull('tanggal_diterima')->orWhereNull('approved_by')->orWhereNull('approved_at')->orWhereColumn('created_by', 'approved_by'))->count();
        $issues['claim_checker_invalid'] = DB::table('klaim_dana_sosial')->whereIn('status', [KlaimDanaSosial::STATUS_DISETUJUI, KlaimDanaSosial::STATUS_PAID])->where(fn ($q) => $q->whereNull('approved_by')->orWhereNull('approved_at')->orWhereNull('approval_reason')->orWhereColumn('created_by', 'approved_by'))->count();
        $issues['claim_limit_invalid'] = DB::table('klaim_dana_sosial')->where(fn ($q) => $q->whereNull('batas_klaim_id')->orWhereNull('batas_nominal_snapshot')->orWhereColumn('nominal', '>', 'batas_nominal_snapshot'))->count();
        $issues['saldo_sumber_invalid'] = DB::table('dana_sosial_sumber')->get()->filter(function ($source): bool { $in = (int) DB::table('mutasi_dana_sosial')->where('dana_sosial_sumber_id', $source->id)->where('tipe', 'masuk')->sum('nominal'); $out = (int) DB::table('mutasi_dana_sosial')->where('dana_sosial_sumber_id', $source->id)->where('tipe', 'keluar')->sum('nominal'); return (int) $source->saldo_tersedia < 0 || $in - $out !== (int) $source->saldo_tersedia; })->count();
        $issues['paid_artifacts_invalid'] = KlaimDanaSosial::query()->where('status', KlaimDanaSosial::STATUS_PAID)->get()->filter(fn ($claim) => ! $claim->dompet_id || ! $claim->paid_by || ! $claim->paid_at || ! DB::table('mutasi_dana_sosial')->where('idempotency_key', 'dana-claim:fund-mutation:'.$claim->id)->exists() || ! DB::table('mutasi_kas')->where('idempotency_key', 'dana-claim:cash:'.$claim->id)->exists() || ! DB::table('jurnal_umum')->where('idempotency_key', 'dana-claim:journal:'.$claim->id)->exists())->count();
        $issues['reversal_artifacts_invalid'] = KlaimDanaSosial::query()->where('status', KlaimDanaSosial::STATUS_REVERSED)->get()->filter(fn ($claim) => ! $claim->reversed_by || ! $claim->reversed_at || ! $claim->reversal_reason || ! DB::table('mutasi_dana_sosial')->where('idempotency_key', 'dana-claim:reversal:fund-mutation:'.$claim->id)->exists() || ! DB::table('mutasi_kas')->where('idempotency_key', 'dana-claim:reversal:cash:'.$claim->id)->exists() || ! DB::table('jurnal_umum')->where('idempotency_key', 'dana-claim:reversal:journal:'.$claim->id)->exists())->count();
        $issues['jurnal_unbalanced'] = DB::table('jurnal_umum as j')->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')->select('j.id')->where('j.idempotency_key', 'like', 'dana-%')->groupBy('j.id')->havingRaw('ABS(SUM(d.debit)-SUM(d.kredit)) > 0.01')->get()->count();
        $this->table(['Pemeriksaan', 'Count'], collect($issues)->map(fn ($value, $key) => [$key, $value])->values()->all());
        if (array_sum($issues) > 0) { $this->error('Preflight Dana Sosial menemukan konflik kritis. Tidak ada data yang diubah.'); return self::FAILURE; }
        $this->info('Preflight Dana Sosial bersih.'); return self::SUCCESS;
    }
}
