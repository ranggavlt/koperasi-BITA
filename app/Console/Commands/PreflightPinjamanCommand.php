<?php

namespace App\Console\Commands;

use App\Models\Pinjaman;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightPinjamanCommand extends Command
{
    protected $signature = 'koperasi:preflight-pinjaman';

    protected $description = 'Audit read-only lifecycle pengajuan, persetujuan, dan pencairan Pinjaman.';

    public function handle(): int
    {
        $checks = [
            $this->check('schema_missing', 'Schema lifecycle Pinjaman belum lengkap', $this->schemaMissing()),
            $this->check('pinjaman_aktif_tanpa_siklus', 'Pinjaman aktif tanpa siklus keanggotaan', $this->activeWithoutCycle()),
            $this->check('pinjaman_siklus_anggota_mismatch', 'Pinjaman merujuk siklus milik Anggota lain', $this->cycleAnggotaMismatch()),
            $this->check('pinjaman_terbuka_ganda', 'Lebih dari satu proses terbuka/aktif per Anggota', $this->openLoanDuplicate()),
            $this->check('nominal_sistem_invalid', 'Pinjaman melebihi Rp5.000.000 atau nominal tidak valid', $this->invalidAmount()),
            $this->check('melebihi_plafon_snapshot', 'Pinjaman lebih besar dari plafon snapshot', $this->exceedsSnapshot()),
            $this->check('bunga_bukan_nol', 'Bunga Pinjaman bukan 0%', $this->nonZeroInterest()),
            $this->check('tenor_invalid', 'Tenor di luar 1-12 bulan', $this->invalidTenor()),
            $this->check('pre_disbursement_posting', 'Draft/diajukan/disetujui sudah memiliki Mutasi/Jurnal/Jadwal', $this->preDisbursementPosting()),
            $this->check('aktif_tanpa_mutasi', 'Pinjaman aktif tanpa Mutasi pencairan', $this->activeWithoutPosting('mutasi_kas')),
            $this->check('aktif_tanpa_jurnal', 'Pinjaman aktif tanpa Jurnal pencairan', $this->activeWithoutPosting('jurnal_umum')),
            $this->check('aktif_tanpa_jadwal', 'Pinjaman aktif tanpa Jadwal Cicilan', $this->activeWithoutSchedule()),
            $this->check('jadwal_total_mismatch', 'Total Jadwal tidak sama dengan pokok Pinjaman', $this->scheduleTotalMismatch()),
            $this->check('sisa_jadwal_mismatch', 'Sisa Pinjaman tidak sama dengan total nominal_sisa jadwal', $this->remainingScheduleMismatch()),
            $this->check('jadwal_status_nominal_invalid', 'Status Jadwal tidak konsisten dengan nominal_sisa', $this->invalidScheduleRemainingStatus()),
            $this->check('kode_duplikat', 'Kode Pinjaman duplikat/kosong', $this->duplicateCode()),
            $this->check('idempotency_duplikat', 'Idempotency Mutasi/Jurnal Pinjaman duplikat', $this->duplicateIdempotency()),
            $this->check('orphan_fk', 'Referensi Anggota/Karyawan/Dompet Pinjaman orphan', $this->orphanReference()),
            $this->check('audit_status_invalid', 'Status dan audit timestamp tidak konsisten', $this->invalidStatusAudit()),
            $this->check('terminal_berposting', 'Pinjaman ditolak/dibatalkan memiliki posting keuangan', $this->terminalWithPosting()),
            $this->check('jurnal_tidak_balance', 'Jurnal Pinjaman tidak balance', $this->unbalancedJournal()),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight Pinjaman');
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
            $this->error('Preflight Pinjaman menemukan konflik kritis. Command ini read-only dan tidak melakukan repair/backfill.');

            return self::FAILURE;
        }

        $this->info('Preflight Pinjaman bersih: tidak ada konflik kritis.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count, bool $critical = true): array
    {
        return compact('code', 'label', 'count', 'critical');
    }

    private function schemaMissing(): int
    {
        if (! $this->hasTables(['pinjaman'])) {
            return 1;
        }

        foreach ([
            'kode_pinjaman',
            'anggota_id',
            'tanggal_pengajuan',
            'submitted_by',
            'submitted_at',
            'approved_by',
            'approved_at',
            'rejected_by',
            'rejected_at',
            'rejection_reason',
            'cancelled_by',
            'cancelled_at',
            'cancellation_reason',
            'disbursed_by',
            'disbursed_at',
            'anggota_pinjaman_terbuka_id',
            'siklus_keanggotaan_id',
        ] as $column) {
            if (! Schema::hasColumn('pinjaman', $column)) {
                return 1;
            }
        }

        return 0;
    }

    private function activeWithoutCycle(): int
    {
        if (! Schema::hasTable('pinjaman') || ! Schema::hasColumn('pinjaman', 'siklus_keanggotaan_id')) {
            return 0;
        }

        return DB::table('pinjaman')
            ->where('status', Pinjaman::STATUS_AKTIF)
            ->whereRaw('CAST(sisa_pinjaman AS DECIMAL(15,2)) > 0')
            ->whereNull('siklus_keanggotaan_id')
            ->count();
    }

    private function cycleAnggotaMismatch(): int
    {
        if (! $this->hasTables(['pinjaman', 'siklus_keanggotaan']) || ! Schema::hasColumn('pinjaman', 'siklus_keanggotaan_id')) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->join('siklus_keanggotaan as s', 's.id', '=', 'p.siklus_keanggotaan_id')
            ->whereNotNull('p.siklus_keanggotaan_id')
            ->whereColumn('s.anggota_id', '!=', 'p.anggota_id')
            ->count('p.id');
    }

    private function openLoanDuplicate(): int
    {
        if (! Schema::hasTable('pinjaman') || ! Schema::hasColumn('pinjaman', 'anggota_id')) {
            return 0;
        }

        return DB::table('pinjaman')
            ->select('anggota_id', DB::raw('COUNT(*) as total'))
            ->whereIn('status', Pinjaman::openStatuses())
            ->whereNotNull('anggota_id')
            ->groupBy('anggota_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function invalidAmount(): int
    {
        if (! Schema::hasTable('pinjaman')) {
            return 0;
        }

        return DB::table('pinjaman')
            ->where(function ($query): void {
                $query->where('jumlah_pinjaman', '<=', 0)
                    ->orWhere('jumlah_pinjaman', '>', 5000000);
            })
            ->count();
    }

    private function exceedsSnapshot(): int
    {
        if (! Schema::hasTable('pinjaman') || ! Schema::hasColumn('pinjaman', 'plafon_pinjaman_snapshot')) {
            return 0;
        }

        return DB::table('pinjaman')
            ->where(function ($query): void {
                $query->whereNull('plafon_pinjaman_snapshot')
                    ->orWhereColumn('jumlah_pinjaman', '>', 'plafon_pinjaman_snapshot');
            })
            ->count();
    }

    private function nonZeroInterest(): int
    {
        if (! Schema::hasTable('pinjaman')) {
            return 0;
        }

        return DB::table('pinjaman')->where('bunga_persen', '!=', 0)->count();
    }

    private function invalidTenor(): int
    {
        if (! Schema::hasTable('pinjaman')) {
            return 0;
        }

        return DB::table('pinjaman')
            ->where(function ($query): void {
                $query->where('tenor_bulan', '<', 1)
                    ->orWhere('tenor_bulan', '>', 12);
            })
            ->count();
    }

    private function preDisbursementPosting(): int
    {
        if (! $this->hasTables(['pinjaman'])) {
            return 0;
        }

        return $this->preDisbursementWith('jadwal_cicilan_pinjaman', 'pinjaman_id')
            + $this->preDisbursementWithReference('mutasi_kas')
            + $this->preDisbursementWithReference('jurnal_umum');
    }

    private function activeWithoutPosting(string $table): int
    {
        if (! $this->hasTables(['pinjaman', $table])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->leftJoin("{$table} as r", function ($join): void {
                $join->on('r.referensi_id', '=', 'p.id')
                    ->where('r.referensi_tipe', '=', Pinjaman::class);
            })
            ->where('p.status', Pinjaman::STATUS_AKTIF)
            ->select('p.id', DB::raw('COUNT(r.id) as total_posting'))
            ->groupBy('p.id')
            ->get()
            ->filter(fn ($row) => (int) $row->total_posting !== 1)
            ->count();
    }

    private function activeWithoutSchedule(): int
    {
        if (! $this->hasTables(['pinjaman', 'jadwal_cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->leftJoin('jadwal_cicilan_pinjaman as j', 'j.pinjaman_id', '=', 'p.id')
            ->where('p.status', Pinjaman::STATUS_AKTIF)
            ->whereNull('j.id')
            ->count('p.id');
    }

    private function scheduleTotalMismatch(): int
    {
        if (! $this->hasTables(['pinjaman', 'jadwal_cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->leftJoin('jadwal_cicilan_pinjaman as j', 'j.pinjaman_id', '=', 'p.id')
            ->whereIn('p.status', [Pinjaman::STATUS_AKTIF, Pinjaman::STATUS_LUNAS])
            ->select('p.id', 'p.jumlah_pinjaman', DB::raw('COALESCE(SUM(j.nominal_pokok), 0) as total_jadwal'))
            ->groupBy('p.id', 'p.jumlah_pinjaman')
            ->get()
            ->filter(fn ($row) => number_format((float) $row->jumlah_pinjaman, 2, '.', '') !== number_format((float) $row->total_jadwal, 2, '.', ''))
            ->count();
    }

    private function remainingScheduleMismatch(): int
    {
        if (! $this->hasTables(['pinjaman', 'jadwal_cicilan_pinjaman']) || ! Schema::hasColumn('jadwal_cicilan_pinjaman', 'nominal_sisa')) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->leftJoin('jadwal_cicilan_pinjaman as j', function ($join): void {
                $join->on('j.pinjaman_id', '=', 'p.id')
                    ->where('j.status', '!=', 'cancelled');
            })
            ->whereIn('p.status', [Pinjaman::STATUS_AKTIF, Pinjaman::STATUS_LUNAS])
            ->select('p.id', 'p.sisa_pinjaman', DB::raw('COALESCE(SUM(COALESCE(j.nominal_sisa, j.nominal_pokok)), 0) as total_sisa'))
            ->groupBy('p.id', 'p.sisa_pinjaman')
            ->get()
            ->filter(fn ($row) => number_format((float) $row->sisa_pinjaman, 2, '.', '') !== number_format((float) $row->total_sisa, 2, '.', ''))
            ->count();
    }

    private function invalidScheduleRemainingStatus(): int
    {
        if (! Schema::hasTable('jadwal_cicilan_pinjaman') || ! Schema::hasColumn('jadwal_cicilan_pinjaman', 'nominal_sisa')) {
            return 0;
        }

        $paidWithRemaining = DB::table('jadwal_cicilan_pinjaman')
            ->where('status', 'paid')
            ->whereRaw('CAST(COALESCE(nominal_sisa, 0) AS DECIMAL(15,2)) > 0.01')
            ->count();

        $openWithoutRemaining = DB::table('jadwal_cicilan_pinjaman')
            ->whereIn('status', ['scheduled', 'reserved'])
            ->whereRaw('CAST(COALESCE(nominal_sisa, nominal_pokok) AS DECIMAL(15,2)) <= 0')
            ->count();

        return $paidWithRemaining + $openWithoutRemaining;
    }

    private function duplicateCode(): int
    {
        if (! Schema::hasTable('pinjaman') || ! Schema::hasColumn('pinjaman', 'kode_pinjaman')) {
            return 0;
        }

        $empty = DB::table('pinjaman')
            ->where(function ($query): void {
                $query->whereNull('kode_pinjaman')->orWhere('kode_pinjaman', '');
            })
            ->count();

        $duplicate = DB::table('pinjaman')
            ->select('kode_pinjaman', DB::raw('COUNT(*) as total'))
            ->whereNotNull('kode_pinjaman')
            ->groupBy('kode_pinjaman')
            ->having('total', '>', 1)
            ->get()
            ->count();

        return $empty + $duplicate;
    }

    private function duplicateIdempotency(): int
    {
        return collect(['mutasi_kas', 'jurnal_umum'])
            ->filter(fn ($table) => Schema::hasTable($table) && Schema::hasColumn($table, 'idempotency_key'))
            ->sum(fn ($table) => DB::query()->fromSub(
                DB::table($table)
                    ->select('idempotency_key', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('idempotency_key')
                    ->where('idempotency_key', 'like', 'pinjaman:%')
                    ->groupBy('idempotency_key')
                    ->having('total', '>', 1),
                'd'
            )->count());
    }

    private function orphanReference(): int
    {
        if (! $this->hasTables(['pinjaman', 'anggota', 'karyawan'])) {
            return 0;
        }

        $query = DB::table('pinjaman as p')
            ->leftJoin('anggota as a', 'a.id', '=', 'p.anggota_id')
            ->leftJoin('karyawan as k', 'k.id', '=', 'p.karyawan_id')
            ->where(function ($query): void {
                $query->whereNull('a.id')->orWhereNull('k.id');
            });

        $count = $query->count('p.id');

        if ($this->hasTables(['dompet_koperasi']) && Schema::hasColumn('pinjaman', 'dompet_id')) {
            $count += DB::table('pinjaman as p')
                ->leftJoin('dompet_koperasi as d', 'd.id', '=', 'p.dompet_id')
                ->whereIn('p.status', [Pinjaman::STATUS_AKTIF, Pinjaman::STATUS_LUNAS])
                ->where(function ($query): void {
                    $query->whereNull('p.dompet_id')->orWhereNull('d.id');
                })
                ->count('p.id');
        }

        return $count;
    }

    private function invalidStatusAudit(): int
    {
        if (! Schema::hasTable('pinjaman')) {
            return 0;
        }

        $validStatuses = array_keys(Pinjaman::statusLabels());

        return DB::table('pinjaman')
            ->whereNotIn('status', $validStatuses)
            ->orWhere(function ($query): void {
                $query->where('status', Pinjaman::STATUS_DIAJUKAN)->whereNull('submitted_at');
            })
            ->orWhere(function ($query): void {
                $query->where('status', Pinjaman::STATUS_DISETUJUI)->whereNull('approved_at');
            })
            ->orWhere(function ($query): void {
                $query->where('status', Pinjaman::STATUS_AKTIF)->whereNull('disbursed_at');
            })
            ->orWhere(function ($query): void {
                $query->where('status', Pinjaman::STATUS_DITOLAK)->where(function ($nested): void {
                    $nested->whereNull('rejected_at')->orWhereNull('rejection_reason');
                });
            })
            ->orWhere(function ($query): void {
                $query->where('status', Pinjaman::STATUS_DIBATALKAN)->where(function ($nested): void {
                    $nested->whereNull('cancelled_at')->orWhereNull('cancellation_reason');
                });
            })
            ->count();
    }

    private function terminalWithPosting(): int
    {
        return $this->terminalWithReference('mutasi_kas')
            + $this->terminalWithReference('jurnal_umum')
            + $this->terminalWith('jadwal_cicilan_pinjaman', 'pinjaman_id');
    }

    private function unbalancedJournal(): int
    {
        if (! $this->hasTables(['jurnal_umum', 'jurnal_umum_detail'])) {
            return 0;
        }

        return DB::query()->fromSub(
            DB::table('jurnal_umum as j')
                ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
                ->where('j.referensi_tipe', Pinjaman::class)
                ->select('j.id', DB::raw('SUM(d.debit) as debit'), DB::raw('SUM(d.kredit) as kredit'))
                ->groupBy('j.id'),
            'balance'
        )
            ->whereRaw('ABS(CAST(debit AS DECIMAL(15,2)) - CAST(kredit AS DECIMAL(15,2))) > 0.01')
            ->count();
    }

    private function preDisbursementWith(string $table, string $foreignKey): int
    {
        if (! $this->hasTables(['pinjaman', $table])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->join("{$table} as r", "r.{$foreignKey}", '=', 'p.id')
            ->whereIn('p.status', [Pinjaman::STATUS_DRAFT, Pinjaman::STATUS_DIAJUKAN, Pinjaman::STATUS_DISETUJUI])
            ->distinct()
            ->count('p.id');
    }

    private function preDisbursementWithReference(string $table): int
    {
        if (! $this->hasTables(['pinjaman', $table])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->join("{$table} as r", function ($join): void {
                $join->on('r.referensi_id', '=', 'p.id')
                    ->where('r.referensi_tipe', '=', Pinjaman::class);
            })
            ->whereIn('p.status', [Pinjaman::STATUS_DRAFT, Pinjaman::STATUS_DIAJUKAN, Pinjaman::STATUS_DISETUJUI])
            ->distinct()
            ->count('p.id');
    }

    private function terminalWith(string $table, string $foreignKey): int
    {
        if (! $this->hasTables(['pinjaman', $table])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->join("{$table} as r", "r.{$foreignKey}", '=', 'p.id')
            ->whereIn('p.status', [Pinjaman::STATUS_DITOLAK, Pinjaman::STATUS_DIBATALKAN])
            ->distinct()
            ->count('p.id');
    }

    private function terminalWithReference(string $table): int
    {
        if (! $this->hasTables(['pinjaman', $table])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->join("{$table} as r", function ($join): void {
                $join->on('r.referensi_id', '=', 'p.id')
                    ->where('r.referensi_tipe', '=', Pinjaman::class);
            })
            ->whereIn('p.status', [Pinjaman::STATUS_DITOLAK, Pinjaman::STATUS_DIBATALKAN])
            ->distinct()
            ->count('p.id');
    }

    /**
     * @param array<int, string> $tables
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
