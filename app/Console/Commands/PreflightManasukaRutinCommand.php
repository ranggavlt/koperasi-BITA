<?php

namespace App\Console\Commands;

use App\Models\KonfigurasiManasukaRutin;
use App\Models\PemakaianPotongGaji;
use App\Models\Simpanan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightManasukaRutinCommand extends Command
{
    protected $signature = 'koperasi:preflight-manasuka-rutin';

    protected $description = 'Audit read-only konfigurasi, reservasi, saldo, Mutasi, dan Jurnal Manasuka rutin PG-2.';

    public function handle(): int
    {
        $checks = [
            $this->check('schema_missing', 'Schema konfigurasi PG-2 belum lengkap', $this->schemaMissing()),
            $this->check('config_invalid', 'Konfigurasi orphan/status/nominal tidak valid', $this->invalidConfigurations()),
            $this->check('ledger_duplicate', 'Lebih dari satu ledger Manasuka aktif per limit', $this->duplicateActiveLedgers()),
            $this->check('ledger_snapshot_invalid', 'Ledger tidak sesuai snapshot transaksi/config', $this->invalidLedgerSnapshots()),
            $this->check('settled_posting_missing', 'Setoran settled tanpa Jurnal atau snapshot saldo valid', $this->missingSettledPostings()),
            $this->check('journal_unbalanced', 'Jurnal Manasuka rutin tidak balance', $this->unbalancedJournals()),
            $this->check('released_transaction_active', 'Ledger released masih memiliki transaksi pending', $this->releasedTransactionsStillPending()),
            $this->check('code_invalid', 'Kode transaksi rutin bukan format SMN-YYYYMM-000001', $this->invalidCodes()),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight Manasuka Rutin PG-2');
        $this->table(
            ['Kode', 'Pemeriksaan', 'Count', 'Severity'],
            array_map(fn (array $check) => [
                $check['code'],
                $check['label'],
                $check['count'],
                'critical',
            ], $checks)
        );

        if (collect($checks)->contains(fn (array $check) => $check['count'] > 0)) {
            $this->error('Preflight PG-2 menemukan konflik kritis. Command ini tidak menulis database.');

            return self::FAILURE;
        }

        $this->info('Preflight Manasuka Rutin bersih: tidak ada konflik kritis.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count): array
    {
        return compact('code', 'label', 'count');
    }

    private function schemaMissing(): int
    {
        foreach (['konfigurasi_manasuka_rutin', 'simpanan', 'pemakaian_potong_gaji', 'saldo_simpanan_manasuka'] as $table) {
            if (! Schema::hasTable($table)) {
                return 1;
            }
        }

        foreach (['anggota_id', 'siklus_keanggotaan_id', 'status', 'nominal_snapshot', 'berlaku_mulai', 'alasan', 'idempotency_key'] as $column) {
            if (! Schema::hasColumn('konfigurasi_manasuka_rutin', $column)) {
                return 1;
            }
        }

        return Schema::hasColumn('simpanan', 'konfigurasi_manasuka_rutin_id') ? 0 : 1;
    }

    private function invalidConfigurations(): int
    {
        if ($this->schemaMissing()) {
            return 0;
        }

        return DB::table('konfigurasi_manasuka_rutin as k')
            ->leftJoin('anggota as a', 'a.id', '=', 'k.anggota_id')
            ->leftJoin('siklus_keanggotaan as s', 's.id', '=', 'k.siklus_keanggotaan_id')
            ->where(function ($query): void {
                $query->whereNull('a.id')
                    ->orWhereNull('s.id')
                    ->orWhereColumn('s.anggota_id', '!=', 'k.anggota_id')
                    ->orWhereNotIn('k.status', KonfigurasiManasukaRutin::statuses())
                    ->orWhere('k.nominal_snapshot', '<', 0)
                    ->orWhere(function ($subQuery): void {
                        $subQuery->where('k.status', KonfigurasiManasukaRutin::STATUS_AKTIF)
                            ->where('k.nominal_snapshot', '<=', 0);
                    });
            })
            ->count();
    }

    private function duplicateActiveLedgers(): int
    {
        if ($this->schemaMissing()) {
            return 0;
        }

        return DB::query()->fromSub(
            DB::table('pemakaian_potong_gaji')
                ->select('limit_potong_gaji_anggota_id', DB::raw('COUNT(*) as total'))
                ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_MANASUKA)
                ->whereIn('status', [
                    PemakaianPotongGaji::STATUS_RESERVED,
                    PemakaianPotongGaji::STATUS_CONSUMED,
                    PemakaianPotongGaji::STATUS_SETTLED,
                ])
                ->groupBy('limit_potong_gaji_anggota_id')
                ->havingRaw('COUNT(*) > 1'),
            'duplikat'
        )->count();
    }

    private function invalidLedgerSnapshots(): int
    {
        if ($this->schemaMissing()) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as p')
            ->leftJoin('simpanan as sm', function ($join): void {
                $join->on('sm.id', '=', 'p.source_id')
                    ->where('p.source_type', Simpanan::class);
            })
            ->leftJoin('konfigurasi_manasuka_rutin as k', 'k.id', '=', 'sm.konfigurasi_manasuka_rutin_id')
            ->leftJoin('limit_potong_gaji_anggota as l', 'l.id', '=', 'p.limit_potong_gaji_anggota_id')
            ->where('p.kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_MANASUKA)
            ->where(function ($query): void {
                $query->whereNull('sm.id')
                    ->orWhereNull('k.id')
                    ->orWhereColumn('sm.anggota_id', '!=', 'l.anggota_id')
                    ->orWhereColumn('sm.siklus_keanggotaan_id', '!=', 'k.siklus_keanggotaan_id')
                    ->orWhereRaw('CAST(p.nominal AS DECIMAL(15,2)) <> CAST(sm.nominal_snapshot AS DECIMAL(15,2))')
                    ->orWhereRaw('CAST(sm.nominal_snapshot AS DECIMAL(15,2)) <> CAST(k.nominal_snapshot AS DECIMAL(15,2))');
            })
            ->count();
    }

    private function missingSettledPostings(): int
    {
        if ($this->schemaMissing() || ! Schema::hasTable('jurnal_umum')) {
            return 0;
        }

        return DB::table('simpanan as sm')
            ->leftJoin('jurnal_umum as j', function ($join): void {
                $join->on('j.referensi_id', '=', 'sm.id')
                    ->where('j.referensi_tipe', Simpanan::class);
            })
            ->whereNotNull('sm.konfigurasi_manasuka_rutin_id')
            ->where('sm.status', Simpanan::STATUS_SETTLED)
            ->where(function ($query): void {
                $query->whereNull('j.id')
                    ->orWhereNull('sm.saldo_sebelum_snapshot')
                    ->orWhereNull('sm.saldo_sesudah_snapshot')
                    ->orWhereRaw('CAST(sm.saldo_sesudah_snapshot AS DECIMAL(15,2)) <> CAST(sm.saldo_sebelum_snapshot AS DECIMAL(15,2)) + CAST(sm.nominal_snapshot AS DECIMAL(15,2))');
            })
            ->count();
    }

    private function unbalancedJournals(): int
    {
        if ($this->schemaMissing() || ! Schema::hasTable('jurnal_umum_detail')) {
            return 0;
        }

        return DB::query()->fromSub(
            DB::table('jurnal_umum as j')
                ->select('j.id')
                ->join('simpanan as sm', function ($join): void {
                    $join->on('sm.id', '=', 'j.referensi_id')
                        ->where('j.referensi_tipe', Simpanan::class);
                })
                ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
                ->whereNotNull('sm.konfigurasi_manasuka_rutin_id')
                ->groupBy('j.id')
                ->havingRaw('ABS(SUM(d.debit) - SUM(d.kredit)) > 0.01'),
            'tidak_balance'
        )->count();
    }

    private function releasedTransactionsStillPending(): int
    {
        if ($this->schemaMissing()) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as p')
            ->join('simpanan as sm', function ($join): void {
                $join->on('sm.id', '=', 'p.source_id')
                    ->where('p.source_type', Simpanan::class);
            })
            ->where('p.kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_MANASUKA)
            ->where('p.status', PemakaianPotongGaji::STATUS_RELEASED)
            ->where('sm.status', Simpanan::STATUS_PENDING_PAYROLL)
            ->count();
    }

    private function invalidCodes(): int
    {
        if ($this->schemaMissing()) {
            return 0;
        }

        return DB::table('simpanan')
            ->whereNotNull('konfigurasi_manasuka_rutin_id')
            ->pluck('kode_transaksi')
            ->filter(fn ($code) => preg_match('/^SMN-\d{6}-\d{6}$/', (string) $code) !== 1)
            ->count();
    }
}
