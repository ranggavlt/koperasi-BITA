<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightAccountingIntegrityCommand extends Command
{
    protected $signature = 'koperasi:preflight-accounting-integrity';

    protected $description = 'Audit read-only pemetaan COA inti, identitas 209/210, jurnal, dan larangan destructive reversal.';

    public function handle(): int
    {
        $issues = [
            'account_map_code_collision' => $this->conflictingConfiguredCodes(),
            'coa_209_invalid' => $this->invalidAccount('209', 'Utang Vendor Sewa Mobil'),
            'coa_210_invalid' => $this->invalidAccount('210', 'Dana Sosial Tersedia'),
            'vendor_mobile_journal_wrong_coa' => $this->journalSnapshotMismatch('Utang Vendor Sewa Mobil', '209'),
            'dana_social_journal_wrong_coa' => $this->journalSnapshotMismatch('Dana Sosial Tersedia', '210'),
            'journal_unbalanced' => $this->unbalancedJournals(),
            'posted_without_timestamp' => Schema::hasColumn('jurnal_umum', 'posted_at') ? DB::table('jurnal_umum')->where('status', 'posted')->whereNull('posted_at')->count() : 1,
            'accounting_period_overlap' => $this->overlappingPeriods(),
            'closed_period_invalid' => $this->invalidClosedPeriods(),
            'closed_period_unlinked_journal' => $this->unlinkedClosedPeriodJournals(),
            'correction_audit_invalid' => Schema::hasColumn('jurnal_umum', 'correction_period_id') ? DB::table('jurnal_umum')->where('is_adjustment', true)->where(fn ($q) => $q->whereNull('correction_period_id')->orWhereNull('correction_reason'))->count() : 1,
            'destructive_reversal_api' => $this->destructiveReversalApis(),
        ];

        $this->table(
            ['Pemeriksaan', 'Count'],
            collect($issues)->map(fn (int $count, string $code): array => [$code, $count])->values()->all()
        );

        if (array_sum($issues) > 0) {
            $this->error('Preflight accounting integrity menemukan konflik kritis. Tidak ada data yang diubah.');

            return self::FAILURE;
        }

        $this->info('Preflight accounting integrity bersih.');

        return self::SUCCESS;
    }

    private function conflictingConfiguredCodes(): int
    {
        return collect(config('account_map.accounts', []))
            ->groupBy(fn (array $account): string => (string) ($account['kode_akun'] ?? ''))
            ->filter(function ($accounts, string $code): bool {
                if ($code === '') {
                    return true;
                }

                return $accounts
                    ->map(fn (array $account): string => implode('|', [
                        (string) ($account['nama_akun'] ?? ''),
                        (string) ($account['kategori'] ?? ''),
                        (string) ($account['posisi_saldo'] ?? ''),
                    ]))
                    ->unique()
                    ->count() > 1;
            })
            ->count();
    }

    private function invalidAccount(string $code, string $name): int
    {
        if (! Schema::hasTable('akun')) {
            return 1;
        }

        return DB::table('akun')
            ->where('kode_akun', $code)
            ->where('nama_akun', $name)
            ->where('kategori', 'kewajiban')
            ->where('posisi_saldo', 'kredit')
            ->where('is_aktif', true)
            ->count() === 1 ? 0 : 1;
    }

    private function journalSnapshotMismatch(string $accountName, string $expectedCode): int
    {
        if (! Schema::hasTable('jurnal_umum_detail')) {
            return 0;
        }

        $expectedId = DB::table('akun')->where('kode_akun', $expectedCode)->value('id');

        return DB::table('jurnal_umum_detail')
            ->where('akun_nama', $accountName)
            ->where(function ($query) use ($expectedCode, $expectedId): void {
                $query->where('akun_kode', '!=', $expectedCode);
                if ($expectedId) {
                    $query->orWhere('akun_id', '!=', $expectedId);
                }
            })
            ->count();
    }

    private function unbalancedJournals(): int
    {
        if (! Schema::hasTable('jurnal_umum') || ! Schema::hasTable('jurnal_umum_detail')) {
            return 0;
        }

        return DB::table('jurnal_umum as j')
            ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
            ->select('j.id')
            ->groupBy('j.id')
            ->havingRaw('ABS(SUM(d.debit) - SUM(d.kredit)) > 0.01')
            ->get()
            ->count();
    }

    private function destructiveReversalApis(): int
    {
        $files = [
            app_path('Services/AkuntansiService.php'),
            app_path('Services/MutasiKasService.php'),
        ];

        return collect($files)
            ->filter(fn (string $path): bool => is_file($path))
            ->sum(function (string $path): int {
                $source = (string) file_get_contents($path);

                return substr_count($source, 'reverseByReference(')
                    + substr_count($source, 'deleteAndReverse(');
            });
    }

    private function overlappingPeriods(): int
    {
        if (! Schema::hasTable('periode_akuntansi')) return 1;
        return DB::table('periode_akuntansi as a')->join('periode_akuntansi as b', fn ($join) => $join->on('a.id', '<', 'b.id')->whereColumn('a.tanggal_mulai', '<=', 'b.tanggal_selesai')->whereColumn('a.tanggal_selesai', '>=', 'b.tanggal_mulai'))->count();
    }

    private function invalidClosedPeriods(): int
    {
        if (! Schema::hasTable('periode_akuntansi')) return 1;
        return DB::table('periode_akuntansi')->where('status', 'closed')->where(fn ($q) => $q->whereNull('closed_at')->orWhereNull('closed_by')->orWhereNull('checksum')->orWhereNull('closing_snapshot')->orWhereNull('closing_reason'))->count();
    }

    private function unlinkedClosedPeriodJournals(): int
    {
        if (! Schema::hasTable('periode_akuntansi')) return 1;
        return DB::table('jurnal_umum as j')->join('periode_akuntansi as p', fn ($join) => $join->on('j.tanggal', '>=', 'p.tanggal_mulai')->on('j.tanggal', '<=', 'p.tanggal_selesai'))->where('p.status', 'closed')->whereNull('j.periode_akuntansi_id')->where('j.is_adjustment', false)->count();
    }
}
