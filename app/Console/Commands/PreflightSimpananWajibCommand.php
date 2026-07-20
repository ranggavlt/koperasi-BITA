<?php

namespace App\Console\Commands;

use App\Models\JadwalSimpananWajib;
use App\Models\JenisSimpanan;
use App\Models\PemakaianPotongGaji;
use App\Models\Simpanan;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightSimpananWajibCommand extends Command
{
    protected $signature = 'koperasi:preflight-simpanan-wajib';

    protected $description = 'Audit read-only jadwal, tunggakan, dan payroll Simpanan Wajib.';

    public function handle(): int
    {
        $checks = [
            $this->check('schema_missing', 'Schema Jadwal Simpanan Wajib belum lengkap', $this->schemaMissing()),
            $this->check('jadwal_duplicate', 'Jadwal duplikat per Anggota/Siklus/Jenis/Periode', $this->duplicateSchedule()),
            $this->check('kode_tagihan_duplicate', 'Kode tagihan Simpanan Wajib duplikat', $this->duplicateCode()),
            $this->check('periode_bukan_awal_bulan', 'Periode jadwal bukan tanggal pertama bulan', $this->periodNotFirstDay()),
            $this->check('snapshot_invalid', 'Snapshot nominal/interval/kode jadwal invalid', $this->invalidSnapshots()),
            $this->check('jadwal_sebelum_eligible', 'Jadwal sebelum tanggal bergabung/siklus/master berlaku', $this->beforeEligibleDate()),
            $this->check('jadwal_setelah_nonaktif', 'Jadwal dibuat setelah tanggal nonaktif Anggota', $this->afterInactiveDate()),
            $this->check('jadwal_tanpa_simpanan', 'Jadwal tanpa transaksi Simpanan immutable', $this->scheduleWithoutSimpanan()),
            $this->check('simpanan_wajib_tanpa_jadwal', 'Transaksi Simpanan Wajib tanpa jadwal', $this->wajibSimpananWithoutSchedule()),
            $this->check('simpanan_ganda_jadwal', 'Lebih dari satu transaksi Simpanan untuk satu jadwal', $this->duplicateSimpananForSchedule()),
            $this->check('ledger_aktif_ganda', 'Ledger aktif ganda untuk satu jadwal Wajib', $this->duplicateActiveLedger()),
            $this->check('ledger_nominal_mismatch', 'Nominal ledger tidak sama dengan snapshot jadwal', $this->ledgerNominalMismatch()),
            $this->check('reserved_tanpa_ledger', 'Jadwal reserved tanpa ledger reserved', $this->reservedWithoutLedger()),
            $this->check('settled_tanpa_payroll', 'Jadwal settled tanpa ledger payroll settled', $this->settledWithoutPayroll()),
            $this->check('ledger_settled_source_belum_settled', 'Ledger settled tetapi jadwal/simpanan belum settled', $this->ledgerSettledButSourceOpen()),
            $this->check('pos_priority_violation', 'POS payroll aktif saat Wajib jatuh tempo belum dialokasikan', $this->posPriorityViolation()),
            $this->check('pos_tunai_ada_ledger_payroll', 'POS non-payroll mempunyai ledger potong gaji', $this->posNonPayrollWithLedger()),
            $this->check('ledger_source_orphan', 'Ledger Simpanan Wajib tanpa source jadwal valid', $this->ledgerSourceOrphan()),
            $this->check('ledger_category_invalid', 'Kategori ledger potong gaji tidak dikenal', $this->invalidLedgerCategory()),
            $this->check('idempotency_wajib_duplicate', 'Duplicate idempotency Simpanan Wajib/ledger/Mutasi/Jurnal', $this->duplicateWajibIdempotency()),
            $this->check('posting_payroll_wajib_invalid', 'Mutasi/Jurnal payroll Simpanan Wajib hilang, ganda, atau orphan', $this->invalidPayrollPosting()),
            $this->check('jurnal_wajib_tidak_seimbang', 'Jurnal Simpanan Wajib/payroll tidak seimbang', $this->unbalancedWajibJournal()),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight Simpanan Wajib');
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
            $this->error('Preflight Simpanan Wajib menemukan konflik kritis. Lakukan rekonsiliasi manual; command ini tidak menulis database.');

            return self::FAILURE;
        }

        $this->info('Preflight Simpanan Wajib bersih: tidak ada konflik kritis.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count, bool $critical = true): array
    {
        return compact('code', 'label', 'count', 'critical');
    }

    private function schemaMissing(): int
    {
        if (! $this->hasTables(['jadwal_simpanan_wajib', 'simpanan', 'pemakaian_potong_gaji'])) {
            return 1;
        }

        foreach (['jadwal_simpanan_wajib_id', 'anggota_id', 'jenis_simpanan_id'] as $column) {
            if (! Schema::hasColumn('simpanan', $column)) {
                return 1;
            }
        }

        return 0;
    }

    private function duplicateSchedule(): int
    {
        if (! Schema::hasTable('jadwal_simpanan_wajib')) {
            return 0;
        }

        return DB::table('jadwal_simpanan_wajib')
            ->select('anggota_id', 'siklus_keanggotaan_id', 'jenis_simpanan_id', 'periode', DB::raw('COUNT(*) as total'))
            ->groupBy('anggota_id', 'siklus_keanggotaan_id', 'jenis_simpanan_id', 'periode')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function duplicateCode(): int
    {
        if (! Schema::hasTable('jadwal_simpanan_wajib')) {
            return 0;
        }

        return DB::table('jadwal_simpanan_wajib')
            ->select('kode_tagihan', DB::raw('COUNT(*) as total'))
            ->groupBy('kode_tagihan')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function periodNotFirstDay(): int
    {
        if (! Schema::hasTable('jadwal_simpanan_wajib')) {
            return 0;
        }

        return DB::table('jadwal_simpanan_wajib')
            ->get(['periode'])
            ->filter(fn ($row) => CarbonImmutable::parse((string) $row->periode)->day !== 1)
            ->count();
    }

    private function invalidSnapshots(): int
    {
        if (! Schema::hasTable('jadwal_simpanan_wajib')) {
            return 0;
        }

        return DB::table('jadwal_simpanan_wajib')
            ->where(function ($query): void {
                $query->where('nominal_snapshot', '<=', 0)
                    ->orWhere('interval_bulan_snapshot', '<', 1)
                    ->orWhere('interval_bulan_snapshot', '>', 12)
                    ->orWhere('kode_jenis_snapshot', '!=', JenisSimpanan::KODE_SIMPANAN_WAJIB)
                    ->orWhereNull('nama_jenis_snapshot');
            })
            ->count();
    }

    private function beforeEligibleDate(): int
    {
        if (! $this->hasTables(['jadwal_simpanan_wajib', 'anggota', 'jenis_simpanan', 'siklus_keanggotaan'])) {
            return 0;
        }

        return DB::table('jadwal_simpanan_wajib as j')
            ->join('anggota as a', 'a.id', '=', 'j.anggota_id')
            ->join('jenis_simpanan as js', 'js.id', '=', 'j.jenis_simpanan_id')
            ->leftJoin('siklus_keanggotaan as sk', 'sk.id', '=', 'j.siklus_keanggotaan_id')
            ->get(['j.periode', 'a.tanggal_bergabung', 'js.berlaku_mulai', 'sk.tanggal_mulai'])
            ->filter(function ($row): bool {
                $periode = CarbonImmutable::parse((string) $row->periode)->startOfMonth();
                $dates = collect([$row->tanggal_bergabung, $row->berlaku_mulai, $row->tanggal_mulai])
                    ->filter()
                    ->map(fn ($date) => CarbonImmutable::parse((string) $date)->startOfMonth());
                $eligible = $dates->max();

                return $eligible && $periode->lessThan($eligible);
            })
            ->count();
    }

    private function afterInactiveDate(): int
    {
        if (! $this->hasTables(['jadwal_simpanan_wajib', 'anggota'])) {
            return 0;
        }

        return DB::table('jadwal_simpanan_wajib as j')
            ->join('anggota as a', 'a.id', '=', 'j.anggota_id')
            ->whereNotNull('a.tanggal_nonaktif')
            ->get(['j.periode', 'a.tanggal_nonaktif'])
            ->filter(fn ($row) => CarbonImmutable::parse((string) $row->periode)->startOfMonth()
                ->greaterThan(CarbonImmutable::parse((string) $row->tanggal_nonaktif)->startOfMonth()))
            ->count();
    }

    private function scheduleWithoutSimpanan(): int
    {
        if (! $this->hasTables(['jadwal_simpanan_wajib', 'simpanan']) || ! Schema::hasColumn('simpanan', 'jadwal_simpanan_wajib_id')) {
            return 0;
        }

        return DB::table('jadwal_simpanan_wajib as j')
            ->leftJoin('simpanan as s', 's.jadwal_simpanan_wajib_id', '=', 'j.id')
            ->whereNull('s.id')
            ->count('j.id');
    }

    private function wajibSimpananWithoutSchedule(): int
    {
        if (! Schema::hasTable('simpanan') || ! Schema::hasColumn('simpanan', 'jadwal_simpanan_wajib_id')) {
            return 0;
        }

        return DB::table('simpanan')
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->whereNull('jadwal_simpanan_wajib_id')
            ->count();
    }

    private function duplicateSimpananForSchedule(): int
    {
        if (! Schema::hasTable('simpanan') || ! Schema::hasColumn('simpanan', 'jadwal_simpanan_wajib_id')) {
            return 0;
        }

        return DB::table('simpanan')
            ->whereNotNull('jadwal_simpanan_wajib_id')
            ->select('jadwal_simpanan_wajib_id', DB::raw('COUNT(*) as total'))
            ->groupBy('jadwal_simpanan_wajib_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function duplicateActiveLedger(): int
    {
        if (! Schema::hasTable('pemakaian_potong_gaji')) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji')
            ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
            ->where('source_type', JadwalSimpananWajib::class)
            ->whereIn('status', [
                PemakaianPotongGaji::STATUS_RESERVED,
                PemakaianPotongGaji::STATUS_CONSUMED,
                PemakaianPotongGaji::STATUS_SETTLED,
            ])
            ->select('source_id', DB::raw('COUNT(*) as total'))
            ->groupBy('source_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function ledgerNominalMismatch(): int
    {
        if (! $this->hasTables(['pemakaian_potong_gaji', 'jadwal_simpanan_wajib'])) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as p')
            ->join('jadwal_simpanan_wajib as j', 'j.id', '=', 'p.source_id')
            ->where('p.kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
            ->where('p.source_type', JadwalSimpananWajib::class)
            ->whereRaw('ABS(p.nominal - j.nominal_snapshot) > 0.01')
            ->count();
    }

    private function reservedWithoutLedger(): int
    {
        if (! $this->hasTables(['jadwal_simpanan_wajib', 'pemakaian_potong_gaji'])) {
            return 0;
        }

        return DB::table('jadwal_simpanan_wajib as j')
            ->leftJoin('pemakaian_potong_gaji as p', function ($join): void {
                $join->on('p.source_id', '=', 'j.id')
                    ->where('p.source_type', '=', JadwalSimpananWajib::class)
                    ->where('p.kategori', '=', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
                    ->where('p.status', '=', PemakaianPotongGaji::STATUS_RESERVED);
            })
            ->where('j.status', JadwalSimpananWajib::STATUS_RESERVED)
            ->whereNull('p.id')
            ->count('j.id');
    }

    private function settledWithoutPayroll(): int
    {
        if (! $this->hasTables(['jadwal_simpanan_wajib', 'pemakaian_potong_gaji'])) {
            return 0;
        }

        return DB::table('jadwal_simpanan_wajib as j')
            ->leftJoin('pemakaian_potong_gaji as p', function ($join): void {
                $join->on('p.source_id', '=', 'j.id')
                    ->where('p.source_type', '=', JadwalSimpananWajib::class)
                    ->where('p.kategori', '=', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
                    ->where('p.status', '=', PemakaianPotongGaji::STATUS_SETTLED);
            })
            ->where('j.status', JadwalSimpananWajib::STATUS_SETTLED)
            ->whereNull('p.id')
            ->count('j.id');
    }

    private function ledgerSettledButSourceOpen(): int
    {
        if (! $this->hasTables(['pemakaian_potong_gaji', 'jadwal_simpanan_wajib', 'simpanan'])
            || ! Schema::hasColumn('simpanan', 'jadwal_simpanan_wajib_id')) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as p')
            ->leftJoin('jadwal_simpanan_wajib as j', 'j.id', '=', 'p.source_id')
            ->leftJoin('simpanan as s', 's.jadwal_simpanan_wajib_id', '=', 'j.id')
            ->where('p.kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
            ->where('p.source_type', JadwalSimpananWajib::class)
            ->where('p.status', PemakaianPotongGaji::STATUS_SETTLED)
            ->where(function ($query): void {
                $query->whereNull('j.id')
                    ->orWhere('j.status', '!=', JadwalSimpananWajib::STATUS_SETTLED)
                    ->orWhereNull('s.id')
                    ->orWhere('s.status', '!=', Simpanan::STATUS_SETTLED);
            })
            ->count('p.id');
    }

    private function posPriorityViolation(): int
    {
        if (! $this->hasTables(['pemakaian_potong_gaji', 'limit_potong_gaji_anggota', 'periode_potong_gaji', 'jadwal_simpanan_wajib'])) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as pg')
            ->join('limit_potong_gaji_anggota as l', 'l.id', '=', 'pg.limit_potong_gaji_anggota_id')
            ->join('periode_potong_gaji as pp', 'pp.id', '=', 'l.periode_potong_gaji_id')
            ->join('jadwal_simpanan_wajib as j', function ($join): void {
                $join->on('j.anggota_id', '=', 'l.anggota_id')
                    ->on('j.periode', '<=', 'pp.periode');
            })
            ->where('pg.kategori', PemakaianPotongGaji::KATEGORI_POS)
            ->whereIn('pg.status', [PemakaianPotongGaji::STATUS_CONSUMED, PemakaianPotongGaji::STATUS_SETTLED])
            ->where('j.status', JadwalSimpananWajib::STATUS_OUTSTANDING)
            ->distinct()
            ->count('pg.id');
    }

    private function posNonPayrollWithLedger(): int
    {
        if (! $this->hasTables(['pembayaran', 'pemakaian_potong_gaji']) || ! Schema::hasColumn('pembayaran', 'pemakaian_potong_gaji_id')) {
            return 0;
        }

        return DB::table('pembayaran')
            ->whereNotNull('pemakaian_potong_gaji_id')
            ->where('metode_pembayaran', '!=', 'potong_gaji')
            ->count();
    }

    private function ledgerSourceOrphan(): int
    {
        if (! $this->hasTables(['pemakaian_potong_gaji', 'jadwal_simpanan_wajib'])) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as p')
            ->leftJoin('jadwal_simpanan_wajib as j', 'j.id', '=', 'p.source_id')
            ->where('p.kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
            ->where('p.source_type', JadwalSimpananWajib::class)
            ->whereNull('j.id')
            ->count('p.id');
    }

    private function invalidLedgerCategory(): int
    {
        if (! Schema::hasTable('pemakaian_potong_gaji')) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji')
            ->whereNotIn('kategori', [
                PemakaianPotongGaji::KATEGORI_CICILAN,
                PemakaianPotongGaji::KATEGORI_SIMPANAN_POKOK,
                PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB,
                PemakaianPotongGaji::KATEGORI_POS,
                PemakaianPotongGaji::KATEGORI_JASA_PRINT,
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
                ->where('idempotency_key', 'like', 'simpanan-wajib:%')
                ->select('idempotency_key', DB::raw('COUNT(*) as total'))
                ->groupBy('idempotency_key')
                ->having('total', '>', 1)
                ->get()
                ->count();
        }

        return $total;
    }

    private function invalidPayrollPosting(): int
    {
        if (! $this->hasTables(['pemakaian_potong_gaji', 'mutasi_kas', 'jurnal_umum'])) {
            return 0;
        }

        $issues = DB::table('pemakaian_potong_gaji')
            ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
            ->where('source_type', JadwalSimpananWajib::class)
            ->where('status', PemakaianPotongGaji::STATUS_SETTLED)
            ->get(['id'])
            ->filter(function ($ledger): bool {
                $mutasiCount = DB::table('mutasi_kas')
                    ->where('idempotency_key', 'simpanan-wajib:payroll:mutasi:' . $ledger->id)
                    ->count();
                $jurnalCount = DB::table('jurnal_umum')
                    ->where('idempotency_key', 'simpanan-wajib:payroll:jurnal:' . $ledger->id)
                    ->count();

                return $mutasiCount !== 1 || $jurnalCount !== 1;
            })
            ->count();

        $issues += DB::table('mutasi_kas as m')
            ->leftJoin('pemakaian_potong_gaji as p', 'p.id', '=', 'm.referensi_id')
            ->where('m.idempotency_key', 'like', 'simpanan-wajib:payroll:mutasi:%')
            ->where(function ($query): void {
                $query->where('m.referensi_tipe', '!=', PemakaianPotongGaji::class)
                    ->orWhereNull('p.id')
                    ->orWhere('p.kategori', '!=', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB);
            })
            ->count();

        $issues += DB::table('jurnal_umum as j')
            ->leftJoin('pemakaian_potong_gaji as p', 'p.id', '=', 'j.referensi_id')
            ->where('j.idempotency_key', 'like', 'simpanan-wajib:payroll:jurnal:%')
            ->where(function ($query): void {
                $query->where('j.referensi_tipe', '!=', PemakaianPotongGaji::class)
                    ->orWhereNull('p.id')
                    ->orWhere('p.kategori', '!=', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB);
            })
            ->count();

        return $issues;
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
                    ->orWhere('j.idempotency_key', 'like', 'PG-SWJ-%');
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
