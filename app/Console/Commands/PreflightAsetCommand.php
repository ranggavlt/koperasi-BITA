<?php

namespace App\Console\Commands;

use App\Models\AsetKoperasi;
use App\Services\AsetKoperasiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightAsetCommand extends Command
{
    protected $signature = 'koperasi:preflight-aset';

    protected $description = 'Audit read-only kesiapan data Master Aset Mobil dan Printer Koperasi.';

    public function handle(AsetKoperasiService $service): int
    {
        $checks = [
            $this->check('kode_duplikat', 'Kode aset duplikat', $this->duplicates('aset_koperasi', 'kode_aset')),
            $this->check('format_kode_invalid', 'Format kode aset tidak sesuai jenis', $this->invalidCodeFormat()),
            $this->check('mobil_tanpa_detail', 'Aset mobil tanpa detail mobil', $this->assetWithoutDetail(AsetKoperasi::JENIS_MOBIL)),
            $this->check('mobil_tarif_invalid', 'Mobil aktif/tersedia tanpa Tarif Sewa Harian valid', $this->invalidMobilTariff()),
            $this->check('printer_tanpa_detail', 'Aset printer tanpa detail printer', $this->assetWithoutDetail(AsetKoperasi::JENIS_PRINTER)),
            $this->check('aset_detail_ganda', 'Aset mempunyai detail mobil dan printer sekaligus', $this->assetWithBothDetails()),
            $this->check('detail_mobil_orphan', 'Detail mobil tanpa parent aset', $this->detailOrphan('aset_mobil')),
            $this->check('detail_printer_orphan', 'Detail printer tanpa parent aset', $this->detailOrphan('aset_printer')),
            $this->check('plat_duplikat', 'Plat nomor mobil duplikat', $this->duplicates('aset_mobil', 'plat_nomor')),
            $this->check('nomor_seri_duplikat', 'Nomor seri printer duplikat', $this->duplicates('aset_printer', 'nomor_seri')),
            $this->check('status_invalid', 'Status aset tidak valid', $this->invalidStatus()),
            $this->check('nonaktif_tanpa_waktu', 'Aset nonaktif tanpa nonaktif_at', $this->nonaktifWithoutTimestamp()),
            $this->check('aktif_dengan_nonaktif_at', 'Aset aktif masih mempunyai nonaktif_at', $this->activeWithNonaktifTimestamp()),
            $this->check('counter_dibawah_kode', 'Counter aset lebih kecil dari nomor kode yang sudah ada', $this->counterBelowMaxCode()),
            $this->check('aset_transaksi_masih_deletable', 'Aset dengan transaksi/histori masih terdeteksi dapat dihapus', $this->assetWithDependenciesStillDeletable($service)),
            $this->check('referensi_aset_orphan', 'Referensi transaksi sewa/aset orphan', $this->orphanFutureReferences()),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight Master Aset Koperasi');
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
            $this->error('Preflight aset menemukan konflik kritis. Data aset harus direkonsiliasi tanpa menebak mapping.');

            return self::FAILURE;
        }

        $this->info('Preflight aset bersih: tidak ada konflik kritis yang terdeteksi.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count, bool $critical = true): array
    {
        return compact('code', 'label', 'count', 'critical');
    }

    private function duplicates(string $table, string $column): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)
            ->select($column, DB::raw('COUNT(*) as total'))
            ->whereNotNull($column)
            ->groupBy($column)
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function invalidCodeFormat(): int
    {
        if (! Schema::hasTable('aset_koperasi')) {
            return 0;
        }

        return DB::table('aset_koperasi')
            ->select('jenis_aset', 'kode_aset')
            ->get()
            ->filter(function ($row): bool {
                return match ($row->jenis_aset) {
                    AsetKoperasi::JENIS_MOBIL => ! preg_match('/^MBL-\d{4}$/', (string) $row->kode_aset),
                    AsetKoperasi::JENIS_PRINTER => ! preg_match('/^PRT-\d{4}$/', (string) $row->kode_aset),
                    default => true,
                };
            })
            ->count();
    }

    private function assetWithoutDetail(string $jenis): int
    {
        if (! $this->hasTables(['aset_koperasi', 'aset_mobil', 'aset_printer'])) {
            return 0;
        }

        $detailTable = $jenis === AsetKoperasi::JENIS_MOBIL ? 'aset_mobil' : 'aset_printer';

        return DB::table('aset_koperasi as a')
            ->leftJoin("{$detailTable} as d", 'd.aset_koperasi_id', '=', 'a.id')
            ->where('a.jenis_aset', $jenis)
            ->whereNull('d.id')
            ->count('a.id');
    }

    private function assetWithBothDetails(): int
    {
        if (! $this->hasTables(['aset_koperasi', 'aset_mobil', 'aset_printer'])) {
            return 0;
        }

        return DB::table('aset_koperasi as a')
            ->join('aset_mobil as m', 'm.aset_koperasi_id', '=', 'a.id')
            ->join('aset_printer as p', 'p.aset_koperasi_id', '=', 'a.id')
            ->count('a.id');
    }

    private function invalidMobilTariff(): int
    {
        if (! $this->hasTables(['aset_koperasi', 'aset_mobil']) || ! Schema::hasColumn('aset_mobil', 'tarif_sewa_harian')) {
            return 0;
        }

        return DB::table('aset_koperasi as a')
            ->join('aset_mobil as m', 'm.aset_koperasi_id', '=', 'a.id')
            ->where('a.jenis_aset', AsetKoperasi::JENIS_MOBIL)
            ->whereIn('a.status', [AsetKoperasi::STATUS_TERSEDIA, AsetKoperasi::STATUS_DIGUNAKAN_DISEWA])
            ->where(fn ($query) => $query->whereNull('m.tarif_sewa_harian')->orWhere('m.tarif_sewa_harian', '<=', 0))
            ->count('a.id');
    }

    private function detailOrphan(string $table): int
    {
        if (! $this->hasTables([$table, 'aset_koperasi'])) {
            return 0;
        }

        return DB::table("{$table} as d")
            ->leftJoin('aset_koperasi as a', 'a.id', '=', 'd.aset_koperasi_id')
            ->whereNull('a.id')
            ->count('d.id');
    }

    private function invalidStatus(): int
    {
        if (! Schema::hasTable('aset_koperasi')) {
            return 0;
        }

        return DB::table('aset_koperasi')
            ->whereNotIn('status', AsetKoperasi::statuses())
            ->count();
    }

    private function nonaktifWithoutTimestamp(): int
    {
        if (! Schema::hasTable('aset_koperasi')) {
            return 0;
        }

        return DB::table('aset_koperasi')
            ->where('status', AsetKoperasi::STATUS_NONAKTIF)
            ->whereNull('nonaktif_at')
            ->count();
    }

    private function activeWithNonaktifTimestamp(): int
    {
        if (! Schema::hasTable('aset_koperasi')) {
            return 0;
        }

        return DB::table('aset_koperasi')
            ->where('status', '!=', AsetKoperasi::STATUS_NONAKTIF)
            ->whereNotNull('nonaktif_at')
            ->count();
    }

    private function counterBelowMaxCode(): int
    {
        if (! $this->hasTables(['aset_koperasi', 'nomor_urut_aset'])) {
            return 0;
        }

        $issues = 0;

        foreach ([AsetKoperasi::JENIS_MOBIL => 'MBL', AsetKoperasi::JENIS_PRINTER => 'PRT'] as $jenis => $prefix) {
            $maxCodeNumber = DB::table('aset_koperasi')
                ->where('jenis_aset', $jenis)
                ->pluck('kode_aset')
                ->map(fn ($kode) => preg_match("/^{$prefix}-(\d{4})$/", (string) $kode, $match) ? (int) $match[1] : 0)
                ->max() ?? 0;

            $counter = (int) (DB::table('nomor_urut_aset')
                ->where('jenis_aset', $jenis)
                ->value('last_number') ?? 0);

            if ($counter < $maxCodeNumber) {
                $issues++;
            }
        }

        return $issues;
    }

    private function assetWithDependenciesStillDeletable(AsetKoperasiService $service): int
    {
        if (! Schema::hasTable('aset_koperasi')) {
            return 0;
        }

        return AsetKoperasi::query()
            ->with(['mobil', 'printer'])
            ->get()
            ->filter(function (AsetKoperasi $aset) use ($service): bool {
                $dependencies = $service->dependencyCounts($aset);
                $guard = $service->canDelete($aset);

                return array_sum($dependencies) > 0 && $guard['allowed'];
            })
            ->count();
    }

    private function orphanFutureReferences(): int
    {
        $contracts = [
            ['table' => 'sewa_mobil', 'column' => 'aset_koperasi_id', 'parent' => 'aset_koperasi'],
            ['table' => 'sewa_mobil', 'column' => 'aset_mobil_id', 'parent' => 'aset_mobil'],
            ['table' => 'transaksi_sewa_mobil', 'column' => 'aset_koperasi_id', 'parent' => 'aset_koperasi'],
            ['table' => 'transaksi_sewa_mobil', 'column' => 'aset_mobil_id', 'parent' => 'aset_mobil'],
            ['table' => 'sewa_hardware', 'column' => 'aset_koperasi_id', 'parent' => 'aset_koperasi'],
            ['table' => 'sewa_hardware', 'column' => 'aset_printer_id', 'parent' => 'aset_printer'],
            ['table' => 'sewa_hardware_detail', 'column' => 'aset_koperasi_id', 'parent' => 'aset_koperasi'],
            ['table' => 'beban_operasional_detail', 'column' => 'aset_koperasi_id', 'parent' => 'aset_koperasi'],
            ['table' => 'transaksi_sewa_hardware', 'column' => 'aset_koperasi_id', 'parent' => 'aset_koperasi'],
            ['table' => 'transaksi_sewa_hardware', 'column' => 'aset_printer_id', 'parent' => 'aset_printer'],
        ];

        $issues = 0;

        foreach ($contracts as $contract) {
            if (! $this->hasTables([$contract['table'], $contract['parent']])
                || ! Schema::hasColumn($contract['table'], $contract['column'])) {
                continue;
            }

            $issues += DB::table("{$contract['table']} as t")
                ->leftJoin("{$contract['parent']} as p", 'p.id', '=', "t.{$contract['column']}")
                ->whereNotNull("t.{$contract['column']}")
                ->whereNull('p.id')
                ->count('t.id');
        }

        return $issues;
    }

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
