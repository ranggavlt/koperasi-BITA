<?php

namespace App\Console\Commands;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\PemakaianPotongGaji;
use App\Models\Simpanan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightSimpananWajibCommand extends Command
{
    protected $signature = 'koperasi:preflight-simpanan-wajib';

    protected $description = 'Audit read-only Simpanan Wajib final SP-7 dan histori jadwal Wajib legacy.';

    public function handle(): int
    {
        $checks = [
            $this->check('schema_missing', 'Schema Simpanan Wajib final belum lengkap', $this->schemaMissing()),
            $this->check('master_pokok_aktif', 'Master Pokok masih aktif', $this->activeLegacyCategory(JenisSimpanan::KATEGORI_POKOK)),
            $this->check('master_sukarela_aktif', 'Master Sukarela masih aktif', $this->activeLegacyCategory('sukarela')),
            $this->check('master_wajib_count', 'Master Wajib aktif tidak tepat satu', $this->invalidActiveCount(JenisSimpanan::KATEGORI_WAJIB)),
            $this->check('master_manasuka_count', 'Master Manasuka aktif tidak tepat satu', $this->invalidActiveCount(JenisSimpanan::KATEGORI_MANASUKA)),
            $this->check('wajib_nominal_invalid', 'Nominal Wajib aktif bukan Rp10.000', $this->invalidWajibNominal()),
            $this->check('wajib_interval_invalid', 'Wajib aktif masih memiliki interval', $this->invalidWajibInterval()),
            $this->check('wajib_duplicate_siklus', 'Lebih dari satu Wajib final aktif per siklus', $this->duplicateWajibPerCycle()),
            $this->check('wajib_tanpa_siklus', 'Wajib final tanpa siklus keanggotaan', $this->wajibWithoutCycle()),
            $this->check('jadwal_wajib_baru', 'Jadwal Wajib berkala baru setelah cutoff SP-7', $this->newLegacySchedules()),
            $this->check('wajib_tunai_dompet_salah', 'Wajib tunai tidak memakai Dompet Kas', $this->wrongDompetForMethod(Simpanan::METODE_TUNAI, 'kas')),
            $this->check('wajib_transfer_dompet_salah', 'Wajib transfer tidak memakai Dompet Bank', $this->wrongDompetForMethod(Simpanan::METODE_TRANSFER_BANK, 'bank')),
            $this->check('wajib_direct_posting_invalid', 'Wajib tunai/bank paid tanpa Mutasi/Jurnal tepat satu', $this->directPaidWithoutPosting()),
            $this->check('wajib_payroll_pengakuan_missing', 'Wajib payroll tanpa jurnal pengakuan piutang', $this->payrollRecognitionMissing()),
            $this->check('wajib_payroll_ledger_invalid', 'Wajib payroll allocated/settled tanpa ledger valid', $this->payrollLedgerInvalid()),
            $this->check('wajib_pending_nonaktif', 'Wajib pending/allocated milik Anggota/Karyawan nonaktif', $this->pendingForInactiveMember()),
            $this->check('posting_akun_302_baru', 'Posting Simpanan Wajib baru memakai akun 302 legacy', $this->newPostingToLegacy302()),
            $this->check('hak_settlement_ganda', 'Hak settlement menghitung Pokok dan Wajib final sekaligus', $this->duplicateSettlementRights()),
            $this->check('manasuka_payroll', 'Manasuka masuk ledger payroll', $this->manasukaInPayrollLedger()),
            $this->check('idempotency_duplicate', 'Duplicate idempotency Simpanan Wajib/ledger/Mutasi/Jurnal', $this->duplicateWajibIdempotency()),
            $this->check('jurnal_wajib_tidak_seimbang', 'Jurnal Simpanan Wajib/payroll tidak seimbang', $this->unbalancedWajibJournal()),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight Simpanan Wajib SP-7');
        $this->table(
            ['Kode', 'Pemeriksaan', 'Count', 'Severity'],
            array_map(fn (array $check) => [
                $check['code'],
                $check['label'],
                $check['count'],
                $check['critical'] ? 'critical' : 'info',
            ], $checks)
        );

        $criticalCount = collect($checks)
            ->filter(fn (array $check) => $check['critical'] && $check['count'] > 0)
            ->count();

        if ($criticalCount > 0) {
            $this->error('Preflight Simpanan Wajib SP-7 menemukan konflik kritis. Command ini read-only dan tidak menulis database.');

            return self::FAILURE;
        }

        $this->info('Preflight Simpanan Wajib SP-7 bersih.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count, bool $critical = true): array
    {
        return compact('code', 'label', 'count', 'critical');
    }

    private function schemaMissing(): int
    {
        if (! $this->hasTables([
            'jenis_simpanan',
            'simpanan',
            'pemakaian_potong_gaji',
            'dompet_koperasi',
            'mutasi_kas',
            'jurnal_umum',
            'jurnal_umum_detail',
        ])) {
            return 1;
        }

        foreach (['siklus_keanggotaan_id', 'simpanan_wajib_siklus_id', 'metode_pembayaran', 'pemakaian_potong_gaji_id'] as $column) {
            if (! Schema::hasColumn('simpanan', $column)) {
                return 1;
            }
        }

        return 0;
    }

    private function activeLegacyCategory(string $kategori): int
    {
        if (! Schema::hasTable('jenis_simpanan')) {
            return 0;
        }

        return DB::table('jenis_simpanan')
            ->where('kategori', $kategori)
            ->where('aktif', true)
            ->count();
    }

    private function invalidActiveCount(string $kategori): int
    {
        if (! Schema::hasTable('jenis_simpanan')) {
            return 0;
        }

        return DB::table('jenis_simpanan')
            ->where('kategori', $kategori)
            ->where('aktif', true)
            ->count() === 1 ? 0 : 1;
    }

    private function invalidWajibNominal(): int
    {
        if (! Schema::hasTable('jenis_simpanan')) {
            return 0;
        }

        return DB::table('jenis_simpanan')
            ->where('kategori', JenisSimpanan::KATEGORI_WAJIB)
            ->where('aktif', true)
            ->where(function ($query): void {
                $query->where('kode', '!=', JenisSimpanan::KODE_SIMPANAN_WAJIB)
                    ->orWhereRaw('ABS(nominal_default - 10000) > 0.01');
            })
            ->count();
    }

    private function invalidWajibInterval(): int
    {
        if (! Schema::hasTable('jenis_simpanan')) {
            return 0;
        }

        return DB::table('jenis_simpanan')
            ->where('kategori', JenisSimpanan::KATEGORI_WAJIB)
            ->where('aktif', true)
            ->whereNotNull('interval_bulan')
            ->count();
    }

    private function duplicateWajibPerCycle(): int
    {
        if (! Schema::hasTable('simpanan')) {
            return 0;
        }

        return DB::table('simpanan')
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->whereNotNull('siklus_keanggotaan_id')
            ->whereNotIn('status', [Simpanan::STATUS_REVERSED, Simpanan::STATUS_REVERSED_DUE_TO_EXIT])
            ->select('siklus_keanggotaan_id', DB::raw('COUNT(*) as total'))
            ->groupBy('siklus_keanggotaan_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function wajibWithoutCycle(): int
    {
        if (! Schema::hasTable('simpanan')) {
            return 0;
        }

        return DB::table('simpanan')
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->whereNull('siklus_keanggotaan_id')
            ->whereNotIn('status', [Simpanan::STATUS_REVERSED, Simpanan::STATUS_REVERSED_DUE_TO_EXIT])
            ->count();
    }

    private function newLegacySchedules(): int
    {
        if (! Schema::hasTable('jadwal_simpanan_wajib') || ! Schema::hasColumn('jadwal_simpanan_wajib', 'sp7_archived_at')) {
            return 0;
        }

        return DB::table('jadwal_simpanan_wajib')
            ->whereNull('sp7_archived_at')
            ->count();
    }

    private function wrongDompetForMethod(string $method, string $expectedDompetKind): int
    {
        if (! $this->hasTables(['simpanan', 'dompet_koperasi'])) {
            return 0;
        }

        return DB::table('simpanan as s')
            ->leftJoin('dompet_koperasi as d', 'd.id', '=', 's.dompet_id')
            ->where('s.kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->where('s.metode_pembayaran', $method)
            ->whereNotIn('s.status', [Simpanan::STATUS_REVERSED, Simpanan::STATUS_REVERSED_DUE_TO_EXIT])
            ->where(function ($query) use ($expectedDompetKind): void {
                $query->whereNull('d.id')
                    ->orWhere('d.jenis_dompet', '!=', $expectedDompetKind);
            })
            ->count();
    }

    private function directPaidWithoutPosting(): int
    {
        if (! $this->hasTables(['simpanan', 'mutasi_kas', 'jurnal_umum'])) {
            return 0;
        }

        return DB::table('simpanan')
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->whereIn('metode_pembayaran', [Simpanan::METODE_TUNAI, Simpanan::METODE_TRANSFER_BANK])
            ->whereIn('status', [Simpanan::STATUS_SETTLED, Simpanan::STATUS_SETTLED_CASH])
            ->get(['id'])
            ->filter(function ($simpanan): bool {
                $mutasi = DB::table('mutasi_kas')
                    ->where('idempotency_key', 'simpanan-wajib:direct:mutasi:'.$simpanan->id)
                    ->where('referensi_tipe', Simpanan::class)
                    ->where('referensi_id', $simpanan->id)
                    ->count();
                $jurnal = DB::table('jurnal_umum')
                    ->where('idempotency_key', 'simpanan-wajib:direct:jurnal:'.$simpanan->id)
                    ->where('referensi_tipe', Simpanan::class)
                    ->where('referensi_id', $simpanan->id)
                    ->count();

                return $mutasi !== 1 || $jurnal !== 1;
            })
            ->count();
    }

    private function payrollRecognitionMissing(): int
    {
        if (! $this->hasTables(['simpanan', 'jurnal_umum'])) {
            return 0;
        }

        return DB::table('simpanan')
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->where('metode_pembayaran', Simpanan::METODE_POTONG_GAJI)
            ->whereNotIn('status', [Simpanan::STATUS_REVERSED, Simpanan::STATUS_REVERSED_DUE_TO_EXIT])
            ->get(['id'])
            ->filter(fn ($simpanan): bool => DB::table('jurnal_umum')
                ->where('idempotency_key', 'simpanan-wajib:pengakuan:jurnal:'.$simpanan->id)
                ->where('referensi_tipe', Simpanan::class)
                ->where('referensi_id', $simpanan->id)
                ->count() !== 1)
            ->count();
    }

    private function payrollLedgerInvalid(): int
    {
        if (! $this->hasTables(['simpanan', 'pemakaian_potong_gaji'])) {
            return 0;
        }

        $issues = DB::table('simpanan as s')
            ->leftJoin('pemakaian_potong_gaji as p', 'p.id', '=', 's.pemakaian_potong_gaji_id')
            ->where('s.kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->whereNull('s.jadwal_simpanan_wajib_id')
            ->where('s.metode_pembayaran', Simpanan::METODE_POTONG_GAJI)
            ->whereIn('s.status', [Simpanan::STATUS_ALLOCATED, Simpanan::STATUS_SETTLED])
            ->where(function ($query): void {
                $query->whereNull('p.id')
                    ->orWhere('p.kategori', '!=', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
                    ->orWhere('p.source_type', '!=', Simpanan::class)
                    ->orWhereColumn('p.source_id', '!=', 's.id')
                    ->orWhere(function ($statusQuery): void {
                        $statusQuery->where('s.status', Simpanan::STATUS_ALLOCATED)
                            ->where('p.status', '!=', PemakaianPotongGaji::STATUS_RESERVED);
                    })
                    ->orWhere(function ($statusQuery): void {
                        $statusQuery->where('s.status', Simpanan::STATUS_SETTLED)
                            ->where('p.status', '!=', PemakaianPotongGaji::STATUS_SETTLED);
                    });
            })
            ->count();

        $issues += DB::table('pemakaian_potong_gaji as p')
            ->leftJoin('simpanan as s', 's.id', '=', 'p.source_id')
            ->where('p.kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
            ->where('p.source_type', Simpanan::class)
            ->where(function ($query): void {
                $query->whereNull('s.id')
                    ->orWhere('s.kode_jenis_snapshot', '!=', JenisSimpanan::KODE_SIMPANAN_WAJIB);
            })
            ->count();

        return $issues;
    }

    private function pendingForInactiveMember(): int
    {
        if (! $this->hasTables(['simpanan', 'anggota', 'karyawan'])) {
            return 0;
        }

        return DB::table('simpanan as s')
            ->join('anggota as a', 'a.id', '=', 's.anggota_id')
            ->join('karyawan as k', 'k.id', '=', 'a.karyawan_id')
            ->where('s.kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->whereIn('s.status', [Simpanan::STATUS_PENDING_PAYROLL, Simpanan::STATUS_ALLOCATED])
            ->where(function ($query): void {
                $query->where('a.status', '!=', Anggota::STATUS_AKTIF)
                    ->orWhere('k.status_kerja', '!=', Karyawan::STATUS_AKTIF);
            })
            ->count();
    }

    private function newPostingToLegacy302(): int
    {
        if (! $this->hasTables(['jurnal_umum', 'jurnal_umum_detail'])) {
            return 0;
        }

        return DB::table('jurnal_umum as j')
            ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
            ->leftJoin('simpanan as s', function ($join): void {
                $join->on('s.id', '=', 'j.referensi_id')
                    ->where('j.referensi_tipe', Simpanan::class);
            })
            ->where('d.akun_kode', '302')
            ->whereNull('s.jadwal_simpanan_wajib_id')
            ->where(function ($query): void {
                $query->where('j.idempotency_key', 'like', 'simpanan-wajib:%')
                    ->orWhere('j.idempotency_key', 'like', 'PG-SWJ-%');
            })
            ->count();
    }

    private function duplicateSettlementRights(): int
    {
        if (! $this->hasTables(['penyelesaian_keanggotaan_detail'])) {
            return 0;
        }

        return DB::table('penyelesaian_keanggotaan_detail')
            ->where('tipe_detail', 'hak')
            ->whereIn('kategori_sumber', ['simpanan_pokok', 'simpanan_wajib'])
            ->select('penyelesaian_keanggotaan_id', DB::raw('COUNT(DISTINCT kategori_sumber) as total'))
            ->groupBy('penyelesaian_keanggotaan_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function manasukaInPayrollLedger(): int
    {
        if (! $this->hasTables(['simpanan', 'pemakaian_potong_gaji'])) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as p')
            ->join('simpanan as s', 's.id', '=', 'p.source_id')
            ->where('p.source_type', Simpanan::class)
            ->where('s.kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
            ->whereIn('p.status', [
                PemakaianPotongGaji::STATUS_RESERVED,
                PemakaianPotongGaji::STATUS_CONSUMED,
                PemakaianPotongGaji::STATUS_SETTLED,
            ])
            ->count();
    }

    private function duplicateWajibIdempotency(): int
    {
        $tables = ['simpanan', 'pemakaian_potong_gaji', 'mutasi_kas', 'jurnal_umum'];
        $total = 0;

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'idempotency_key')) {
                continue;
            }

            $total += DB::table($table)
                ->whereNotNull('idempotency_key')
                ->where(function ($query): void {
                    $query->where('idempotency_key', 'like', 'simpanan-wajib:%')
                        ->orWhere('idempotency_key', 'like', 'PG-SWJ-%')
                        ->orWhere('idempotency_key', 'like', 'reversal:simpanan-wajib-exit:%');
                })
                ->select('idempotency_key', DB::raw('COUNT(*) as total'))
                ->groupBy('idempotency_key')
                ->having('total', '>', 1)
                ->get()
                ->count();
        }

        return $total;
    }

    private function unbalancedWajibJournal(): int
    {
        if (! $this->hasTables(['jurnal_umum', 'jurnal_umum_detail'])) {
            return 0;
        }

        return DB::table('jurnal_umum as j')
            ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
            ->where(function ($query): void {
                $query->where('j.idempotency_key', 'like', 'simpanan-wajib:%')
                    ->orWhere('j.idempotency_key', 'like', 'PG-SWJ-%')
                    ->orWhere('j.idempotency_key', 'like', 'reversal:simpanan-wajib-exit:%');
            })
            ->select('j.id', DB::raw('ABS(SUM(d.debit) - SUM(d.kredit)) as diff'))
            ->groupBy('j.id')
            ->having('diff', '>', 0.01)
            ->get()
            ->count();
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function hasTables(array $tables): bool
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
