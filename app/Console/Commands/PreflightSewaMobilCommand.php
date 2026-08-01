<?php

namespace App\Console\Commands;

use App\Models\PembayaranSewaMobil;
use App\Models\SewaMobil;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class PreflightSewaMobilCommand extends Command
{
    protected $signature = 'koperasi:preflight-sewa-mobil';

    protected $description = 'Audit read-only kesiapan transaksi Sewa Mobil vendor-based final.';

    public function handle(): int
    {
        $checks = [
            $this->check('schema_vendor_based', 'Schema vendor snapshot Sewa Mobil belum lengkap', $this->schemaIssues()),
            $this->check('master_mobil_runtime', 'Route/navigation Master Mobil masih aktif sebagai dependensi runtime', $this->masterMobilRuntimeReferences()),
            $this->check('kode_invalid', 'Kode Sewa Mobil tidak sesuai format SWM-YYYYMM-000001', $this->invalidKode()),
            $this->check('kode_duplicate', 'Kode Sewa Mobil duplikat', $this->duplicateKode()),
            $this->check('idempotency_duplicate', 'Idempotency key Sewa Mobil/Mutasi/Jurnal duplikat', $this->duplicateIdempotencyKeys()),
            $this->check('karyawan_nonaktif', 'Sewa Mobil milik Karyawan nonaktif/berhenti', $this->inactiveEmployees()),
            $this->check('snapshot_vendor_missing', 'Snapshot vendor Sewa Mobil belum lengkap', $this->missingVendorSnapshot()),
            $this->check('snapshot_kendaraan_missing', 'Snapshot kendaraan Sewa Mobil belum lengkap', $this->missingVehicleSnapshot()),
            $this->check('plat_approval_missing', 'Sewa Mobil approval/aktif tanpa plat nomor snapshot', $this->approvedWithoutPlate()),
            $this->check('pakai_master_mobil', 'Sewa Mobil masih memakai aset_koperasi_id', $this->usesMasterMobil()),
            $this->check('kalkulasi_invalid', 'Jumlah hari/total vendor/markup/tagihan Sewa Mobil tidak konsisten', $this->invalidCalculations()),
            $this->check('jadwal_overlap', 'Jadwal Sewa Mobil vendor dengan plat yang sama saling bertabrakan', $this->overlappingSchedules()),
            $this->check('pengurus_invalid', 'Sewa Mobil disetujui tanpa Pengurus aktif valid', $this->invalidPengurus()),
            $this->check('paid_tanpa_pembayaran', 'Sewa Mobil paid tanpa pembayaran', $this->paidWithoutPayment()),
            $this->check('pembayaran_invalid', 'Pembayaran Sewa Mobil tidak penuh untuk tagihan perusahaan dan vendor', $this->invalidPayments()),
            $this->check('dompet_invalid', 'Dompet/COA pembayaran Sewa Mobil tidak valid', $this->invalidDompetMappings()),
            $this->check('mutasi_pembayaran_missing', 'Pembayaran Sewa Mobil tanpa Mutasi Kas masuk/keluar resmi', $this->paymentWithoutMutasi()),
            $this->check('jurnal_pembayaran_missing', 'Pembayaran Sewa Mobil tanpa Jurnal penerimaan/vendor resmi', $this->paymentWithoutJournal()),
            $this->check('jurnal_pengakuan_missing', 'Sewa Mobil selesai tanpa Jurnal pengakuan margin', $this->completedWithoutRevenueJournal()),
            $this->check('refund_incomplete', 'Refund Sewa Mobil tidak lengkap atau tidak traceable', $this->incompleteRefunds()),
            $this->check('ledger_payroll', 'Sewa Mobil tidak boleh membuat ledger potong gaji/payroll', $this->payrollLedgerUsage()),
            $this->check('jurnal_unbalanced', 'Jurnal Sewa Mobil tidak balance', $this->unbalancedJournals()),
            $this->check('route_hard_delete', 'Route hard delete/edit transaksi keuangan Sewa Mobil tersedia', $this->hardDeleteRoutes()),
            $this->check('self_service_route', 'Route pengajuan mandiri Karyawan untuk Sewa Mobil masih tersedia', $this->selfServiceRoutes()),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight Sewa Mobil vendor-based (read-only)');
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

    private function schemaIssues(): int
    {
        if (! Schema::hasTable('sewa_mobil')) {
            return 0;
        }

        $requiredSewa = [
            'vendor_nama',
            'vendor_kontak',
            'vendor_alamat',
            'jenis_kendaraan',
            'merek_kendaraan',
            'model_kendaraan',
            'plat_nomor_snapshot',
            'plat_nomor_normalized',
            'tahun_kendaraan',
            'warna_kendaraan',
            'total_harga_vendor',
            'total_markup',
            'total_tagihan_perusahaan',
            'refunded_at',
            'refunded_by',
            'refund_reason',
            'reversal_transaksi_id',
        ];

        $requiredPembayaran = [
            'dompet_penerimaan_id',
            'metode_penerimaan',
            'jumlah_diterima',
            'received_at',
            'dompet_vendor_id',
            'metode_pembayaran_vendor',
            'jumlah_bayar_vendor',
            'vendor_paid_at',
            'refunded_by',
            'refund_reason',
            'reversal_transaksi_id',
        ];

        return collect($requiredSewa)->filter(fn (string $column): bool => ! Schema::hasColumn('sewa_mobil', $column))->count()
            + collect($requiredPembayaran)->filter(fn (string $column): bool => ! Schema::hasColumn('pembayaran_sewa_mobil', $column))->count();
    }

    private function masterMobilRuntimeReferences(): int
    {
        $navigation = collect(config('navigation.modules', []))
            ->filter(fn (array $item): bool => ($item['route'] ?? null) === 'aset-mobil.index'
                || in_array('aset-mobil.*', $item['route_patterns'] ?? [], true))
            ->count();

        return (Route::has('aset-mobil.index') ? 1 : 0) + $navigation;
    }

    private function invalidKode(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['kode_sewa', 'status'])) {
            return 0;
        }

        return DB::table('sewa_mobil')
            ->whereNotIn('status', [SewaMobil::STATUS_DRAFT, SewaMobil::STATUS_DIBATALKAN, SewaMobil::STATUS_REFUNDED])
            ->pluck('kode_sewa')
            ->filter(fn ($kode): bool => preg_match('/^SWM-[0-9]{6}-[0-9]{6}$/', (string) $kode) !== 1)
            ->count();
    }

    private function duplicateKode(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['kode_sewa'])) {
            return 0;
        }

        return DB::table('sewa_mobil')
            ->select('kode_sewa')
            ->whereNotNull('kode_sewa')
            ->where('kode_sewa', '!=', '')
            ->groupBy('kode_sewa')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }

    private function duplicateIdempotencyKeys(): int
    {
        $checks = [
            ['sewa_mobil', 'idempotency_key', null],
            ['pembayaran_sewa_mobil', 'idempotency_key', null],
            ['mutasi_kas', 'idempotency_key', 'sewa-mobil:%'],
            ['jurnal_umum', 'idempotency_key', 'sewa-mobil:%'],
        ];

        return collect($checks)->sum(function (array $check): int {
            [$table, $column, $prefix] = $check;

            if (! $this->hasColumns($table, [$column])) {
                return 0;
            }

            $query = DB::table($table)
                ->select($column)
                ->whereNotNull($column)
                ->where($column, '!=', '');

            if ($prefix) {
                $query->where($column, 'like', $prefix);
            }

            return $query
                ->groupBy($column)
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count();
        });
    }

    private function inactiveEmployees(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['karyawan_id']) || ! $this->hasColumns('karyawan', ['status_kerja'])) {
            return 0;
        }

        return DB::table('sewa_mobil as s')
            ->join('karyawan as k', 'k.id', '=', 's.karyawan_id')
            ->whereNotIn('s.status', [SewaMobil::STATUS_DITOLAK, SewaMobil::STATUS_DIBATALKAN, SewaMobil::STATUS_REFUNDED])
            ->where('k.status_kerja', '!=', 'aktif')
            ->count('s.id');
    }

    private function missingVendorSnapshot(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['vendor_nama', 'vendor_kontak', 'vendor_alamat'])) {
            return 0;
        }

        return DB::table('sewa_mobil')
            ->whereNotIn('status', [SewaMobil::STATUS_DIBATALKAN])
            ->where(fn ($query) => $query
                ->whereNull('vendor_nama')->orWhere('vendor_nama', '')
                ->orWhereNull('vendor_kontak')->orWhere('vendor_kontak', '')
                ->orWhereNull('vendor_alamat')->orWhere('vendor_alamat', ''))
            ->count('id');
    }

    private function missingVehicleSnapshot(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['jenis_kendaraan', 'merek_kendaraan', 'model_kendaraan', 'tahun_kendaraan', 'warna_kendaraan'])) {
            return 0;
        }

        return DB::table('sewa_mobil')
            ->whereNotIn('status', [SewaMobil::STATUS_DIBATALKAN])
            ->where(fn ($query) => $query
                ->whereNull('jenis_kendaraan')->orWhere('jenis_kendaraan', '')
                ->orWhereNull('merek_kendaraan')->orWhere('merek_kendaraan', '')
                ->orWhereNull('model_kendaraan')->orWhere('model_kendaraan', '')
                ->orWhereNull('tahun_kendaraan')
                ->orWhereNull('warna_kendaraan')->orWhere('warna_kendaraan', ''))
            ->count('id');
    }

    private function approvedWithoutPlate(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['plat_nomor_snapshot', 'plat_nomor_normalized', 'status'])) {
            return 0;
        }

        return DB::table('sewa_mobil')
            ->whereIn('status', [SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN, SewaMobil::STATUS_SELESAI])
            ->where(fn ($query) => $query
                ->whereNull('plat_nomor_snapshot')->orWhere('plat_nomor_snapshot', '')
                ->orWhereNull('plat_nomor_normalized')->orWhere('plat_nomor_normalized', ''))
            ->count('id');
    }

    private function usesMasterMobil(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['aset_koperasi_id'])) {
            return 0;
        }

        return DB::table('sewa_mobil')
            ->whereNotNull('aset_koperasi_id')
            ->count('id');
    }

    private function invalidCalculations(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['tanggal_mulai', 'tanggal_selesai', 'jumlah_hari', 'total_harga_vendor', 'total_markup', 'total_tagihan_perusahaan', 'total_sewa'])) {
            return 0;
        }

        return DB::table('sewa_mobil')
            ->select('id', 'tanggal_mulai', 'tanggal_selesai', 'jumlah_hari', 'total_harga_vendor', 'total_markup', 'total_tagihan_perusahaan', 'total_sewa')
            ->get()
            ->filter(function ($row): bool {
                try {
                    $start = CarbonImmutable::parse($row->tanggal_mulai);
                    $end = CarbonImmutable::parse($row->tanggal_selesai);
                } catch (\Throwable) {
                    return true;
                }

                $days = $start->greaterThan($end) ? 0 : ((int) $start->diffInDays($end)) + 1;
                $vendor = (int) $row->total_harga_vendor;
                $markup = (int) $row->total_markup;
                $tagihan = (int) $row->total_tagihan_perusahaan;

                return $days <= 0
                    || (int) $row->jumlah_hari !== $days
                    || $vendor <= 0
                    || $markup <= 0
                    || $tagihan !== $vendor + $markup
                    || (int) $row->total_sewa !== $tagihan;
            })
            ->count();
    }

    private function overlappingSchedules(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['plat_nomor_normalized', 'status', 'tanggal_mulai', 'tanggal_selesai'])) {
            return 0;
        }

        $blocking = [SewaMobil::STATUS_DIAJUKAN, SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN, SewaMobil::STATUS_SELESAI];

        return DB::table('sewa_mobil as a')
            ->join('sewa_mobil as b', function ($join): void {
                $join->on('a.plat_nomor_normalized', '=', 'b.plat_nomor_normalized')
                    ->whereColumn('a.id', '<', 'b.id')
                    ->whereColumn('a.tanggal_mulai', '<=', 'b.tanggal_selesai')
                    ->whereColumn('a.tanggal_selesai', '>=', 'b.tanggal_mulai');
            })
            ->whereNotNull('a.plat_nomor_normalized')
            ->where('a.plat_nomor_normalized', '!=', '')
            ->whereIn('a.status', $blocking)
            ->whereIn('b.status', $blocking)
            ->count('a.id');
    }

    private function invalidPengurus(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['pengurus_penyetuju_id', 'status']) || ! Schema::hasTable('pengurus_koperasi')) {
            return 0;
        }

        return DB::table('sewa_mobil as s')
            ->leftJoin('pengurus_koperasi as p', 'p.id', '=', 's.pengurus_penyetuju_id')
            ->whereIn('s.status', [SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN, SewaMobil::STATUS_SELESAI])
            ->where(fn ($query) => $query->whereNull('p.id')->orWhere('p.status', '!=', 'aktif'))
            ->count('s.id');
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
        if (! $this->hasColumns('sewa_mobil', ['total_harga_vendor', 'total_tagihan_perusahaan'])
            || ! $this->hasColumns('pembayaran_sewa_mobil', ['sewa_mobil_id', 'jumlah_diterima', 'jumlah_bayar_vendor', 'status'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_mobil as p')
            ->join('sewa_mobil as s', 's.id', '=', 'p.sewa_mobil_id')
            ->whereIn('p.status', [PembayaranSewaMobil::STATUS_PAID, PembayaranSewaMobil::STATUS_REFUNDED])
            ->where(fn ($query) => $query
                ->whereColumn('p.jumlah_diterima', '!=', 's.total_tagihan_perusahaan')
                ->orWhereColumn('p.jumlah_bayar_vendor', '!=', 's.total_harga_vendor'))
            ->count('p.id');
    }

    private function invalidDompetMappings(): int
    {
        if (! $this->hasColumns('pembayaran_sewa_mobil', ['dompet_penerimaan_id', 'dompet_vendor_id'])
            || ! $this->hasColumns('dompet_koperasi', ['akun_id'])
            || ! Schema::hasTable('akun')) {
            return 0;
        }

        return DB::table('pembayaran_sewa_mobil as p')
            ->leftJoin('dompet_koperasi as dp', 'dp.id', '=', 'p.dompet_penerimaan_id')
            ->leftJoin('akun as ap', 'ap.id', '=', 'dp.akun_id')
            ->leftJoin('dompet_koperasi as dv', 'dv.id', '=', 'p.dompet_vendor_id')
            ->leftJoin('akun as av', 'av.id', '=', 'dv.akun_id')
            ->whereIn('p.status', [PembayaranSewaMobil::STATUS_PAID, PembayaranSewaMobil::STATUS_REFUNDED])
            ->where(function ($query): void {
                $query->whereNull('dp.id')
                    ->orWhereNull('dv.id')
                    ->orWhereNull('ap.id')
                    ->orWhereNull('av.id')
                    ->orWhere('ap.is_aktif', false)
                    ->orWhere('av.is_aktif', false)
                    ->orWhere('ap.kategori', '!=', 'aset')
                    ->orWhere('av.kategori', '!=', 'aset')
                    ->orWhere('ap.posisi_saldo', '!=', 'debit')
                    ->orWhere('av.posisi_saldo', '!=', 'debit');
            })
            ->count('p.id');
    }

    private function paymentWithoutMutasi(): int
    {
        if (! $this->hasColumns('pembayaran_sewa_mobil', ['id', 'status']) || ! $this->hasColumns('mutasi_kas', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_mobil')
            ->whereIn('status', [PembayaranSewaMobil::STATUS_PAID, PembayaranSewaMobil::STATUS_REFUNDED])
            ->pluck('id')
            ->filter(fn ($id): bool => ! DB::table('mutasi_kas')->where('idempotency_key', 'sewa-mobil:penerimaan:mutasi:' . $id)->exists()
                || ! DB::table('mutasi_kas')->where('idempotency_key', 'sewa-mobil:pembayaran-vendor:mutasi:' . $id)->exists())
            ->count();
    }

    private function paymentWithoutJournal(): int
    {
        if (! $this->hasColumns('pembayaran_sewa_mobil', ['id', 'status']) || ! $this->hasColumns('jurnal_umum', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_mobil')
            ->whereIn('status', [PembayaranSewaMobil::STATUS_PAID, PembayaranSewaMobil::STATUS_REFUNDED])
            ->pluck('id')
            ->filter(fn ($id): bool => ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-mobil:pembayaran-dimuka:jurnal:' . $id)->exists()
                || ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-mobil:pembayaran-vendor:jurnal:' . $id)->exists())
            ->count();
    }

    private function completedWithoutRevenueJournal(): int
    {
        if (! $this->hasColumns('sewa_mobil', ['id', 'status']) || ! $this->hasColumns('jurnal_umum', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('sewa_mobil')
            ->where('status', SewaMobil::STATUS_SELESAI)
            ->pluck('id')
            ->filter(fn ($id): bool => ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-mobil:pengakuan-pendapatan:jurnal:' . $id)->exists())
            ->count();
    }

    private function incompleteRefunds(): int
    {
        if (! $this->hasColumns('pembayaran_sewa_mobil', ['id', 'status', 'reversal_transaksi_id'])
            || ! $this->hasColumns('sewa_mobil', ['id', 'status', 'status_pembayaran', 'reversal_transaksi_id'])
            || ! $this->hasColumns('reversal_transaksi', ['id'])
            || ! $this->hasColumns('mutasi_kas', ['idempotency_key'])
            || ! $this->hasColumns('jurnal_umum', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('sewa_mobil as s')
            ->join('pembayaran_sewa_mobil as p', 'p.sewa_mobil_id', '=', 's.id')
            ->leftJoin('reversal_transaksi as rs', 'rs.id', '=', 's.reversal_transaksi_id')
            ->leftJoin('reversal_transaksi as rp', 'rp.id', '=', 'p.reversal_transaksi_id')
            ->where(fn ($query) => $query
                ->where('s.status', SewaMobil::STATUS_REFUNDED)
                ->orWhere('s.status_pembayaran', SewaMobil::PEMBAYARAN_REFUNDED)
                ->orWhere('p.status', PembayaranSewaMobil::STATUS_REFUNDED))
            ->select('s.id as sewa_id', 'p.id as payment_id', 'rs.id as sewa_reversal_id', 'rp.id as payment_reversal_id')
            ->get()
            ->filter(fn ($row): bool => ! $row->sewa_reversal_id
                || ! $row->payment_reversal_id
                || ! DB::table('mutasi_kas')->where('idempotency_key', 'sewa-mobil:refund-vendor:mutasi:' . $row->payment_id)->exists()
                || ! DB::table('mutasi_kas')->where('idempotency_key', 'sewa-mobil:refund-perusahaan:mutasi:' . $row->payment_id)->exists()
                || ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-mobil:refund-vendor:jurnal:' . $row->payment_id)->exists()
                || ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-mobil:refund-perusahaan:jurnal:' . $row->payment_id)->exists())
            ->count();
    }

    private function payrollLedgerUsage(): int
    {
        if (! $this->hasColumns('pemakaian_potong_gaji', ['source_type'])) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji')
            ->whereIn('source_type', [SewaMobil::class, PembayaranSewaMobil::class])
            ->count('id');
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

    private function hardDeleteRoutes(): int
    {
        return collect(Route::getRoutes())
            ->filter(function ($route): bool {
                $name = (string) $route->getName();

                return str_starts_with($name, 'sewa-mobil.')
                    && in_array('DELETE', $route->methods(), true);
            })
            ->count();
    }

    private function selfServiceRoutes(): int
    {
        return collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'sewa-mobil.karyawan.'))
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
