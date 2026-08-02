<?php

namespace App\Console\Commands;

use App\Models\SewaMobil;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightSewaMobilCommand extends Command
{
    protected $signature = 'koperasi:preflight-sewa-mobil';

    protected $description = 'Audit read-only kesiapan transaksi Sewa Mobil final.';

    public function handle(): int
    {
        $checks = [
            $this->check('schema_legacy', 'Schema Sewa Mobil masih memakai struktur legacy', $this->legacySchemaIssues()),
            $this->check('kode_invalid', 'Kode Sewa Mobil tidak sesuai format SWM-YYYYMM-000001', $this->invalidKode()),
            $this->check('karyawan_nonaktif', 'Sewa Mobil milik Karyawan nonaktif/berhenti', $this->inactiveEmployees()),
            $this->check('mobil_invalid', 'Sewa Mobil memakai aset bukan mobil atau mobil non-rentable', $this->invalidCars()),
            $this->check('kalkulasi_invalid', 'Jumlah hari/tarif snapshot/total Sewa Mobil tidak konsisten', $this->invalidCalculations()),
            $this->check('jadwal_overlap', 'Jadwal Sewa Mobil approved/berjalan saling bertabrakan', $this->overlappingSchedules()),
            $this->check('paid_tanpa_pembayaran', 'Sewa Mobil paid tanpa pembayaran', $this->paidWithoutPayment()),
            $this->check('pembayaran_invalid', 'Pembayaran Sewa Mobil tidak penuh sesuai total sewa', $this->invalidPayments()),
            $this->check('mutasi_pembayaran_missing', 'Pembayaran Sewa Mobil tanpa Mutasi Kas masuk resmi', $this->paymentWithoutMutasi()),
            $this->check('jurnal_pembayaran_missing', 'Pembayaran Sewa Mobil tanpa Jurnal dimuka resmi', $this->paymentWithoutJournal()),
            $this->check('jurnal_pengakuan_missing', 'Sewa Mobil selesai tanpa Jurnal pengakuan pendapatan', $this->completedWithoutRevenueJournal()),
            $this->check('jurnal_unbalanced', 'Jurnal Sewa Mobil tidak balance', $this->unbalancedJournals()),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight Sewa Mobil (read-only)');
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
            $this->error('Preflight Sewa Mobil menemukan konflik kritis. Command ini tidak menulis database.');

            return self::FAILURE;
        }

        $this->info('Preflight Sewa Mobil bersih.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count, bool $critical = true): array
    {
        return compact('code', 'label', 'count', 'critical');
    }

    private function legacySchemaIssues(): int
    {
        if (! Schema::hasTable('sewa_mobil')) {
            return 0;
        }

        $required = ['tanggal_mulai', 'tanggal_selesai', 'jumlah_hari', 'tarif_harian_snapshot', 'total_sewa', 'recorded_by'];
        $legacy = ['mulai_at', 'selesai_at', 'tarif_total'];

        $missingRequired = collect($required)->filter(fn (string $column): bool => ! Schema::hasColumn('sewa_mobil', $column))->count();
        $legacyPresent = collect($legacy)->filter(fn (string $column): bool => Schema::hasColumn('sewa_mobil', $column))->count();

        return $missingRequired + $legacyPresent;
    }

    private function invalidKode(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['kode_sewa', 'status'])) {
            return 0;
        }

        return DB::table('sewa_mobil')
            ->whereNotIn('status', [SewaMobil::STATUS_DRAFT, SewaMobil::STATUS_DIBATALKAN])
            ->pluck('kode_sewa')
            ->filter(fn ($kode): bool => preg_match('/^SWM-[0-9]{6}-[0-9]{6}$/', (string) $kode) !== 1)
            ->count();
    }

    private function inactiveEmployees(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['karyawan_id']) || ! $this->hasColumns('karyawan', ['status_kerja'])) {
            return 0;
        }

        return DB::table('sewa_mobil as s')
            ->join('karyawan as k', 'k.id', '=', 's.karyawan_id')
            ->where('k.status_kerja', '!=', 'aktif')
            ->count('s.id');
    }

    private function invalidCars(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['aset_koperasi_id'])
            || ! $this->hasColumns('aset_koperasi', ['jenis_aset', 'status'])
            || ! $this->hasColumns('aset_mobil', ['aset_koperasi_id', 'tarif_sewa_harian'])) {
            return 0;
        }

        return DB::table('sewa_mobil as s')
            ->join('aset_koperasi as a', 'a.id', '=', 's.aset_koperasi_id')
            ->leftJoin('aset_mobil as m', 'm.aset_koperasi_id', '=', 'a.id')
            ->where(fn ($query) => $query
                ->where('a.jenis_aset', '!=', 'mobil')
                ->orWhereNull('m.id')
                ->orWhereIn('a.status', ['nonaktif', 'perawatan'])
                ->orWhere('m.tarif_sewa_harian', '<=', 0))
            ->count('s.id');
    }

    private function invalidCalculations(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['tanggal_mulai', 'tanggal_selesai', 'jumlah_hari', 'tarif_harian_snapshot', 'total_sewa'])) {
            return 0;
        }

        return DB::table('sewa_mobil')
            ->select('id', 'tanggal_mulai', 'tanggal_selesai', 'jumlah_hari', 'tarif_harian_snapshot', 'total_sewa')
            ->when(Schema::hasColumn('sewa_mobil', 'model_sumber'), fn ($query) => $query->where(fn ($scope) => $scope->whereNull('model_sumber')->orWhere('model_sumber', '!=', 'vendor')))
            ->get()
            ->filter(function ($row): bool {
                try {
                    $start = CarbonImmutable::parse($row->tanggal_mulai);
                    $end = CarbonImmutable::parse($row->tanggal_selesai);
                } catch (\Throwable) {
                    return true;
                }

                $days = $start->greaterThan($end) ? 0 : ((int) $start->diffInDays($end)) + 1;
                $tarif = (int) $row->tarif_harian_snapshot;
                $total = (int) $row->total_sewa;

                return $days <= 0
                    || (int) $row->jumlah_hari !== $days
                    || $tarif <= 0
                    || $total !== $days * $tarif;
            })
            ->count();
    }

    private function overlappingSchedules(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['aset_koperasi_id', 'status', 'tanggal_mulai', 'tanggal_selesai'])) {
            return 0;
        }

        return DB::table('sewa_mobil as a')
            ->join('sewa_mobil as b', function ($join): void {
                $join->on('a.aset_koperasi_id', '=', 'b.aset_koperasi_id')
                    ->whereColumn('a.id', '<', 'b.id')
                    ->whereColumn('a.tanggal_mulai', '<=', 'b.tanggal_selesai')
                    ->whereColumn('a.tanggal_selesai', '>=', 'b.tanggal_mulai');
            })
            ->whereIn('a.status', [SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN])
            ->whereIn('b.status', [SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN])
            ->count('a.id');
    }

    private function paidWithoutPayment(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['status_pembayaran']) || ! Schema::hasTable('pembayaran_sewa_mobil')) {
            return 0;
        }

        return DB::table('sewa_mobil as s')
            ->leftJoin('pembayaran_sewa_mobil as p', 'p.sewa_mobil_id', '=', 's.id')
            ->where('s.status_pembayaran', SewaMobil::PEMBAYARAN_PAID)
            ->whereNull('p.id')
            ->count('s.id');
    }

    private function invalidPayments(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['total_sewa']) || ! $this->hasColumns('pembayaran_sewa_mobil', ['sewa_mobil_id', 'jumlah_bayar'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_mobil as p')
            ->join('sewa_mobil as s', 's.id', '=', 'p.sewa_mobil_id')
            ->whereColumn('p.jumlah_bayar', '!=', 's.total_sewa')
            ->count('p.id');
    }

    private function paymentWithoutMutasi(): int
    {
        if (! $this->hasColumns('pembayaran_sewa_mobil', ['id']) || ! $this->hasColumns('mutasi_kas', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_mobil')
            ->where('status', 'paid')
            ->pluck('id')
            ->filter(fn ($id): bool => ! DB::table('mutasi_kas')->where('idempotency_key', 'sewa-mobil:pembayaran:mutasi:' . $id)->exists())
            ->count();
    }

    private function paymentWithoutJournal(): int
    {
        if (! $this->hasColumns('pembayaran_sewa_mobil', ['id']) || ! $this->hasColumns('jurnal_umum', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_mobil')
            ->where('status', 'paid')
            ->pluck('id')
            ->filter(fn ($id): bool => ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-mobil:pembayaran-dimuka:jurnal:' . $id)->exists())
            ->count();
    }

    private function completedWithoutRevenueJournal(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['id', 'status']) || ! $this->hasColumns('jurnal_umum', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('sewa_mobil')
            ->where('status', SewaMobil::STATUS_SELESAI)
            ->get(['id', ...(Schema::hasColumn('sewa_mobil', 'model_sumber') ? ['model_sumber'] : [])])
            ->filter(fn ($row): bool => ! DB::table('jurnal_umum')->where('idempotency_key', ($row->model_sumber ?? null) === 'vendor' ? 'b2b:margin:jurnal:'.SewaMobil::class.':'.$row->id : 'sewa-mobil:pengakuan-pendapatan:jurnal:'.$row->id)->exists())
            ->count();
    }

    private function unbalancedJournals(): int
    {
        if (! $this->hasColumns('jurnal_umum', ['id', 'idempotency_key']) || ! $this->hasColumns('jurnal_umum_detail', ['jurnal_umum_id', 'debit', 'kredit'])) {
            return 0;
        }

        return DB::table('jurnal_umum as j')
            ->select('j.id')
            ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
            ->where('j.idempotency_key', 'like', 'sewa-mobil:%')
            ->groupBy('j.id')
            ->havingRaw('ABS(SUM(d.debit) - SUM(d.kredit)) > 0.01')
            ->get()
            ->count();
    }

    private function hasColumns(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
}
