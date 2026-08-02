<?php

namespace App\Console\Commands;

use App\Models\PembayaranSewaHardware;
use App\Models\SewaHardware;
use App\Models\SewaHardwareDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class PreflightSewaHardwareCommand extends Command
{
    protected $signature = 'koperasi:preflight-sewa-hardware';

    protected $description = 'Audit read-only kesiapan transaksi Sewa Hardware vendor-based.';

    public function handle(): int
    {
        $checks = [
            $this->check('schema_missing', 'Schema final Sewa Hardware belum lengkap', $this->schemaMissing()),
            $this->check('table_printer_legacy', 'Tabel Sewa Printer legacy masih aktif', $this->legacyTables()),
            $this->check('column_printer_legacy', 'Kolom Printer/aset legacy masih aktif pada schema Hardware', $this->legacyColumns()),
            $this->check('route_printer_active', 'Route runtime /sewa-printer atau sewa-printer.* masih aktif', $this->legacyPrinterRoutes()),
            $this->check('command_printer_active', 'Command preflight Sewa Printer legacy masih aktif', $this->legacyPrinterCommand()),
            $this->check('kode_swp_tersisa', 'Kode SWP masih tersisa pada Sewa Hardware', $this->remainingSwpCodes()),
            $this->check('kode_invalid', 'Kode Sewa Hardware tidak sesuai format SWH-YYYYMM-000001', $this->invalidKode()),
            $this->check('duplicate_swh', 'Kode SWH duplikat', $this->duplicateValues('sewa_hardware', 'kode_sewa')),
            $this->check('duplicate_idempotency', 'Idempotency key duplikat pada domain Hardware', $this->duplicateIdempotency()),
            $this->check('counter_legacy_collision', 'Counter legacy sewa_printer tersisa atau collision dengan sewa_hardware', $this->counterLegacyCollision()),
            $this->check('header_tanpa_karyawan', 'Header tanpa Karyawan valid', $this->headerWithoutKaryawan()),
            $this->check('karyawan_nonaktif', 'Sewa Hardware milik Karyawan nonaktif/berhenti', $this->inactiveEmployees()),
            $this->check('vendor_snapshot_missing', 'Snapshot vendor tidak lengkap', $this->missingVendorSnapshot()),
            $this->check('detail_missing', 'Kontrak Sewa Hardware tanpa detail kebutuhan', $this->withoutDetails()),
            $this->check('detail_orphan', 'Detail Sewa Hardware orphan', $this->orphanDetails()),
            $this->check('detail_invalid', 'Detail Sewa Hardware tidak valid atau subtotal tidak konsisten', $this->invalidDetails()),
            $this->check('header_total_invalid', 'Total header Sewa Hardware tidak sama dengan detail', $this->invalidHeaderTotals()),
            $this->check('paid_tanpa_pembayaran', 'Sewa Hardware paid tanpa pembayaran', $this->paidWithoutPayment()),
            $this->check('pembayaran_invalid', 'Nominal pembayaran Hardware tidak sama dengan snapshot tagihan/vendor', $this->invalidPayments()),
            $this->check('dompet_invalid', 'Dompet pembayaran Hardware tidak sesuai metode atau COA', $this->invalidPaymentDompets()),
            $this->check('mutasi_missing', 'Pembayaran Hardware tanpa Mutasi masuk/keluar resmi', $this->paymentWithoutMutasi()),
            $this->check('jurnal_missing', 'Pembayaran Hardware tanpa Jurnal penerimaan/vendor resmi', $this->paymentWithoutJournals()),
            $this->check('jurnal_pengakuan_missing', 'Sewa Hardware selesai tanpa Jurnal pengakuan margin', $this->completedWithoutRevenueJournal()),
            $this->check('refund_incomplete', 'Refund Hardware tidak lengkap atau ganda', $this->refundIncomplete()),
            $this->check('source_type_printer_legacy', 'Source type/idempotency legacy Printer masih tersisa', $this->legacySourceTypes()),
            $this->check('account_map_hardware_missing', 'Account map Sewa Hardware belum lengkap atau key Printer masih aktif', $this->accountMapIssues()),
            $this->check('relasi_master_printer_aktif', 'Relasi Master Printer/Aset Printer aktif pada transaksi Hardware', $this->activeMasterPrinterRelations()),
            $this->check('payroll_ledger_hardware', 'Sewa Hardware masuk ke ledger payroll/potong gaji', $this->payrollLedgerHardware()),
            $this->check('route_hard_delete', 'Route hard delete Sewa Hardware tersedia', $this->hardDeleteRoutes()),
            $this->check('self_service_karyawan', 'Route self-service Karyawan Sewa Hardware tersedia', $this->selfServiceRoutes()),
            $this->check('approval_pengurus', 'Route approval Pengurus Sewa Hardware tersedia', $this->approvalRoutes()),
            $this->check('jurnal_unbalanced', 'Jurnal Sewa Hardware tidak balance', $this->unbalancedJournals()),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight Sewa Hardware (read-only)');
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
            $this->error('Preflight Sewa Hardware menemukan konflik kritis. Command ini tidak menulis database.');

            return self::FAILURE;
        }

        $this->info('Preflight Sewa Hardware bersih.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count, bool $critical = true): array
    {
        return compact('code', 'label', 'count', 'critical');
    }

    private function schemaMissing(): int
    {
        $required = [
            'sewa_hardware' => [
                'kode_sewa',
                'karyawan_id',
                'vendor_nama',
                'vendor_kontak',
                'vendor_alamat',
                'total_harga_vendor',
                'total_margin',
                'total_tagihan_perusahaan',
                'recorded_by',
                'status',
                'status_pembayaran',
                'refunded_at',
                'refunded_by',
                'refund_reason',
                'reversal_transaksi_id',
            ],
            'sewa_hardware_detail' => [
                'sewa_hardware_id',
                'jenis_hardware',
                'nama_model_hardware',
                'spesifikasi_kebutuhan',
                'kuantitas',
                'harga_vendor_per_unit',
                'margin_persen_snapshot',
                'margin_per_unit',
                'harga_tagihan_per_unit',
                'subtotal_harga_vendor',
                'subtotal_margin',
                'subtotal_tagihan',
            ],
            'pembayaran_sewa_hardware' => [
                'sewa_hardware_id',
                'dompet_penerimaan_id',
                'dompet_vendor_id',
                'metode_penerimaan',
                'metode_pembayaran_vendor',
                'jumlah_diterima',
                'jumlah_bayar_vendor',
                'status',
                'paid_at',
                'refunded_at',
                'refunded_by',
                'refund_reason',
                'reversal_transaksi_id',
                'idempotency_key',
            ],
        ];

        $missing = 0;
        foreach ($required as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $missing++;
                continue;
            }

            foreach ($columns as $column) {
                $missing += Schema::hasColumn($table, $column) ? 0 : 1;
            }
        }

        return $missing;
    }

    private function legacyTables(): int
    {
        return collect(['sewa_printer', 'sewa_printer_detail', 'pembayaran_sewa_printer'])
            ->filter(fn (string $table): bool => Schema::hasTable($table))
            ->count();
    }

    private function legacyColumns(): int
    {
        $legacy = [
            'sewa_hardware' => ['karyawan_pic_id', 'total_harga_dasar', 'grand_total', 'aset_koperasi_id', 'aset_printer_id'],
            'sewa_hardware_detail' => ['sewa_printer_id', 'jenis_model_printer', 'aset_koperasi_id', 'kode_aset_snapshot', 'nomor_seri_snapshot', 'harga_dasar', 'margin_nominal', 'total_harga'],
            'pembayaran_sewa_hardware' => ['sewa_printer_id', 'dompet_id', 'metode_pembayaran', 'jumlah_bayar'],
        ];

        $count = 0;
        foreach ($legacy as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                $count += Schema::hasColumn($table, $column) ? 1 : 0;
            }
        }

        return $count;
    }

    private function legacyPrinterRoutes(): int
    {
        return collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'sewa-printer.')
                || str_starts_with($route->uri(), 'sewa-printer'))
            ->count();
    }

    private function legacyPrinterCommand(): int
    {
        return array_key_exists('koperasi:preflight-sewa-printer', Artisan::all()) ? 1 : 0;
    }

    private function invalidKode(): int
    {
        if (! $this->hasColumns('sewa_hardware', ['kode_sewa'])) {
            return 0;
        }

        return DB::table('sewa_hardware')
            ->pluck('kode_sewa')
            ->filter(fn ($kode): bool => preg_match('/^SWH-[0-9]{6}-[0-9]{6}$/', (string) $kode) !== 1)
            ->count();
    }

    private function remainingSwpCodes(): int
    {
        if (! $this->hasColumns('sewa_hardware', ['kode_sewa'])) {
            return 0;
        }

        return DB::table('sewa_hardware')->where('kode_sewa', 'like', 'SWP-%')->count();
    }

    private function duplicateIdempotency(): int
    {
        return $this->duplicateValues('sewa_hardware', 'idempotency_key')
            + $this->duplicateValues('pembayaran_sewa_hardware', 'idempotency_key')
            + $this->duplicateValues('mutasi_kas', 'idempotency_key')
            + $this->duplicateValues('jurnal_umum', 'idempotency_key')
            + $this->duplicateValues('reversal_transaksi', 'idempotency_key');
    }

    private function duplicateValues(string $table, string $column): int
    {
        if (! $this->hasColumns($table, [$column])) {
            return 0;
        }

        return DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }

    private function counterLegacyCollision(): int
    {
        if (! $this->hasColumns('nomor_urut_transaksi', ['jenis', 'periode'])) {
            return 0;
        }

        $legacy = DB::table('nomor_urut_transaksi')->where('jenis', 'sewa_printer')->count();
        $collision = DB::table('nomor_urut_transaksi as p')
            ->join('nomor_urut_transaksi as h', function ($join): void {
                $join->on('h.periode', '=', 'p.periode')
                    ->where('h.jenis', '=', 'sewa_hardware');
            })
            ->where('p.jenis', 'sewa_printer')
            ->count('p.id');

        return $legacy + $collision;
    }

    private function headerWithoutKaryawan(): int
    {
        if (! $this->hasColumns('sewa_hardware', ['karyawan_id']) || ! Schema::hasTable('karyawan')) {
            return 0;
        }

        return DB::table('sewa_hardware as s')
            ->leftJoin('karyawan as k', 'k.id', '=', 's.karyawan_id')
            ->where(fn ($query) => $query->whereNull('s.karyawan_id')->orWhereNull('k.id'))
            ->count('s.id');
    }

    private function inactiveEmployees(): int
    {
        if (! $this->hasColumns('sewa_hardware', ['karyawan_id']) || ! $this->hasColumns('karyawan', ['status_kerja'])) {
            return 0;
        }

        return DB::table('sewa_hardware as s')
            ->join('karyawan as k', 'k.id', '=', 's.karyawan_id')
            ->where('k.status_kerja', '!=', 'aktif')
            ->count('s.id');
    }

    private function missingVendorSnapshot(): int
    {
        if (! $this->hasColumns('sewa_hardware', ['vendor_nama', 'vendor_kontak', 'vendor_alamat'])) {
            return 0;
        }

        return DB::table('sewa_hardware')
            ->where(fn ($query) => $query
                ->whereNull('vendor_nama')
                ->orWhere('vendor_nama', '')
                ->orWhereNull('vendor_kontak')
                ->orWhere('vendor_kontak', '')
                ->orWhereNull('vendor_alamat')
                ->orWhere('vendor_alamat', ''))
            ->count();
    }

    private function withoutDetails(): int
    {
        if (! $this->hasColumns('sewa_hardware', ['id']) || ! $this->hasColumns('sewa_hardware_detail', ['sewa_hardware_id'])) {
            return 0;
        }

        return DB::table('sewa_hardware as s')
            ->leftJoin('sewa_hardware_detail as d', 'd.sewa_hardware_id', '=', 's.id')
            ->whereNull('d.id')
            ->count('s.id');
    }

    private function orphanDetails(): int
    {
        if (! $this->hasColumns('sewa_hardware_detail', ['sewa_hardware_id']) || ! Schema::hasTable('sewa_hardware')) {
            return 0;
        }

        return DB::table('sewa_hardware_detail as d')
            ->leftJoin('sewa_hardware as s', 's.id', '=', 'd.sewa_hardware_id')
            ->whereNull('s.id')
            ->count('d.id');
    }

    private function invalidDetails(): int
    {
        if (! $this->hasColumns('sewa_hardware_detail', [
            'jenis_hardware',
            'nama_model_hardware',
            'kuantitas',
            'harga_vendor_per_unit',
            'margin_persen_snapshot',
            'margin_per_unit',
            'harga_tagihan_per_unit',
            'subtotal_harga_vendor',
            'subtotal_margin',
            'subtotal_tagihan',
        ])) {
            return 0;
        }

        $validTypes = array_keys(SewaHardwareDetail::jenisOptions());

        return DB::table('sewa_hardware_detail')
            ->get()
            ->filter(function ($row) use ($validTypes): bool {
                $qty = (int) $row->kuantitas;
                $price = (int) $row->harga_vendor_per_unit;
                $margin = intdiv(($price * SewaHardwareDetail::MARGIN_PERSEN) + 50, 100);
                $tagihan = $price + $margin;

                return ! in_array((string) $row->jenis_hardware, $validTypes, true)
                    || trim((string) $row->nama_model_hardware) === ''
                    || $qty <= 0
                    || $price <= 0
                    || (int) $row->margin_persen_snapshot !== SewaHardwareDetail::MARGIN_PERSEN
                    || (int) $row->margin_per_unit !== $margin
                    || (int) $row->harga_tagihan_per_unit !== $tagihan
                    || (int) $row->subtotal_harga_vendor !== $price * $qty
                    || (int) $row->subtotal_margin !== $margin * $qty
                    || (int) $row->subtotal_tagihan !== $tagihan * $qty;
            })
            ->count();
    }

    private function invalidHeaderTotals(): int
    {
        if (! $this->hasColumns('sewa_hardware', ['total_harga_vendor', 'total_margin', 'total_tagihan_perusahaan'])
            || ! $this->hasColumns('sewa_hardware_detail', ['sewa_hardware_id', 'subtotal_harga_vendor', 'subtotal_margin', 'subtotal_tagihan'])) {
            return 0;
        }

        return DB::table('sewa_hardware as s')
            ->leftJoin('sewa_hardware_detail as d', 'd.sewa_hardware_id', '=', 's.id')
            ->select('s.id', 's.total_harga_vendor', 's.total_margin', 's.total_tagihan_perusahaan')
            ->selectRaw('COALESCE(SUM(d.subtotal_harga_vendor), 0) as detail_vendor')
            ->selectRaw('COALESCE(SUM(d.subtotal_margin), 0) as detail_margin')
            ->selectRaw('COALESCE(SUM(d.subtotal_tagihan), 0) as detail_tagihan')
            ->groupBy('s.id', 's.total_harga_vendor', 's.total_margin', 's.total_tagihan_perusahaan')
            ->get()
            ->filter(fn ($row): bool => (int) $row->total_harga_vendor !== (int) $row->detail_vendor
                || (int) $row->total_margin !== (int) $row->detail_margin
                || (int) $row->total_tagihan_perusahaan !== (int) $row->detail_tagihan)
            ->count();
    }

    private function paidWithoutPayment(): int
    {
        if (! $this->hasColumns('sewa_hardware', ['status_pembayaran']) || ! Schema::hasTable('pembayaran_sewa_hardware')) {
            return 0;
        }

        return DB::table('sewa_hardware as s')
            ->leftJoin('pembayaran_sewa_hardware as p', 'p.sewa_hardware_id', '=', 's.id')
            ->where('s.status_pembayaran', SewaHardware::PEMBAYARAN_PAID)
            ->whereNull('p.id')
            ->count('s.id');
    }

    private function invalidPayments(): int
    {
        if (! $this->hasColumns('sewa_hardware', ['total_harga_vendor', 'total_tagihan_perusahaan'])
            || ! $this->hasColumns('pembayaran_sewa_hardware', ['sewa_hardware_id', 'jumlah_diterima', 'jumlah_bayar_vendor'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_hardware as p')
            ->join('sewa_hardware as s', 's.id', '=', 'p.sewa_hardware_id')
            ->where(fn ($query) => $query
                ->whereColumn('p.jumlah_diterima', '!=', 's.total_tagihan_perusahaan')
                ->orWhereColumn('p.jumlah_bayar_vendor', '!=', 's.total_harga_vendor'))
            ->count('p.id');
    }

    private function invalidPaymentDompets(): int
    {
        if (! $this->hasColumns('pembayaran_sewa_hardware', ['dompet_penerimaan_id', 'dompet_vendor_id', 'metode_penerimaan', 'metode_pembayaran_vendor'])
            || ! $this->hasColumns('dompet_koperasi', ['jenis_dompet', 'akun_id'])
            || ! $this->hasColumns('akun', ['kategori', 'posisi_saldo', 'is_aktif'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_hardware as p')
            ->join('dompet_koperasi as dp', 'dp.id', '=', 'p.dompet_penerimaan_id')
            ->join('akun as ap', 'ap.id', '=', 'dp.akun_id')
            ->join('dompet_koperasi as dv', 'dv.id', '=', 'p.dompet_vendor_id')
            ->join('akun as av', 'av.id', '=', 'dv.akun_id')
            ->where(function ($query): void {
                $query->where(fn ($q) => $q->where('p.metode_penerimaan', PembayaranSewaHardware::METODE_TUNAI)->where('dp.jenis_dompet', '!=', 'kas'))
                    ->orWhere(fn ($q) => $q->where('p.metode_penerimaan', PembayaranSewaHardware::METODE_TRANSFER_BANK)->where('dp.jenis_dompet', '!=', 'bank'))
                    ->orWhere(fn ($q) => $q->where('p.metode_pembayaran_vendor', PembayaranSewaHardware::METODE_TUNAI)->where('dv.jenis_dompet', '!=', 'kas'))
                    ->orWhere(fn ($q) => $q->where('p.metode_pembayaran_vendor', PembayaranSewaHardware::METODE_TRANSFER_BANK)->where('dv.jenis_dompet', '!=', 'bank'))
                    ->orWhere('ap.kategori', '!=', 'aset')
                    ->orWhere('ap.posisi_saldo', '!=', 'debit')
                    ->orWhere('ap.is_aktif', false)
                    ->orWhere('av.kategori', '!=', 'aset')
                    ->orWhere('av.posisi_saldo', '!=', 'debit')
                    ->orWhere('av.is_aktif', false);
            })
            ->count('p.id');
    }

    private function paymentWithoutMutasi(): int
    {
        if (! $this->hasColumns('pembayaran_sewa_hardware', ['id']) || ! $this->hasColumns('mutasi_kas', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_hardware')
            ->where('status', PembayaranSewaHardware::STATUS_PAID)
            ->pluck('id')
            ->filter(fn ($id): bool => ! DB::table('mutasi_kas')->where('idempotency_key', 'sewa-hardware:penerimaan:mutasi:' . $id)->exists()
                || ! DB::table('mutasi_kas')->where('idempotency_key', 'sewa-hardware:pembayaran-vendor:mutasi:' . $id)->exists())
            ->count();
    }

    private function paymentWithoutJournals(): int
    {
        if (! $this->hasColumns('pembayaran_sewa_hardware', ['id']) || ! $this->hasColumns('jurnal_umum', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_hardware')
            ->where('status', PembayaranSewaHardware::STATUS_PAID)
            ->pluck('id')
            ->filter(fn ($id): bool => ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-hardware:pembayaran-dimuka:jurnal:' . $id)->exists()
                || ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-hardware:pembayaran-vendor:jurnal:' . $id)->exists())
            ->count();
    }

    private function completedWithoutRevenueJournal(): int
    {
        if (! $this->hasColumns('sewa_hardware', ['id', 'status']) || ! $this->hasColumns('jurnal_umum', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('sewa_hardware')
            ->where('status', SewaHardware::STATUS_SELESAI)
            ->pluck('id')
            ->filter(fn ($id): bool => ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-hardware:pengakuan-pendapatan:jurnal:' . $id)->exists())
            ->count();
    }

    private function refundIncomplete(): int
    {
        if (! $this->hasColumns('pembayaran_sewa_hardware', ['id', 'status', 'reversal_transaksi_id']) || ! $this->hasColumns('jurnal_umum', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_hardware')
            ->where('status', PembayaranSewaHardware::STATUS_REFUNDED)
            ->get()
            ->filter(fn ($row): bool => $row->reversal_transaksi_id === null
                || ! DB::table('mutasi_kas')->where('idempotency_key', 'sewa-hardware:refund-vendor:mutasi:' . $row->id)->exists()
                || ! DB::table('mutasi_kas')->where('idempotency_key', 'sewa-hardware:refund-perusahaan:mutasi:' . $row->id)->exists()
                || ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-hardware:refund-vendor:jurnal:' . $row->id)->exists()
                || ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-hardware:refund-perusahaan:jurnal:' . $row->id)->exists())
            ->count();
    }

    private function legacySourceTypes(): int
    {
        $count = 0;
        foreach (['mutasi_kas' => 'referensi_tipe', 'jurnal_umum' => 'referensi_tipe', 'reversal_transaksi' => 'source_type'] as $table => $column) {
            if (! $this->hasColumns($table, [$column])) {
                continue;
            }

            $count += DB::table($table)
                ->where(fn ($query) => $query
                    ->where($column, 'App\\Models\\SewaPrinter')
                    ->orWhere($column, 'App\\Models\\PembayaranSewaPrinter')
                    ->orWhere($column, 'like', '%SewaPrinter%'))
                ->count();
        }

        foreach (['sewa_hardware', 'pembayaran_sewa_hardware', 'mutasi_kas', 'jurnal_umum', 'reversal_transaksi'] as $table) {
            if ($this->hasColumns($table, ['idempotency_key'])) {
                $count += DB::table($table)->where('idempotency_key', 'like', 'sewa-printer:%')->count();
            }
        }

        return $count;
    }

    private function accountMapIssues(): int
    {
        $posting = config('account_map.postings', []);
        $required = ['utang_vendor', 'pendapatan_diterima_dimuka_margin', 'pendapatan_margin'];
        $missing = array_key_exists('sewa_hardware', $posting) ? 0 : 1;
        $missing += array_key_exists('sewa_printer', $posting) ? 1 : 0;

        foreach ($required as $key) {
            $missing += empty($posting['sewa_hardware'][$key] ?? null) ? 1 : 0;
        }

        return $missing;
    }

    private function activeMasterPrinterRelations(): int
    {
        return $this->legacyColumns();
    }

    private function payrollLedgerHardware(): int
    {
        if (! $this->hasColumns('pemakaian_potong_gaji', ['source_type'])) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji')
            ->whereIn('source_type', [SewaHardware::class, PembayaranSewaHardware::class])
            ->count();
    }

    private function hardDeleteRoutes(): int
    {
        return collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'sewa-hardware.')
                && in_array('DELETE', $route->methods(), true))
            ->count();
    }

    private function selfServiceRoutes(): int
    {
        return collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains((string) $route->getName(), 'sewa-hardware.karyawan')
                || str_contains($route->uri(), 'karyawan/sewa-hardware'))
            ->count();
    }

    private function approvalRoutes(): int
    {
        return collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'sewa-hardware.')
                && str_contains((string) $route->getName(), 'approve'))
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
            ->where('j.idempotency_key', 'like', 'sewa-hardware:%')
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
