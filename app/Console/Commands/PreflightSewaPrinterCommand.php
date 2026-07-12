<?php

namespace App\Console\Commands;

use App\Models\AsetKoperasi;
use App\Models\PembayaranSewaPrinter;
use App\Models\SewaPrinter;
use App\Models\SewaPrinterDetail;
use App\Services\AsetKoperasiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightSewaPrinterCommand extends Command
{
    protected $signature = 'koperasi:preflight-sewa-printer';

    protected $description = 'Audit read-only kesiapan data transaksi Sewa Printer Koperasi.';

    public function handle(AsetKoperasiService $asetService): int
    {
        $checks = [
            $this->check('kontrak_tanpa_detail', 'Kontrak Sewa Printer tanpa detail', $this->contractWithoutDetails()),
            $this->check('detail_duplikat', 'Detail Printer duplikat dalam satu kontrak', $this->duplicateDetails()),
            $this->check('detail_bukan_printer', 'Detail menunjuk aset bukan Printer', $this->detailNotPrinter()),
            $this->check('detail_orphan', 'Detail Sewa Printer orphan', $this->detailOrphan()),
            $this->check('pic_invalid', 'PIC tidak valid atau bukan Karyawan aktif', $this->invalidPic()),
            $this->check('snapshot_perusahaan_kosong', 'Snapshot perusahaan penyewa kosong', $this->emptyCompanySnapshot()),
            $this->check('periode_invalid', 'Tanggal mulai setelah tanggal selesai', $this->invalidPeriod()),
            $this->check('kontrak_overlap', 'Kontrak Sewa Printer overlap pada aset yang sama', $this->overlap()),
            $this->check('harga_dasar_invalid', 'Harga dasar nol/negatif', $this->invalidBasePrice()),
            $this->check('margin_persen_invalid', 'Margin persen bukan 15%', $this->invalidMarginPercent()),
            $this->check('margin_nominal_salah', 'Margin nominal tidak sesuai 15% half-up', $this->invalidMarginNominal()),
            $this->check('total_detail_salah', 'Total detail tidak sama harga dasar + margin', $this->invalidDetailTotal()),
            $this->check('total_header_salah', 'Total header tidak sama jumlah detail', $this->invalidHeaderTotals()),
            $this->check('pembayaran_sebagian', 'Pembayaran sebagian atau tidak sama grand total', $this->partialPayment()),
            $this->check('metode_dompet_mismatch', 'Metode pembayaran dan jenis Dompet tidak cocok', $this->methodDompetMismatch()),
            $this->check('pembayaran_tanpa_posting', 'Pembayaran tanpa Mutasi/Jurnal', $this->paymentWithoutPosting()),
            $this->check('jurnal_pembayaran_salah', 'Jurnal pembayaran tidak memakai Pendapatan Diterima Dimuka', $this->paymentJournalWithoutDeferredRevenue()),
            $this->check('pendapatan_sebelum_selesai', 'Pendapatan diakui sebelum kontrak selesai', $this->revenueBeforeCompleted()),
            $this->check('berjalan_belum_paid', 'Kontrak berjalan belum paid', $this->runningNotPaid()),
            $this->check('printer_berjalan_status_salah', 'Printer berjalan tetapi status bukan digunakan/disewa', $this->runningAssetWrongStatus()),
            $this->check('selesai_printer_masih_used', 'Kontrak selesai tetapi Printer masih digunakan/disewa tanpa kontrak lain', $this->completedAssetStillUsedWithoutRunning()),
            $this->check('jurnal_selesai_salah', 'Jurnal selesai tidak memisahkan dasar dan margin', $this->completedJournalNotSplit()),
            $this->check('refund_ganda', 'Refund Sewa Printer ganda', $this->duplicateRefund()),
            $this->check('ledger_payroll_sewa_printer', 'Sewa Printer membuat ledger payroll', $this->payrollLedgerForSewaPrinter()),
            $this->check('aset_transaksi_masih_deletable', 'Aset dengan histori Sewa Printer masih dapat dihapus', $this->assetWithSewaStillDeletable($asetService)),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight Sewa Printer');
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
            $this->error('Preflight Sewa Printer menemukan konflik kritis. Command ini hanya membaca database.');

            return self::FAILURE;
        }

        $this->info('Preflight Sewa Printer bersih: tidak ada konflik kritis yang terdeteksi.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count, bool $critical = true): array
    {
        return compact('code', 'label', 'count', 'critical');
    }

    private function contractWithoutDetails(): int
    {
        if (! $this->hasTables(['sewa_printer', 'sewa_printer_detail'])) {
            return 0;
        }

        return DB::table('sewa_printer as s')
            ->leftJoin('sewa_printer_detail as d', 'd.sewa_printer_id', '=', 's.id')
            ->whereNull('d.id')
            ->count('s.id');
    }

    private function duplicateDetails(): int
    {
        if (! Schema::hasTable('sewa_printer_detail')) {
            return 0;
        }

        return DB::table('sewa_printer_detail')
            ->select('sewa_printer_id', 'aset_koperasi_id', DB::raw('COUNT(*) as total'))
            ->groupBy('sewa_printer_id', 'aset_koperasi_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function detailNotPrinter(): int
    {
        if (! $this->hasTables(['sewa_printer_detail', 'aset_koperasi', 'aset_printer'])) {
            return 0;
        }

        return DB::table('sewa_printer_detail as d')
            ->join('aset_koperasi as a', 'a.id', '=', 'd.aset_koperasi_id')
            ->leftJoin('aset_printer as p', 'p.aset_koperasi_id', '=', 'a.id')
            ->where(function ($query): void {
                $query->where('a.jenis_aset', '!=', AsetKoperasi::JENIS_PRINTER)
                    ->orWhereNull('p.id');
            })
            ->count('d.id');
    }

    private function detailOrphan(): int
    {
        if (! $this->hasTables(['sewa_printer_detail', 'sewa_printer', 'aset_koperasi'])) {
            return 0;
        }

        return DB::table('sewa_printer_detail as d')
            ->leftJoin('sewa_printer as s', 's.id', '=', 'd.sewa_printer_id')
            ->leftJoin('aset_koperasi as a', 'a.id', '=', 'd.aset_koperasi_id')
            ->where(function ($query): void {
                $query->whereNull('s.id')->orWhereNull('a.id');
            })
            ->count('d.id');
    }

    private function invalidPic(): int
    {
        if (! $this->hasTables(['sewa_printer', 'karyawan'])) {
            return 0;
        }

        return DB::table('sewa_printer as s')
            ->leftJoin('karyawan as k', 'k.id', '=', 's.karyawan_pic_id')
            ->where(function ($query): void {
                $query->whereNull('k.id')->orWhere('k.status_kerja', '!=', 'aktif');
            })
            ->whereNotIn('s.status', [SewaPrinter::STATUS_DIBATALKAN])
            ->count('s.id');
    }

    private function emptyCompanySnapshot(): int
    {
        return Schema::hasTable('sewa_printer')
            ? DB::table('sewa_printer')->where(function ($query): void {
                $query->whereNull('nama_perusahaan_snapshot')->orWhere('nama_perusahaan_snapshot', '');
            })->count()
            : 0;
    }

    private function invalidPeriod(): int
    {
        return Schema::hasTable('sewa_printer')
            ? DB::table('sewa_printer')->whereColumn('mulai_tanggal', '>', 'selesai_tanggal')->count()
            : 0;
    }

    private function overlap(): int
    {
        if (! $this->hasTables(['sewa_printer', 'sewa_printer_detail'])) {
            return 0;
        }

        return DB::table('sewa_printer_detail as da')
            ->join('sewa_printer as a', 'a.id', '=', 'da.sewa_printer_id')
            ->join('sewa_printer_detail as db', function ($join): void {
                $join->on('db.aset_koperasi_id', '=', 'da.aset_koperasi_id')
                    ->whereColumn('da.sewa_printer_id', '<', 'db.sewa_printer_id');
            })
            ->join('sewa_printer as b', 'b.id', '=', 'db.sewa_printer_id')
            ->whereIn('a.status', [SewaPrinter::STATUS_DIKONFIRMASI, SewaPrinter::STATUS_BERJALAN])
            ->whereIn('b.status', [SewaPrinter::STATUS_DIKONFIRMASI, SewaPrinter::STATUS_BERJALAN])
            ->whereColumn('a.mulai_tanggal', '<=', 'b.selesai_tanggal')
            ->whereColumn('a.selesai_tanggal', '>=', 'b.mulai_tanggal')
            ->count('da.id');
    }

    private function invalidBasePrice(): int
    {
        return Schema::hasTable('sewa_printer_detail')
            ? DB::table('sewa_printer_detail')->where('harga_dasar', '<=', 0)->count()
            : 0;
    }

    private function invalidMarginPercent(): int
    {
        return Schema::hasTable('sewa_printer_detail')
            ? DB::table('sewa_printer_detail')->where('margin_persen_snapshot', '!=', SewaPrinterDetail::MARGIN_PERSEN)->count()
            : 0;
    }

    private function invalidMarginNominal(): int
    {
        if (! Schema::hasTable('sewa_printer_detail')) {
            return 0;
        }

        return DB::table('sewa_printer_detail')
            ->get(['harga_dasar', 'margin_nominal'])
            ->filter(fn ($row) => $this->rupiahInt($row->margin_nominal) !== $this->margin($this->rupiahInt($row->harga_dasar)))
            ->count();
    }

    private function invalidDetailTotal(): int
    {
        if (! Schema::hasTable('sewa_printer_detail')) {
            return 0;
        }

        return DB::table('sewa_printer_detail')
            ->get(['harga_dasar', 'margin_nominal', 'total_harga'])
            ->filter(fn ($row) => $this->rupiahInt($row->total_harga) !== $this->rupiahInt($row->harga_dasar) + $this->rupiahInt($row->margin_nominal))
            ->count();
    }

    private function invalidHeaderTotals(): int
    {
        if (! $this->hasTables(['sewa_printer', 'sewa_printer_detail'])) {
            return 0;
        }

        return DB::table('sewa_printer as s')
            ->leftJoin('sewa_printer_detail as d', 'd.sewa_printer_id', '=', 's.id')
            ->select(
                's.id',
                's.total_harga_dasar',
                's.total_margin',
                's.grand_total',
                DB::raw('COALESCE(SUM(d.harga_dasar),0) as detail_dasar'),
                DB::raw('COALESCE(SUM(d.margin_nominal),0) as detail_margin'),
                DB::raw('COALESCE(SUM(d.total_harga),0) as detail_total')
            )
            ->groupBy('s.id', 's.total_harga_dasar', 's.total_margin', 's.grand_total')
            ->get()
            ->filter(fn ($row) => $this->rupiahInt($row->total_harga_dasar) !== $this->rupiahInt($row->detail_dasar)
                || $this->rupiahInt($row->total_margin) !== $this->rupiahInt($row->detail_margin)
                || $this->rupiahInt($row->grand_total) !== $this->rupiahInt($row->detail_total))
            ->count();
    }

    private function partialPayment(): int
    {
        if (! $this->hasTables(['pembayaran_sewa_printer', 'sewa_printer'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_printer as p')
            ->join('sewa_printer as s', 's.id', '=', 'p.sewa_printer_id')
            ->whereColumn('p.jumlah_bayar', '!=', 's.grand_total')
            ->count('p.id');
    }

    private function methodDompetMismatch(): int
    {
        if (! $this->hasTables(['pembayaran_sewa_printer', 'dompet_koperasi'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_printer as p')
            ->join('dompet_koperasi as d', 'd.id', '=', 'p.dompet_id')
            ->where(function ($query): void {
                $query->where(fn ($q) => $q->where('p.metode_pembayaran', PembayaranSewaPrinter::METODE_TUNAI)->where('d.jenis_dompet', '!=', 'kas'))
                    ->orWhere(fn ($q) => $q->where('p.metode_pembayaran', PembayaranSewaPrinter::METODE_TRANSFER_BANK)->where('d.jenis_dompet', '!=', 'bank'));
            })
            ->count('p.id');
    }

    private function paymentWithoutPosting(): int
    {
        if (! $this->hasTables(['pembayaran_sewa_printer', 'mutasi_kas', 'jurnal_umum'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_printer as p')
            ->leftJoin('mutasi_kas as m', function ($join): void {
                $join->on('m.referensi_id', '=', 'p.id')
                    ->where('m.referensi_tipe', PembayaranSewaPrinter::class)
                    ->where('m.tipe', 'masuk');
            })
            ->leftJoin('jurnal_umum as j', function ($join): void {
                $join->on('j.referensi_id', '=', 'p.id')
                    ->where('j.referensi_tipe', PembayaranSewaPrinter::class)
                    ->where('j.idempotency_key', 'like', 'sewa-printer:pembayaran-dimuka:jurnal:%');
            })
            ->where('p.status', PembayaranSewaPrinter::STATUS_PAID)
            ->where(function ($query): void {
                $query->whereNull('m.id')->orWhereNull('j.id');
            })
            ->count('p.id');
    }

    private function paymentJournalWithoutDeferredRevenue(): int
    {
        if (! $this->hasTables(['jurnal_umum', 'jurnal_umum_detail'])) {
            return 0;
        }

        $kode = (string) config('account_map.accounts.pendapatan_diterima_dimuka_sewa_printer.kode_akun');

        return DB::table('jurnal_umum as j')
            ->leftJoin('jurnal_umum_detail as d', function ($join) use ($kode): void {
                $join->on('d.jurnal_umum_id', '=', 'j.id')
                    ->where('d.akun_kode', $kode)
                    ->where('d.kredit', '>', 0);
            })
            ->where('j.referensi_tipe', PembayaranSewaPrinter::class)
            ->where('j.idempotency_key', 'like', 'sewa-printer:pembayaran-dimuka:jurnal:%')
            ->whereNull('d.id')
            ->count('j.id');
    }

    private function revenueBeforeCompleted(): int
    {
        if (! $this->hasTables(['sewa_printer', 'jurnal_umum'])) {
            return 0;
        }

        return DB::table('jurnal_umum as j')
            ->join('sewa_printer as s', 's.id', '=', 'j.referensi_id')
            ->where('j.referensi_tipe', SewaPrinter::class)
            ->where('j.idempotency_key', 'like', 'sewa-printer:pengakuan-pendapatan:jurnal:%')
            ->where('s.status', '!=', SewaPrinter::STATUS_SELESAI)
            ->count('j.id');
    }

    private function runningNotPaid(): int
    {
        return Schema::hasTable('sewa_printer')
            ? DB::table('sewa_printer')
                ->where('status', SewaPrinter::STATUS_BERJALAN)
                ->where('status_pembayaran', '!=', SewaPrinter::PEMBAYARAN_PAID)
                ->count()
            : 0;
    }

    private function runningAssetWrongStatus(): int
    {
        if (! $this->hasTables(['sewa_printer', 'sewa_printer_detail', 'aset_koperasi'])) {
            return 0;
        }

        return DB::table('sewa_printer as s')
            ->join('sewa_printer_detail as d', 'd.sewa_printer_id', '=', 's.id')
            ->join('aset_koperasi as a', 'a.id', '=', 'd.aset_koperasi_id')
            ->where('s.status', SewaPrinter::STATUS_BERJALAN)
            ->where('a.status', '!=', AsetKoperasi::STATUS_DIGUNAKAN_DISEWA)
            ->count('d.id');
    }

    private function completedAssetStillUsedWithoutRunning(): int
    {
        if (! $this->hasTables(['sewa_printer', 'sewa_printer_detail', 'aset_koperasi'])) {
            return 0;
        }

        return DB::table('sewa_printer as s')
            ->join('sewa_printer_detail as d', 'd.sewa_printer_id', '=', 's.id')
            ->join('aset_koperasi as a', 'a.id', '=', 'd.aset_koperasi_id')
            ->where('s.status', SewaPrinter::STATUS_SELESAI)
            ->where('a.status', AsetKoperasi::STATUS_DIGUNAKAN_DISEWA)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('sewa_printer_detail as running_detail')
                    ->join('sewa_printer as running', 'running.id', '=', 'running_detail.sewa_printer_id')
                    ->whereColumn('running_detail.aset_koperasi_id', 'a.id')
                    ->where('running.status', SewaPrinter::STATUS_BERJALAN);
            })
            ->count('d.id');
    }

    private function completedJournalNotSplit(): int
    {
        if (! $this->hasTables(['sewa_printer', 'jurnal_umum', 'jurnal_umum_detail'])) {
            return 0;
        }

        $deferred = (string) config('account_map.accounts.pendapatan_diterima_dimuka_sewa_printer.kode_akun');
        $dasar = (string) config('account_map.accounts.pendapatan_sewa_printer_dasar.kode_akun');
        $margin = (string) config('account_map.accounts.pendapatan_margin_sewa_printer.kode_akun');

        return SewaPrinter::query()
            ->where('status', SewaPrinter::STATUS_SELESAI)
            ->get()
            ->filter(function (SewaPrinter $sewa) use ($deferred, $dasar, $margin): bool {
                $jurnal = DB::table('jurnal_umum')
                    ->where('referensi_tipe', SewaPrinter::class)
                    ->where('referensi_id', $sewa->id)
                    ->where('idempotency_key', 'like', 'sewa-printer:pengakuan-pendapatan:jurnal:%')
                    ->first();

                if (! $jurnal) {
                    return true;
                }

                $details = DB::table('jurnal_umum_detail')
                    ->where('jurnal_umum_id', $jurnal->id)
                    ->get()
                    ->groupBy('akun_kode');

                return $this->rupiahInt($details->get($deferred)?->sum('debit') ?? 0) !== $this->rupiahInt($sewa->grand_total)
                    || $this->rupiahInt($details->get($dasar)?->sum('kredit') ?? 0) !== $this->rupiahInt($sewa->total_harga_dasar)
                    || $this->rupiahInt($details->get($margin)?->sum('kredit') ?? 0) !== $this->rupiahInt($sewa->total_margin);
            })
            ->count();
    }

    private function duplicateRefund(): int
    {
        if (! $this->hasTables(['pembayaran_sewa_printer', 'mutasi_kas', 'jurnal_umum'])) {
            return 0;
        }

        $mutasi = DB::table('mutasi_kas')
            ->select('referensi_id', DB::raw('COUNT(*) as total'))
            ->where('referensi_tipe', PembayaranSewaPrinter::class)
            ->where('idempotency_key', 'like', 'sewa-printer:refund:mutasi:%')
            ->groupBy('referensi_id')
            ->having('total', '>', 1)
            ->get()
            ->count();

        $jurnal = DB::table('jurnal_umum')
            ->select('referensi_id', DB::raw('COUNT(*) as total'))
            ->where('referensi_tipe', PembayaranSewaPrinter::class)
            ->where('idempotency_key', 'like', 'sewa-printer:refund:jurnal:%')
            ->groupBy('referensi_id')
            ->having('total', '>', 1)
            ->get()
            ->count();

        return $mutasi + $jurnal;
    }

    private function payrollLedgerForSewaPrinter(): int
    {
        if (! Schema::hasTable('pemakaian_potong_gaji')) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji')
            ->whereIn('source_type', [SewaPrinter::class, SewaPrinterDetail::class, PembayaranSewaPrinter::class])
            ->count();
    }

    private function assetWithSewaStillDeletable(AsetKoperasiService $service): int
    {
        if (! Schema::hasTable('aset_koperasi')) {
            return 0;
        }

        return AsetKoperasi::query()
            ->printer()
            ->with('printer')
            ->get()
            ->filter(function (AsetKoperasi $aset) use ($service): bool {
                $dependencies = $service->dependencyCounts($aset);
                $guard = $service->canDelete($aset);

                return (($dependencies['Sewa Printer Detail'] ?? 0) > 0) && $guard['allowed'];
            })
            ->count();
    }

    private function margin(int $hargaDasar): int
    {
        return intdiv(($hargaDasar * SewaPrinterDetail::MARGIN_PERSEN) + 50, 100);
    }

    private function rupiahInt(mixed $value): int
    {
        return (int) round((float) $value);
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
