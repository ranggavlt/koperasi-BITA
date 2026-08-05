<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightDanaSosialCommand extends Command
{
    protected $signature = 'koperasi:preflight-dana-sosial';
    protected $description = 'Audit read-only saldo sumber, alokasi FIFO, status klaim, dan maker-checker Dana Sosial.';

    public function handle(): int
    {
        $checks = [
            ['saldo_sumber', 'Saldo sumber negatif atau melebihi nilai awal', $this->invalidSourceBalances()],
            ['maker_checker_donasi', 'Penyetuju donasi sama dengan pembuat', $this->donationApprovalConflicts()],
            ['maker_checker_klaim', 'Penyetuju klaim sama dengan pembuat', $this->claimApprovalConflicts()],
            ['alokasi_klaim', 'Total alokasi klaim dibayar tidak sama dengan nominal', $this->claimAllocationMismatches()],
            ['klaim_tanpa_bayar', 'Status klaim dan atribut pembayaran tidak konsisten', $this->claimPaymentMismatches()],
        ];

        $this->info('Ringkasan preflight Dana Sosial (read-only)');
        $this->table(['Kode', 'Pemeriksaan', 'Count'], $checks);
        if (collect($checks)->contains(fn (array $check): bool => $check[2] > 0)) {
            $this->error('Preflight Dana Sosial menemukan konflik. Tidak ada data yang diubah.');
            return self::FAILURE;
        }

        $this->info('Preflight Dana Sosial bersih.');
        return self::SUCCESS;
    }

    private function ready(): bool
    {
        return Schema::hasTable('dana_sosial_sumber') && Schema::hasTable('klaim_dana_sosial') && Schema::hasTable('alokasi_klaim_dana_sosial');
    }

    private function invalidSourceBalances(): int
    {
        if (! $this->ready()) return 0;
        return DB::table('dana_sosial_sumber')->where(fn ($query) => $query->where('saldo_tersedia', '<', 0)->orWhereColumn('saldo_tersedia', '>', 'jumlah'))->count();
    }

    private function donationApprovalConflicts(): int
    {
        if (! $this->ready()) return 0;
        return DB::table('dana_sosial_sumber')->where('jenis', 'donasi_resmi')->whereNotNull('approved_by')->whereColumn('approved_by', 'created_by')->count();
    }

    private function claimApprovalConflicts(): int
    {
        if (! $this->ready()) return 0;
        return DB::table('klaim_dana_sosial')->whereNotNull('approved_by')->whereColumn('approved_by', 'created_by')->count();
    }

    private function claimAllocationMismatches(): int
    {
        if (! $this->ready()) return 0;
        return DB::table('klaim_dana_sosial as k')->select('k.id', 'k.nominal_diajukan')->selectRaw('COALESCE(SUM(a.jumlah), 0) as total_alokasi')->leftJoin('alokasi_klaim_dana_sosial as a', 'a.klaim_dana_sosial_id', '=', 'k.id')->where('k.status', 'dibayar')->groupBy('k.id', 'k.nominal_diajukan')->havingRaw('ABS(COALESCE(SUM(a.jumlah), 0) - k.nominal_diajukan) > 0.01')->get()->count();
    }

    private function claimPaymentMismatches(): int
    {
        if (! $this->ready()) return 0;
        return DB::table('klaim_dana_sosial')->where(function ($query): void {
            $query->where(fn ($q) => $q->where('status', 'dibayar')->where(fn ($inner) => $inner->whereNull('dompet_id')->orWhereNull('tanggal_bayar')->orWhereNull('paid_by')))
                ->orWhere(fn ($q) => $q->where('status', '!=', 'dibayar')->whereNotNull('paid_at'));
        })->count();
    }
}
