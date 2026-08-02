<?php

namespace App\Console\Commands;

use App\Models\KlaimDanaSosial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightDanaSosialCommand extends Command
{
    protected $signature = 'koperasi:preflight-dana-sosial';
    protected $description = 'Audit read-only saldo, approval, payout, Mutasi Kas, jurnal, dan histori Dana Sosial.';

    public function handle(): int
    {
        if (! Schema::hasTable('dana_sosial_sumber')) return self::FAILURE;
        $issues=[];
        $issues['saldo_sumber_invalid']=DB::table('dana_sosial_sumber')->get()->filter(function($s){$in=(int)DB::table('mutasi_dana_sosial')->where('dana_sosial_sumber_id',$s->id)->where('tipe','masuk')->sum('nominal');$out=(int)DB::table('mutasi_dana_sosial')->where('dana_sosial_sumber_id',$s->id)->where('tipe','keluar')->sum('nominal');return (int)$s->saldo_tersedia<0 || $in-$out!==(int)$s->saldo_tersedia;})->count();
        $issues['approval_invalid']=DB::table('klaim_dana_sosial')->whereIn('status',['disetujui','paid'])->where(fn($q)=>$q->whereNull('sumber_dana_sosial_id')->orWhereNull('approved_by')->orWhereNull('approved_at'))->count();
        $issues['paid_invalid']=KlaimDanaSosial::query()->where('status',KlaimDanaSosial::STATUS_PAID)->get()->filter(fn($c)=>!$c->dompet_id||!$c->paid_by||!$c->paid_at||!DB::table('mutasi_dana_sosial')->where('klaim_dana_sosial_id',$c->id)->exists()||!DB::table('mutasi_kas')->where('idempotency_key','dana-claim:kas:'.$c->id)->exists()||!DB::table('jurnal_umum')->where('idempotency_key','dana-claim:jurnal:'.$c->id)->exists())->count();
        $issues['jurnal_unbalanced']=DB::table('jurnal_umum as j')->join('jurnal_umum_detail as d','d.jurnal_umum_id','=','j.id')->select('j.id')->where('j.idempotency_key','like','dana-%')->groupBy('j.id')->havingRaw('ABS(SUM(d.debit)-SUM(d.kredit)) > 0.01')->get()->count();
        $this->table(['Pemeriksaan','Count'],collect($issues)->map(fn($v,$k)=>[$k,$v])->values()->all());
        if(array_sum($issues)>0){$this->error('Preflight Dana Sosial menemukan konflik kritis. Tidak ada data yang diubah.');return self::FAILURE;}$this->info('Preflight Dana Sosial bersih.');return self::SUCCESS;
    }
}
