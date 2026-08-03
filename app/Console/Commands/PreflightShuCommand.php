<?php

namespace App\Console\Commands;

use App\Models\ShuConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class PreflightShuCommand extends Command
{
    protected $signature = 'koperasi:preflight-shu';

    protected $description = 'Audit read-only konfigurasi, snapshot, dan hardening SHU tanpa mengaktifkan atau menghitung ulang data.';

    public function handle(): int
    {
        $issues = [
            'feature_aktif_sebelum_hardening' => config('features.shu_enabled', false) ? 1 : 0,
            'route_hard_delete_tersedia' => (Route::has('shu-koperasi.destroy') || Route::has('shu-koperasi.transaksi.destroy') || Route::has('shu-koperasi.cairkan')) ? 1 : 0,
            'route_input_laba_manual_tersedia' => Route::has('shu-koperasi.transaksi.store') ? 1 : 0,
            'approved_config_invalid' => $this->invalidApprovedConfigs(),
            'approved_effective_duplicate' => $this->duplicateEffectiveConfigs(),
            'period_tanpa_config_snapshot' => $this->periodsWithoutConfigSnapshot(),
            'closed_tanpa_snapshot' => $this->closedWithoutSnapshot(),
            'source_periode_invalid' => $this->invalidAccountingPeriodSources(),
            'allocation_total_invalid' => $this->invalidAllocationTotals(),
            'closed_posting_invalid' => $this->invalidClosedPostings(),
        ];

        $this->table(
            ['Pemeriksaan', 'Count'],
            collect($issues)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        if (array_sum($issues) > 0) {
            $this->error('SHU belum aman untuk diaktifkan. Command ini tidak menulis database.');

            return self::FAILURE;
        }

        $this->info('Konfigurasi SHU valid, operasional tetap nonaktif, dan hardening dasar bersih.');

        return self::SUCCESS;
    }

    private function invalidApprovedConfigs(): int
    {
        if (! Schema::hasTable('shu_configs')) {
            return 1;
        }

        return DB::table('shu_configs')
            ->where('status_persetujuan', ShuConfig::STATUS_APPROVED)
            ->where(function ($query): void {
                $query
                    ->whereNull('berlaku_mulai')
                    ->orWhereNull('approved_by')
                    ->orWhereNull('approved_at')
                    ->orWhereNull('dasar_persetujuan')
                    ->orWhereRaw('ABS((persen_dana_cadangan + persen_anggota + persen_pengawas + persen_pembina + persen_pengurus + persen_dana_sosial + persen_dana_pendidikan) - 100) > 0.01')
                    ->orWhereRaw('ABS((persen_jasa_modal + persen_jasa_usaha) - 100) > 0.01');
            })
            ->count();
    }

    private function duplicateEffectiveConfigs(): int
    {
        if (! Schema::hasTable('shu_configs')) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table('shu_configs')
                    ->select('berlaku_mulai')
                    ->where('status_persetujuan', ShuConfig::STATUS_APPROVED)
                    ->groupBy('berlaku_mulai')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicates'
            )
            ->count();
    }

    private function periodsWithoutConfigSnapshot(): int
    {
        if (! Schema::hasTable('shu_koperasi') || ! Schema::hasColumn('shu_koperasi', 'config_snapshot')) {
            return 0;
        }

        return DB::table('shu_koperasi')->whereNull('config_snapshot')->count();
    }

    private function closedWithoutSnapshot(): int
    {
        if (! Schema::hasTable('shu_koperasi') || ! Schema::hasColumn('shu_koperasi', 'status')) {
            return 0;
        }

        return DB::table('shu_koperasi')
            ->where('status', 'closed')
            ->where(function ($query): void {
                $query
                    ->whereNull('config_snapshot')
                    ->orWhereNull('source_snapshot')
                    ->orWhereNull('closed_at')
                    ->orWhereNull('closed_by');
            })
            ->count();
    }

    private function invalidAccountingPeriodSources(): int
    {
        if (! Schema::hasColumn('shu_koperasi', 'periode_akuntansi_id')) return 1;
        return DB::table('shu_koperasi as shu')->leftJoin('periode_akuntansi as p', 'p.id', '=', 'shu.periode_akuntansi_id')->whereNotNull('shu.periode_akuntansi_id')->where(fn ($q) => $q->whereNull('p.id')->orWhere('p.status', '!=', 'closed')->orWhereColumn('shu.total_pendapatan', '!=', 'p.total_pendapatan')->orWhereColumn('shu.total_biaya', '!=', 'p.total_beban')->orWhereColumn('shu.shu_total', '!=', 'p.laba_bersih'))->count();
    }

    private function invalidAllocationTotals(): int
    {
        return DB::table('shu_koperasi')->where('shu_total', '>', 0)->whereRaw('ABS((nominal_dana_cadangan + nominal_shu_anggota + nominal_pengawas + nominal_pembina + nominal_pengurus + nominal_dana_sosial + nominal_dana_pendidikan) - shu_total) > 0.01')->count();
    }

    private function invalidClosedPostings(): int
    {
        if (! Schema::hasColumn('shu_koperasi', 'allocation_journal_id')) return 1;
        return DB::table('shu_koperasi')->where('status', 'closed')->where(fn ($q) => $q->whereNull('allocation_journal_id')->orWhereNull('posted_by')->orWhereNull('posted_at')->orWhereNull('approved_by')->orWhereNull('approved_at')->orWhereNull('approval_reason'))->count();
    }
}
