<?php

namespace App\Console\Commands;

use App\Models\SewaPrinter;
use App\Models\SewaPrinterDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightSewaPrinterCommand extends Command
{
    protected $signature = 'koperasi:preflight-sewa-printer';

    protected $description = 'Audit read-only kesiapan transaksi Sewa Printer vendor-based.';

    public function handle(): int
    {
        $checks = [
            $this->check('schema_legacy', 'Schema Sewa Printer masih memakai struktur aset printer legacy', $this->legacySchemaIssues()),
            $this->check('kode_invalid', 'Kode Sewa Printer tidak sesuai format SWP-YYYYMM-000001', $this->invalidKode()),
            $this->check('karyawan_nonaktif', 'Sewa Printer milik Karyawan nonaktif/berhenti', $this->inactiveEmployees()),
            $this->check('vendor_snapshot_missing', 'Snapshot vendor tidak lengkap', $this->missingVendorSnapshot()),
            $this->check('detail_missing', 'Kontrak Sewa Printer tanpa detail kebutuhan', $this->withoutDetails()),
            $this->check('detail_invalid', 'Detail Sewa Printer tidak valid atau subtotal tidak konsisten', $this->invalidDetails()),
            $this->check('header_total_invalid', 'Total header Sewa Printer tidak sama dengan detail', $this->invalidHeaderTotals()),
            $this->check('paid_tanpa_pembayaran', 'Sewa Printer paid tanpa pembayaran', $this->paidWithoutPayment()),
            $this->check('pembayaran_invalid', 'Nominal pembayaran printer tidak sama dengan snapshot tagihan/vendor', $this->invalidPayments()),
            $this->check('dompet_invalid', 'Dompet pembayaran printer tidak sesuai metode atau COA', $this->invalidPaymentDompets()),
            $this->check('mutasi_missing', 'Pembayaran printer tanpa Mutasi masuk/keluar resmi', $this->paymentWithoutMutasi()),
            $this->check('jurnal_missing', 'Pembayaran printer tanpa Jurnal penerimaan/vendor resmi', $this->paymentWithoutJournals()),
            $this->check('jurnal_pengakuan_missing', 'Sewa Printer selesai tanpa Jurnal pengakuan margin', $this->completedWithoutRevenueJournal()),
            $this->check('akun_405_dipakai_baru', 'Transaksi Sewa Printer baru masih memakai akun legacy 405', $this->newTransactionsUsingLegacyRevenueAccount()),
            $this->check('jurnal_unbalanced', 'Jurnal Sewa Printer tidak balance', $this->unbalancedJournals()),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight Sewa Printer (read-only)');
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
            $this->error('Preflight Sewa Printer menemukan konflik kritis. Command ini tidak menulis database.');

            return self::FAILURE;
        }

        $this->info('Preflight Sewa Printer bersih.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count, bool $critical = true): array
    {
        return compact('code', 'label', 'count', 'critical');
    }

    private function legacySchemaIssues(): int
    {
        if (! Schema::hasTable('sewa_printer')) {
            return 0;
        }

        $requiredHeader = [
            'karyawan_id',
            'vendor_nama',
            'vendor_kontak',
            'vendor_alamat',
            'total_harga_vendor',
            'total_margin',
            'total_tagihan_perusahaan',
            'recorded_by',
        ];
        $legacyHeader = ['karyawan_pic_id', 'total_harga_dasar', 'grand_total', 'aset_koperasi_id', 'aset_printer_id'];
        $requiredDetail = [
            'jenis_model_printer',
            'kuantitas',
            'harga_vendor_per_unit',
            'margin_per_unit',
            'harga_tagihan_per_unit',
            'subtotal_harga_vendor',
            'subtotal_margin',
            'subtotal_tagihan',
        ];
        $legacyDetail = ['aset_koperasi_id', 'kode_aset_snapshot', 'nomor_seri_snapshot', 'harga_dasar', 'margin_nominal', 'total_harga'];
        $requiredPayment = ['dompet_penerimaan_id', 'dompet_vendor_id', 'metode_penerimaan', 'metode_pembayaran_vendor', 'jumlah_diterima', 'jumlah_bayar_vendor'];
        $legacyPayment = ['dompet_id', 'metode_pembayaran', 'jumlah_bayar', 'refunded_at'];

        return collect($requiredHeader)->filter(fn (string $column): bool => ! Schema::hasColumn('sewa_printer', $column))->count()
            + collect($legacyHeader)->filter(fn (string $column): bool => Schema::hasColumn('sewa_printer', $column))->count()
            + collect($requiredDetail)->filter(fn (string $column): bool => ! Schema::hasColumn('sewa_printer_detail', $column))->count()
            + collect($legacyDetail)->filter(fn (string $column): bool => Schema::hasColumn('sewa_printer_detail', $column))->count()
            + collect($requiredPayment)->filter(fn (string $column): bool => ! Schema::hasColumn('pembayaran_sewa_printer', $column))->count()
            + collect($legacyPayment)->filter(fn (string $column): bool => Schema::hasColumn('pembayaran_sewa_printer', $column))->count();
    }

    private function invalidKode(): int
    {
        if (! $this->hasColumns('sewa_printer', ['kode_sewa'])) {
            return 0;
        }

        return DB::table('sewa_printer')
            ->pluck('kode_sewa')
            ->filter(fn ($kode): bool => preg_match('/^SWP-[0-9]{6}-[0-9]{6}$/', (string) $kode) !== 1)
            ->count();
    }

    private function inactiveEmployees(): int
    {
        if (! $this->hasColumns('sewa_printer', ['karyawan_id']) || ! $this->hasColumns('karyawan', ['status_kerja'])) {
            return 0;
        }

        return DB::table('sewa_printer as s')
            ->join('karyawan as k', 'k.id', '=', 's.karyawan_id')
            ->where('k.status_kerja', '!=', 'aktif')
            ->count('s.id');
    }

    private function missingVendorSnapshot(): int
    {
        if (! $this->hasColumns('sewa_printer', ['vendor_nama', 'vendor_kontak', 'vendor_alamat'])) {
            return 0;
        }

        return DB::table('sewa_printer')
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
        if (! $this->hasColumns('sewa_printer', ['id']) || ! $this->hasColumns('sewa_printer_detail', ['sewa_printer_id'])) {
            return 0;
        }

        return DB::table('sewa_printer as s')
            ->leftJoin('sewa_printer_detail as d', 'd.sewa_printer_id', '=', 's.id')
            ->whereNull('d.id')
            ->count('s.id');
    }

    private function invalidDetails(): int
    {
        if (! $this->hasColumns('sewa_printer_detail', [
            'jenis_model_printer',
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

        return DB::table('sewa_printer_detail')
            ->get()
            ->filter(function ($row): bool {
                $qty = (int) $row->kuantitas;
                $price = (int) $row->harga_vendor_per_unit;
                $margin = intdiv(($price * SewaPrinterDetail::MARGIN_PERSEN) + 50, 100);
                $tagihan = $price + $margin;

                return trim((string) $row->jenis_model_printer) === ''
                    || $qty <= 0
                    || $price <= 0
                    || (int) $row->margin_persen_snapshot !== SewaPrinterDetail::MARGIN_PERSEN
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
        if (! $this->hasColumns('sewa_printer', ['total_harga_vendor', 'total_margin', 'total_tagihan_perusahaan'])
            || ! $this->hasColumns('sewa_printer_detail', ['sewa_printer_id', 'subtotal_harga_vendor', 'subtotal_margin', 'subtotal_tagihan'])) {
            return 0;
        }

        return DB::table('sewa_printer as s')
            ->leftJoin('sewa_printer_detail as d', 'd.sewa_printer_id', '=', 's.id')
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
        if (! $this->hasColumns('sewa_printer', ['status_pembayaran']) || ! Schema::hasTable('pembayaran_sewa_printer')) {
            return 0;
        }

        return DB::table('sewa_printer as s')
            ->leftJoin('pembayaran_sewa_printer as p', 'p.sewa_printer_id', '=', 's.id')
            ->where('s.status_pembayaran', SewaPrinter::PEMBAYARAN_PAID)
            ->whereNull('p.id')
            ->count('s.id');
    }

    private function invalidPayments(): int
    {
        if (! $this->hasColumns('sewa_printer', ['total_harga_vendor', 'total_tagihan_perusahaan'])
            || ! $this->hasColumns('pembayaran_sewa_printer', ['sewa_printer_id', 'jumlah_diterima', 'jumlah_bayar_vendor'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_printer as p')
            ->join('sewa_printer as s', 's.id', '=', 'p.sewa_printer_id')
            ->where(fn ($query) => $query
                ->whereColumn('p.jumlah_diterima', '!=', 's.total_tagihan_perusahaan')
                ->orWhereColumn('p.jumlah_bayar_vendor', '!=', 's.total_harga_vendor'))
            ->count('p.id');
    }

    private function invalidPaymentDompets(): int
    {
        if (! $this->hasColumns('pembayaran_sewa_printer', ['dompet_penerimaan_id', 'dompet_vendor_id', 'metode_penerimaan', 'metode_pembayaran_vendor'])
            || ! $this->hasColumns('dompet_koperasi', ['jenis_dompet', 'akun_id'])
            || ! $this->hasColumns('akun', ['kategori', 'posisi_saldo', 'is_aktif'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_printer as p')
            ->join('dompet_koperasi as dp', 'dp.id', '=', 'p.dompet_penerimaan_id')
            ->join('akun as ap', 'ap.id', '=', 'dp.akun_id')
            ->join('dompet_koperasi as dv', 'dv.id', '=', 'p.dompet_vendor_id')
            ->join('akun as av', 'av.id', '=', 'dv.akun_id')
            ->where(function ($query): void {
                $query->where(fn ($q) => $q->where('p.metode_penerimaan', 'tunai')->where('dp.jenis_dompet', '!=', 'kas'))
                    ->orWhere(fn ($q) => $q->where('p.metode_penerimaan', 'transfer_bank')->where('dp.jenis_dompet', '!=', 'bank'))
                    ->orWhere(fn ($q) => $q->where('p.metode_pembayaran_vendor', 'tunai')->where('dv.jenis_dompet', '!=', 'kas'))
                    ->orWhere(fn ($q) => $q->where('p.metode_pembayaran_vendor', 'transfer_bank')->where('dv.jenis_dompet', '!=', 'bank'))
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
        if (! $this->hasColumns('pembayaran_sewa_printer', ['id']) || ! $this->hasColumns('mutasi_kas', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_printer')
            ->where('status', 'paid')
            ->pluck('id')
            ->filter(fn ($id): bool => ! DB::table('mutasi_kas')->where('idempotency_key', 'sewa-printer:penerimaan:mutasi:' . $id)->exists()
                || ! DB::table('mutasi_kas')->where('idempotency_key', 'sewa-printer:pembayaran-vendor:mutasi:' . $id)->exists())
            ->count();
    }

    private function paymentWithoutJournals(): int
    {
        if (! $this->hasColumns('pembayaran_sewa_printer', ['id']) || ! $this->hasColumns('jurnal_umum', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_printer')
            ->where('status', 'paid')
            ->pluck('id')
            ->filter(fn ($id): bool => ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-printer:pembayaran-dimuka:jurnal:' . $id)->exists()
                || ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-printer:pembayaran-vendor:jurnal:' . $id)->exists())
            ->count();
    }

    private function completedWithoutRevenueJournal(): int
    {
        if (! $this->hasColumns('sewa_printer', ['id', 'status']) || ! $this->hasColumns('jurnal_umum', ['idempotency_key'])) {
            return 0;
        }

        return DB::table('sewa_printer')
            ->where('status', SewaPrinter::STATUS_SELESAI)
            ->pluck('id')
            ->filter(fn ($id): bool => ! DB::table('jurnal_umum')->where('idempotency_key', 'sewa-printer:pengakuan-pendapatan:jurnal:' . $id)->exists())
            ->count();
    }

    private function newTransactionsUsingLegacyRevenueAccount(): int
    {
        if (! $this->hasColumns('jurnal_umum', ['id', 'idempotency_key']) || ! $this->hasColumns('jurnal_umum_detail', ['jurnal_umum_id', 'akun_kode'])) {
            return 0;
        }

        return DB::table('jurnal_umum as j')
            ->select('j.id')
            ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
            ->where('j.idempotency_key', 'like', 'sewa-printer:%')
            ->where('d.akun_kode', '405')
            ->count('d.id');
    }

    private function unbalancedJournals(): int
    {
        if (! $this->hasColumns('jurnal_umum', ['id', 'idempotency_key']) || ! $this->hasColumns('jurnal_umum_detail', ['jurnal_umum_id', 'debit', 'kredit'])) {
            return 0;
        }

        return DB::table('jurnal_umum as j')
            ->select('j.id')
            ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
            ->where('j.idempotency_key', 'like', 'sewa-printer:%')
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
