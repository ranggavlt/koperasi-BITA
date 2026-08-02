<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class PreflightShuCommand extends Command
{
    protected $signature = 'koperasi:preflight-shu';
    protected $description = 'Audit read-only hardening SHU tanpa mengaktifkan atau menghitung ulang data.';

    public function handle(): int
    {
        $issues=[];
        $issues['feature_aktif_sebelum_keputusan']=config('features.shu_enabled',false)?1:0;
        $issues['route_hard_delete_tersedia']=(Route::has('shu-koperasi.destroy')||Route::has('shu-koperasi.transaksi.destroy')||Route::has('shu-koperasi.cairkan'))?1:0;
        $issues['closed_tanpa_snapshot']=Schema::hasColumn('shu_koperasi','status')?DB::table('shu_koperasi')->where('status','closed')->where(fn($q)=>$q->whereNull('config_snapshot')->orWhereNull('source_snapshot')->orWhereNull('closed_at')->orWhereNull('closed_by'))->count():0;
        $this->table(['Pemeriksaan','Count'],collect($issues)->map(fn($v,$k)=>[$k,$v])->values()->all());
        if(array_sum($issues)>0){$this->error('SHU belum aman untuk diaktifkan. Command ini tidak menulis database.');return self::FAILURE;}$this->info('SHU tetap nonaktif dan hardening dasar bersih.');return self::SUCCESS;
    }
}
