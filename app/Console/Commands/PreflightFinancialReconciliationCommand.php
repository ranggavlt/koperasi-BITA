<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightFinancialReconciliationCommand extends Command
{
    protected $signature = 'koperasi:preflight-financial-reconciliation';

    protected $description = 'Rekonsiliasi read-only lintas Dompet, Mutasi, Jurnal, invoice B2B, payroll, kode transaksi, dan idempotency.';

    public function handle(): int
    {
        $issues = [
            'wallet_balance_mismatch' => $this->walletBalanceMismatch(),
            'journal_unbalanced' => $this->journalUnbalanced(),
            'journal_without_details' => $this->journalWithoutDetails(),
            'journal_detail_orphan' => $this->orphan('jurnal_umum_detail', 'jurnal_umum_id', 'jurnal_umum'),
            'cash_mutation_wallet_orphan' => $this->orphan('mutasi_kas', 'dompet_id', 'dompet_koperasi'),
            'duplicate_idempotency' => $this->duplicateIdempotency(),
            'duplicate_transaction_code' => $this->duplicateTransactionCodes(),
            'coa_code_duplicate' => $this->duplicateColumn('akun', 'kode_akun'),
            'coa_core_identity_invalid' => $this->coreAccountIdentityInvalid(),
            'active_membership_duplicate' => $this->duplicateActiveMembership(),
            'open_loan_duplicate' => $this->duplicateOpenLoans(),
            'invoice_reconciliation_mismatch' => $this->invoiceMismatch(),
            'vendor_payment_orphan' => $this->vendorPaymentOrphan(),
            'payroll_state_mismatch' => $this->payrollStateMismatch(),
        ];

        $this->table(
            ['Pemeriksaan', 'Count'],
            collect($issues)->map(fn (int $count, string $code): array => [$code, $count])->values()->all()
        );

        if (array_sum($issues) > 0) {
            $this->error('Rekonsiliasi keuangan menemukan konflik kritis. Command ini tidak mengubah data.');

            return self::FAILURE;
        }

        $this->info('Rekonsiliasi keuangan lintas modul bersih.');

        return self::SUCCESS;
    }

    private function walletBalanceMismatch(): int
    {
        if (! Schema::hasColumn('dompet_koperasi', 'saldo_awal')) {
            return 1;
        }

        return DB::table('dompet_koperasi')->get()->filter(function ($wallet): bool {
            $incoming = (int) DB::table('mutasi_kas')->where('dompet_id', $wallet->id)->where('tipe', 'masuk')->sum('jumlah');
            $outgoing = (int) DB::table('mutasi_kas')->where('dompet_id', $wallet->id)->where('tipe', 'keluar')->sum('jumlah');

            return (int) $wallet->saldo !== (int) $wallet->saldo_awal + $incoming - $outgoing;
        })->count();
    }

    private function journalUnbalanced(): int
    {
        return DB::table('jurnal_umum as j')
            ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
            ->select('j.id')->groupBy('j.id')
            ->havingRaw('ABS(SUM(d.debit) - SUM(d.kredit)) > 0.01')
            ->get()->count();
    }

    private function journalWithoutDetails(): int
    {
        return DB::table('jurnal_umum as j')
            ->leftJoin('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
            ->whereNull('d.id')->count('j.id');
    }

    private function orphan(string $table, string $foreignKey, string $parentTable): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($table, $foreignKey)) {
            return 0;
        }

        return DB::table("{$table} as child")
            ->leftJoin("{$parentTable} as parent", 'parent.id', '=', "child.{$foreignKey}")
            ->whereNotNull("child.{$foreignKey}")
            ->whereNull('parent.id')
            ->count();
    }

    private function duplicateIdempotency(): int
    {
        $tables = [
            'jurnal_umum', 'mutasi_kas', 'simpanan', 'pinjaman', 'pemakaian_potong_gaji',
            'pembayaran_vendor_sewa', 'invoice_penagihan', 'pembayaran_invoice_perusahaan',
            'dana_sosial_sumber', 'klaim_dana_sosial', 'mutasi_dana_sosial',
        ];

        return collect($tables)
            ->filter(fn (string $table): bool => Schema::hasTable($table) && Schema::hasColumn($table, 'idempotency_key'))
            ->sum(fn (string $table): int => DB::table($table)
                ->whereNotNull('idempotency_key')
                ->where('idempotency_key', '!=', '')
                ->select('idempotency_key')->groupBy('idempotency_key')
                ->havingRaw('COUNT(*) > 1')->get()->count());
    }

    private function duplicateTransactionCodes(): int
    {
        $columns = [
            'simpanan' => 'kode_transaksi',
            'pinjaman' => 'kode_pinjaman',
            'penjualan' => 'kode_transaksi',
            'sewa_mobil' => 'kode_sewa',
            'sewa_hardware' => 'kode_sewa',
            'invoice_penagihan' => 'nomor_invoice',
            'pembayaran_vendor_sewa' => 'kode_pembayaran',
            'pembayaran_invoice_perusahaan' => 'kode_pembayaran',
            'dana_sosial_sumber' => 'kode_sumber',
            'klaim_dana_sosial' => 'kode_klaim',
        ];

        return collect($columns)
            ->filter(fn (string $column, string $table): bool => Schema::hasTable($table) && Schema::hasColumn($table, $column))
            ->map(fn (string $column, string $table): int => $this->duplicateColumn($table, $column))
            ->sum();
    }

    private function duplicateColumn(string $table, string $column): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->whereNotNull($column)->where($column, '!=', '')
            ->select($column)->groupBy($column)->havingRaw('COUNT(*) > 1')->get()->count();
    }

    private function coreAccountIdentityInvalid(): int
    {
        $expected = [
            '209' => 'Utang Vendor Sewa Mobil',
            '210' => 'Dana Sosial Tersedia',
        ];

        return collect($expected)->filter(fn (string $name, string $code): bool => DB::table('akun')
            ->where('kode_akun', $code)->where('nama_akun', $name)
            ->where('kategori', 'kewajiban')->where('posisi_saldo', 'kredit')
            ->where('is_aktif', true)->count() !== 1)->count();
    }

    private function duplicateActiveMembership(): int
    {
        if (! Schema::hasTable('siklus_keanggotaan')) {
            return 0;
        }

        return DB::table('siklus_keanggotaan')->where('status', 'active')
            ->select('anggota_id')->groupBy('anggota_id')->havingRaw('COUNT(*) > 1')->get()->count();
    }

    private function duplicateOpenLoans(): int
    {
        if (! Schema::hasTable('pinjaman')) {
            return 0;
        }

        return DB::table('pinjaman')->whereIn('status', ['draft', 'diajukan', 'disetujui', 'aktif'])
            ->select('anggota_id')->groupBy('anggota_id')->havingRaw('COUNT(*) > 1')->get()->count();
    }

    private function invoiceMismatch(): int
    {
        if (! Schema::hasTable('invoice_penagihan')) {
            return 0;
        }

        return DB::table('invoice_penagihan')->get()->filter(function ($invoice): bool {
            $details = (int) DB::table('invoice_penagihan_detail')->where('invoice_penagihan_id', $invoice->id)->sum('nominal');
            $payments = (int) DB::table('pembayaran_invoice_perusahaan')->where('invoice_penagihan_id', $invoice->id)->where('status', 'paid')->sum('jumlah_bayar');
            $remaining = max(0, (int) $invoice->total_tagihan - $payments);
            $status = $remaining === 0 ? 'paid' : ($payments > 0 ? 'partial' : 'unpaid');

            return $details !== (int) $invoice->total_tagihan
                || $payments !== (int) $invoice->jumlah_dibayar
                || $remaining !== (int) $invoice->sisa_tagihan
                || $status !== $invoice->status;
        })->count();
    }

    private function vendorPaymentOrphan(): int
    {
        if (! Schema::hasTable('pembayaran_vendor_sewa')) {
            return 0;
        }

        return DB::table('pembayaran_vendor_sewa')->get()->filter(function ($payment): bool {
            $table = match ($payment->sewa_type) {
                'App\\Models\\SewaMobil' => 'sewa_mobil',
                'App\\Models\\SewaHardware' => 'sewa_hardware',
                default => null,
            };

            return $table === null || ! DB::table($table)->where('id', $payment->sewa_id)->exists();
        })->count();
    }

    private function payrollStateMismatch(): int
    {
        if (! Schema::hasTable('pemakaian_potong_gaji')) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji')->get()->filter(function ($usage): bool {
            return match ($usage->status) {
                'reserved' => $usage->settled_at !== null || $usage->released_at !== null,
                'settled' => $usage->settled_at === null || $usage->released_at !== null,
                'released' => $usage->released_at === null,
                default => false,
            };
        })->count();
    }
}
