<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightKeanggotaanCommand extends Command
{
    protected $signature = 'koperasi:preflight-keanggotaan';

    protected $description = 'Read-only preflight lifecycle karyawan keluar, siklus keanggotaan, dan pengembalian Simpanan Pokok.';

    /** @var array<int, array{key:string,label:string,count:int,critical:bool}> */
    private array $results = [];

    public function handle(): int
    {
        $this->info('Preflight Keanggotaan KBSM (read-only)');

        if (! $this->hasTables(['anggota', 'karyawan', 'siklus_keanggotaan', 'penyelesaian_keanggotaan', 'penyelesaian_keanggotaan_detail'])) {
            $this->warn('Schema lifecycle keanggotaan belum lengkap.');

            return self::FAILURE;
        }

        $this->check('anggota_aktif_tanpa_siklus', 'Anggota aktif tanpa siklus aktif', $this->activeAnggotaWithoutActiveCycle());
        $this->check('anggota_nonaktif_siklus_aktif', 'Anggota nonaktif masih memiliki siklus aktif', $this->inactiveAnggotaWithActiveCycle());
        $this->check('siklus_aktif_ganda', 'Lebih dari satu siklus aktif per Anggota', $this->duplicateActiveCycles());
        $this->check('siklus_closed_tanpa_penyelesaian', 'Siklus closed tanpa penyelesaian aktif/final', $this->closedCycleWithoutSettlement());
        $this->check('penyelesaian_ganda', 'Lebih dari satu penyelesaian aktif/final per siklus', $this->duplicateSettlementPerCycle());
        $this->check('simpanan_pokok_ganda_siklus', 'Lebih dari satu Simpanan Pokok valid per siklus', $this->duplicatePokokPerCycle());
        $this->check('simpanan_pokok_tanpa_siklus', 'Simpanan Pokok valid tanpa siklus', $this->validPokokWithoutCycle());
        $this->check('settlement_detail_mismatch', 'Total detail penyelesaian tidak sesuai total kewajiban snapshot', $this->settlementDetailMismatch());
        $this->check('settlement_completed_sisa', 'Penyelesaian completed masih punya sisa kewajiban', $this->completedWithRemainingObligation());
        $this->check('refund_tanpa_mutasi', 'Penyelesaian completed dengan refund tanpa Mutasi Kas', $this->completedRefundWithoutMutasi());
        $this->check('pinjaman_sisa_jadwal_mismatch', 'Sisa Pinjaman tidak sesuai jadwal unpaid/partial', $this->pinjamanScheduleMismatch());
        $this->check('offset_jadwal_tanpa_settlement', 'Jadwal cicilan offset tanpa detail penyelesaian Pinjaman', $this->scheduleOffsetWithoutSettlement());
        $this->check('reversal_simpanan_exit_tanpa_record', 'Simpanan Pokok reversed_due_to_exit tanpa reversal', $this->exitReversedPokokWithoutReversal());

        $this->newLine();
        $this->table(['Kode', 'Temuan', 'Count', 'Severity'], array_map(
            fn (array $item): array => [$item['key'], $item['label'], $item['count'], $item['critical'] ? 'critical' : 'warning'],
            $this->results
        ));

        $critical = collect($this->results)->where('critical', true)->sum('count');
        if ($critical > 0) {
            $this->error("Preflight menemukan {$critical} konflik kritis.");

            return self::FAILURE;
        }

        $this->info('Preflight keanggotaan selesai tanpa konflik kritis.');

        return self::SUCCESS;
    }

    private function check(string $key, string $label, int $count, bool $critical = true): void
    {
        $this->results[] = compact('key', 'label', 'count', 'critical');
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

    private function activeAnggotaWithoutActiveCycle(): int
    {
        return DB::table('anggota')
            ->where('status', 'aktif')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('siklus_keanggotaan')
                    ->whereColumn('siklus_keanggotaan.anggota_id', 'anggota.id')
                    ->where('siklus_keanggotaan.status', 'active');
            })
            ->count();
    }

    private function inactiveAnggotaWithActiveCycle(): int
    {
        return DB::table('anggota')
            ->join('siklus_keanggotaan', 'siklus_keanggotaan.anggota_id', '=', 'anggota.id')
            ->where('anggota.status', '!=', 'aktif')
            ->where('siklus_keanggotaan.status', 'active')
            ->count();
    }

    private function duplicateActiveCycles(): int
    {
        return DB::query()
            ->fromSub(
                DB::table('siklus_keanggotaan')
                    ->select('anggota_id', DB::raw('COUNT(*) as total'))
                    ->where('status', 'active')
                    ->groupBy('anggota_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicates'
            )
            ->count();
    }

    private function closedCycleWithoutSettlement(): int
    {
        return DB::table('siklus_keanggotaan')
            ->where('status', 'closed')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('penyelesaian_keanggotaan')
                    ->whereColumn('penyelesaian_keanggotaan.siklus_keanggotaan_id', 'siklus_keanggotaan.id')
                    ->where('penyelesaian_keanggotaan.status', '!=', 'cancelled');
            })
            ->count();
    }

    private function duplicateSettlementPerCycle(): int
    {
        return DB::query()
            ->fromSub(
                DB::table('penyelesaian_keanggotaan')
                    ->select('siklus_keanggotaan_id', DB::raw('COUNT(*) as total'))
                    ->where('status', '!=', 'cancelled')
                    ->groupBy('siklus_keanggotaan_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicates'
            )
            ->count();
    }

    private function duplicatePokokPerCycle(): int
    {
        if (! Schema::hasColumn('simpanan', 'siklus_keanggotaan_id')) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table('simpanan')
                    ->select('siklus_keanggotaan_id', DB::raw('COUNT(*) as total'))
                    ->where('kode_jenis_snapshot', 'SIMPANAN_POKOK')
                    ->whereNotIn('status', ['reversed', 'reversed_due_to_exit'])
                    ->whereNotNull('siklus_keanggotaan_id')
                    ->groupBy('siklus_keanggotaan_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicates'
            )
            ->count();
    }

    private function validPokokWithoutCycle(): int
    {
        if (! Schema::hasColumn('simpanan', 'siklus_keanggotaan_id')) {
            return 0;
        }

        return DB::table('simpanan')
            ->where('kode_jenis_snapshot', 'SIMPANAN_POKOK')
            ->whereNotIn('status', ['reversed', 'reversed_due_to_exit'])
            ->whereNull('siklus_keanggotaan_id')
            ->count();
    }

    private function settlementDetailMismatch(): int
    {
        return DB::query()
            ->fromSub(
                DB::table('penyelesaian_keanggotaan')
                    ->leftJoin('penyelesaian_keanggotaan_detail', 'penyelesaian_keanggotaan_detail.penyelesaian_keanggotaan_id', '=', 'penyelesaian_keanggotaan.id')
                    ->select('penyelesaian_keanggotaan.id', 'penyelesaian_keanggotaan.total_kewajiban_awal', DB::raw('COALESCE(SUM(penyelesaian_keanggotaan_detail.nominal_kewajiban_awal), 0) as total_detail'))
                    ->groupBy('penyelesaian_keanggotaan.id', 'penyelesaian_keanggotaan.total_kewajiban_awal'),
                'snapshot'
            )
            ->whereRaw('ABS(CAST(total_kewajiban_awal AS DECIMAL(15,2)) - CAST(total_detail AS DECIMAL(15,2))) > 0.01')
            ->count();
    }

    private function completedWithRemainingObligation(): int
    {
        return DB::table('penyelesaian_keanggotaan')
            ->where('status', 'completed')
            ->whereRaw('CAST(sisa_kewajiban AS DECIMAL(15,2)) > 0')
            ->count();
    }

    private function completedRefundWithoutMutasi(): int
    {
        return DB::table('penyelesaian_keanggotaan')
            ->where('status', 'completed')
            ->whereRaw('CAST(total_refund AS DECIMAL(15,2)) > 0')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('mutasi_kas')
                    ->whereColumn('mutasi_kas.referensi_id', 'penyelesaian_keanggotaan.id')
                    ->where('mutasi_kas.referensi_tipe', 'App\\Models\\PenyelesaianKeanggotaan')
                    ->where('mutasi_kas.tipe', 'keluar');
            })
            ->count();
    }

    private function pinjamanScheduleMismatch(): int
    {
        if (! Schema::hasColumn('jadwal_cicilan_pinjaman', 'nominal_sisa')) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table('pinjaman')
                    ->leftJoin('jadwal_cicilan_pinjaman', function ($join): void {
                        $join->on('jadwal_cicilan_pinjaman.pinjaman_id', '=', 'pinjaman.id')
                            ->whereIn('jadwal_cicilan_pinjaman.status', ['scheduled', 'reserved']);
                    })
                    ->select('pinjaman.id', 'pinjaman.sisa_pinjaman', DB::raw('COALESCE(SUM(COALESCE(jadwal_cicilan_pinjaman.nominal_sisa, jadwal_cicilan_pinjaman.nominal_pokok)), 0) as total_sisa_jadwal'))
                    ->where('pinjaman.status', 'aktif')
                    ->groupBy('pinjaman.id', 'pinjaman.sisa_pinjaman'),
                'snapshot'
            )
            ->whereRaw('ABS(CAST(sisa_pinjaman AS DECIMAL(15,2)) - CAST(total_sisa_jadwal AS DECIMAL(15,2))) > 0.01')
            ->count();
    }

    private function scheduleOffsetWithoutSettlement(): int
    {
        if (! Schema::hasColumn('jadwal_cicilan_pinjaman', 'nominal_offset')) {
            return 0;
        }

        return DB::table('jadwal_cicilan_pinjaman')
            ->join('pinjaman', 'pinjaman.id', '=', 'jadwal_cicilan_pinjaman.pinjaman_id')
            ->whereRaw('CAST(jadwal_cicilan_pinjaman.nominal_offset AS DECIMAL(15,2)) > 0')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('penyelesaian_keanggotaan_detail')
                    ->whereColumn('penyelesaian_keanggotaan_detail.source_id', 'pinjaman.id')
                    ->where('penyelesaian_keanggotaan_detail.source_type', 'App\\Models\\Pinjaman')
                    ->whereRaw('CAST(penyelesaian_keanggotaan_detail.nominal_offset AS DECIMAL(15,2)) > 0');
            })
            ->count();
    }

    private function exitReversedPokokWithoutReversal(): int
    {
        return DB::table('simpanan')
            ->where('status', 'reversed_due_to_exit')
            ->whereNull('reversal_transaksi_id')
            ->count();
    }
}
