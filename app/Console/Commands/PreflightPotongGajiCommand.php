<?php

namespace App\Console\Commands;

use App\Models\JenisSimpanan;
use App\Models\PembayaranSewaMobil;
use App\Models\PembayaranSewaHardware;
use App\Models\ReversalTransaksi;
use App\Models\SewaMobil;
use App\Models\SewaHardware;
use App\Models\Simpanan;
use App\Services\PotongGajiReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class PreflightPotongGajiCommand extends Command
{
    protected $signature = 'koperasi:preflight-potong-gaji';

    protected $description = 'Audit read-only kesiapan data untuk fondasi mesin potong gaji bulanan.';

    public function handle(): int
    {
        $checks = [
            $this->check('penjualan_tanpa_pembayaran', 'Penjualan tanpa Pembayaran', $this->penjualanTanpaPembayaran()),
            $this->check('pembayaran_ganda', 'Pembayaran ganda untuk satu Penjualan', $this->pembayaranGanda()),
            $this->check('simpanan_tanpa_anggota', 'Simpanan tanpa mapping Anggota', $this->memberTableWithoutAnggota('simpanan')),
            $this->check('pinjaman_tanpa_anggota', 'Pinjaman tanpa mapping Anggota', $this->memberTableWithoutAnggota('pinjaman')),
            $this->check('pinjaman_aktif_tanpa_siklus', 'Pinjaman aktif tanpa siklus keanggotaan', $this->pinjamanAktifTanpaSiklus()),
            $this->check('pinjaman_siklus_anggota_mismatch', 'Pinjaman merujuk siklus milik Anggota lain', $this->pinjamanSiklusAnggotaMismatch()),
            $this->check('pinjaman_nonaktif', 'Pinjaman milik Anggota/Karyawan nonaktif', $this->pinjamanNonaktif()),
            $this->check('shu_tanpa_anggota', 'SHU Anggota tanpa mapping Anggota', $this->memberTableWithoutAnggota('shu_anggota')),
            $this->check('pinjaman_aktif_ganda', 'Lebih dari satu Pinjaman aktif per Anggota', $this->pinjamanAktifGanda()),
            $this->check('cicilan_ganda', 'Cicilan ganda dalam Pinjaman dan periode yang sama', $this->cicilanGanda()),
            $this->check('pinjaman_berbunga', 'Pinjaman berbunga bukan 0%', $this->pinjamanWhere('bunga_persen', '!=', 0)),
            $this->check('pinjaman_tenor_invalid', 'Pinjaman tenor di luar 1-12 bulan', $this->pinjamanTenorInvalid()),
            $this->check('pinjaman_nominal_besar', 'Pinjaman lebih dari Rp5.000.000', $this->pinjamanWhere('jumlah_pinjaman', '>', 5000000)),
            $this->check('pinjaman_melebihi_plafon', 'Pinjaman lebih besar dari plafon Anggota', $this->pinjamanMelebihiPlafon()),
            $this->check('pinjaman_tanpa_jadwal', 'Pinjaman tanpa jadwal Cicilan', $this->pinjamanTanpaJadwal()),
            $this->check('jadwal_jumlah_baris', 'Jumlah baris jadwal tidak sama dengan tenor', $this->jadwalCountMismatch()),
            $this->check('jadwal_total_pokok', 'Total jadwal tidak sama dengan pokok Pinjaman', $this->jadwalTotalMismatch()),
            $this->check('jadwal_periode_ganda', 'Periode jadwal duplikat', $this->jadwalDuplicate('periode')),
            $this->check('jadwal_angsuran_ganda', 'Urutan angsuran jadwal duplikat', $this->jadwalDuplicate('angsuran_ke')),
            $this->check('jadwal_pertama_salah', 'Cicilan pertama bukan bulan setelah pencairan', $this->jadwalPertamaSalah()),
            $this->check('dompet_tanpa_coa', 'Dompet tanpa mapping COA Aset aktif Debit', $this->dompetTanpaCoa()),
            $this->check('bank_default_payroll_hilang', 'Tidak ada Bank default payroll', $this->bankDefaultPayrollMissing()),
            $this->check('bank_default_payroll_ganda', 'Lebih dari satu Bank default payroll', $this->bankDefaultPayrollMultiple()),
            $this->check('bank_default_payroll_coa_invalid', 'Bank default payroll tanpa COA valid', $this->bankDefaultPayrollInvalidCoa()),
            $this->check('kebijakan_limit_umum_hilang', 'Kebijakan limit umum potong gaji tidak tersedia', $this->kebijakanLimitUmumMissing()),
            $this->check('kebijakan_limit_umum_ganda', 'Lebih dari satu kebijakan limit umum aktif terbuka', $this->kebijakanLimitUmumMultiple()),
            $this->check('anggota_aktif_tanpa_limit_periode_terbuka', 'Anggota aktif tanpa limit pada periode terbuka', $this->anggotaAktifTanpaLimitPeriodeTerbuka()),
            $this->check('limit_anggota_periode_duplikat', 'Duplicate limit Anggota/periode', $this->duplicateLimitAnggotaPeriode()),
            $this->check('karyawan_aktif_tanpa_perusahaan', 'Karyawan aktif tanpa perusahaan', $this->karyawanAktifTanpaPerusahaan()),
            $this->check('override_limit_aktif_ganda', 'Override limit aktif ganda per Anggota', $this->overrideLimitAktifGanda()),
            $this->check('limit_khusus_tanpa_audit', 'Limit khusus tanpa audit atau alasan', $this->limitKhususTanpaAudit()),
            $this->check('kredit_waserba_nonaktif_tanpa_alasan', 'Kredit Waserba nonaktif tanpa alasan', $this->kreditWaserbaNonaktifTanpaAlasan()),
            $this->check('limit_terpakai_berubah', 'Limit yang sudah dipakai berubah setelah ledger dibuat', $this->limitTerpakaiBerubah()),
            $this->check('manasuka_masuk_payroll', 'Simpanan Manasuka masuk payroll', $this->manasukaMasukPayroll()),
            $this->check('prioritas_payroll_invalid', 'Prioritas Cicilan/Wajib/POS tidak sesuai', $this->payrollPriorityInvalid()),
            $this->check('mutasi_pencairan_pinjaman', 'Mutasi pencairan Pinjaman ganda/hilang', $this->pinjamanPostingIssues('mutasi_kas')),
            $this->check('jurnal_pencairan_pinjaman', 'Jurnal pencairan Pinjaman ganda/hilang', $this->pinjamanPostingIssues('jurnal_umum')),
            $this->check('reservasi_tanpa_limit', 'Reservasi Cicilan tanpa limit', $this->reservasiTanpaLimit()),
            $this->check('reservasi_nominal_mismatch', 'Reservasi Cicilan tidak sama dengan nominal sisa jadwal', $this->reservasiNominalMismatch()),
            $this->check('reservasi_ganda', 'Reservasi Cicilan ganda', $this->reservasiGanda()),
            $this->check('cicilan_due_tanpa_ledger', 'Cicilan jatuh tempo/tunggakan pada limit active belum terreservasi', $this->cicilanDueTanpaLedgerPadaLimitActive()),
            $this->check('pos_payroll_cicilan_unreserved', 'POS payroll tercatat saat Cicilan prioritas belum terreservasi', $this->posPayrollSaatCicilanUnreserved()),
            $this->check('jadwal_reserved_tanpa_ledger', 'Jadwal reserved tanpa ledger reserved', $this->jadwalReservedTanpaLedger()),
            $this->check('ledger_settled_tanpa_pembayaran', 'Ledger settled tanpa pembayaran', $this->ledgerSettledTanpaPembayaran()),
            $this->check('ledger_settled_jadwal_belum_paid', 'Ledger settled tetapi jadwal belum paid', $this->ledgerSettledJadwalBelumPaid()),
            $this->check('pembayaran_tanpa_jadwal', 'Pembayaran Cicilan tanpa jadwal', $this->pembayaranTanpaJadwal()),
            $this->check('pembayaran_ganda_jadwal', 'Pembayaran ganda untuk satu jadwal', $this->pembayaranGandaJadwal()),
            $this->check('jadwal_paid_tanpa_pembayaran', 'Jadwal paid tanpa pembayaran', $this->jadwalPaidTanpaPembayaran()),
            $this->check('jadwal_paid_nominal_sisa', 'Jadwal paid masih memiliki nominal_sisa', $this->jadwalPaidNominalSisaNonZero()),
            $this->check('jadwal_unpaid_nominal_sisa_nol', 'Jadwal unpaid masih berstatus terbuka tetapi nominal_sisa nol', $this->jadwalUnpaidNominalSisaZero()),
            $this->check('sisa_pinjaman_mismatch', 'Sisa Pinjaman tidak sama dengan total jadwal unpaid', $this->sisaPinjamanMismatch()),
            $this->check('pinjaman_lunas_jadwal_unpaid', 'Pinjaman lunas masih mempunyai jadwal unpaid', $this->pinjamanLunasJadwalUnpaid()),
            $this->check('mutasi_pembayaran_cicilan', 'Mutasi pembayaran Cicilan ganda/hilang', $this->cicilanPostingIssues('mutasi_kas')),
            $this->check('jurnal_pembayaran_cicilan', 'Jurnal pembayaran Cicilan ganda/hilang', $this->cicilanPostingIssues('jurnal_umum')),
            $this->check('payroll_dompet_salah', 'Pembayaran payroll masuk ke Dompet selain Bank snapshot', $this->payrollDompetSalah()),
            $this->check('tunai_karyawan_aktif', 'Pembayaran tunai milik Karyawan aktif', $this->pembayaranTunaiKaryawanAktif()),
            $this->check('tunai_dengan_ledger_aktif', 'Pembayaran tunai terjadi saat ledger payroll Cicilan masih aktif', $this->pembayaranTunaiDenganLedgerAktif()),
            $this->check('limit_confirmed_reserved', 'Limit confirmed masih mempunyai ledger reserved', $this->limitStatusMasihReserved('confirmed')),
            $this->check('limit_confirmed_consumed', 'Limit confirmed masih mempunyai ledger consumed', $this->limitStatusMasihConsumed('confirmed')),
            $this->check('limit_cancelled_reserved', 'Limit cancelled masih mempunyai reservasi aktif', $this->limitStatusMasihReserved('cancelled')),
            $this->check('pos_anggota_metode_invalid', 'POS Anggota aktif memakai metode selain Tunai/Potong Gaji', $this->posAnggotaMetodeInvalid()),
            $this->check('pos_payroll_tanpa_ledger', 'POS Potong Gaji tanpa ledger payroll', $this->posPayrollTanpaLedger()),
            $this->check('pos_ledger_nominal_mismatch', 'Ledger POS tidak sama dengan grand total', $this->posLedgerNominalMismatch()),
            $this->check('pos_payroll_settled_mismatch', 'POS payroll settled/payment tidak konsisten', $this->posPayrollSettlementMismatch()),
            $this->check('simpanan_pokok_ganda', 'Lebih dari satu Simpanan Pokok per Anggota', $this->simpananPokokGanda()),
            $this->check('simpanan_pokok_manual', 'Simpanan Pokok dibuat manual tanpa payroll lifecycle', $this->simpananPokokManual()),
            $this->check('simpanan_pokok_ledger_mismatch', 'Ledger Simpanan Pokok tidak sesuai transaksi', $this->simpananPokokLedgerMismatch()),
            $this->check('jenis_simpanan_aktif_ganda', 'Master aktif ganda per kategori Simpanan', $this->jenisSimpananAktifGanda()),
            $this->check('jenis_simpanan_kategori_invalid', 'Kategori Jenis Simpanan null/tidak dikenal', $this->jenisSimpananKategoriInvalid()),
            $this->check('jenis_simpanan_kode_mismatch', 'Kode sistem tidak sesuai kategori Jenis Simpanan', $this->jenisSimpananKodeMismatch()),
            $this->check('jenis_simpanan_interval_invalid', 'Simpanan Wajib final masih mempunyai interval', $this->jenisSimpananIntervalInvalid()),
            $this->check('jenis_simpanan_interval_terlarang', 'Simpanan Pokok/Manasuka mempunyai interval', $this->jenisSimpananIntervalTerlarang()),
            $this->check('jenis_simpanan_nominal_invalid', 'Simpanan Wajib final bukan Rp10.000 atau nominal legacy invalid', $this->jenisSimpananNominalInvalid()),
            $this->check('jenis_simpanan_coa_invalid', 'Master aktif tanpa COA valid', $this->jenisSimpananCoaInvalid()),
            $this->check('simpanan_reference_invalid', 'Transaksi Simpanan tanpa Jenis/Anggota/Dompet valid', $this->simpananReferenceInvalid()),
            $this->check('simpanan_posting_dompet_mismatch', 'Mutasi dan Jurnal Simpanan memakai Dompet/COA debit berbeda', $this->simpananPostingDompetMismatch()),
            $this->check('simpanan_idempotency_duplicate', 'Duplicate idempotency Simpanan/Mutasi/Jurnal', $this->duplicateIdempotency(['simpanan', 'mutasi_kas', 'jurnal_umum'])),
            $this->check('simpanan_pokok_snapshot_hilang', 'Snapshot Simpanan Pokok hilang', $this->simpananPokokSnapshotHilang()),
            $this->check('source_reversed_tanpa_reversal', 'Source reversed/refunded/cancelled tanpa record reversal', $this->sourceReversedTanpaReversal()),
            $this->check('reversal_tanpa_source', 'Reversal tanpa source', $this->reversalTanpaSource()),
            $this->check('reversal_ganda', 'Reversal ganda untuk source yang sama', $this->reversalGanda()),
            $this->check('nominal_reversal_mismatch', 'Nominal reversal berbeda dari source', $this->nominalReversalMismatch()),
            $this->check('ledger_reversed_payment_paid', 'Ledger reversed tetapi Pembayaran masih paid', $this->ledgerReversedPaymentPaid()),
            $this->check('kredit_tanpa_reversal', 'Kredit refund tanpa reversal', $this->kreditTanpaReversal()),
            $this->check('kredit_melebihi_nominal', 'Kredit digunakan melebihi nominal atau sisa salah', $this->kreditNominalMismatch()),
            $this->check('kredit_anggota_lain', 'Kredit diterapkan ke Anggota lain', $this->kreditAnggotaLain()),
            $this->check('net_payroll_negatif', 'Net payroll negatif setelah kredit', $this->netPayrollNegatif()),
            $this->check('outstanding_tanpa_pembayaran', 'Sumber outstanding settled tanpa pembayaran cash', $this->outstandingSettledTanpaPembayaran()),
            $this->check('pembayaran_outstanding_orphan', 'Pembayaran outstanding tanpa source/alokasi valid', $this->pembayaranOutstandingOrphan()),
            $this->check('cicilan_reversed_jadwal_paid', 'Cicilan direversal tetapi jadwal masih paid', $this->cicilanReversedJadwalPaid()),
            $this->check('mutasi_refund_ganda', 'Mutasi refund ganda', $this->mutasiRefundGanda()),
            $this->check('jurnal_reversal_tidak_seimbang', 'Jurnal reversal tidak seimbang', $this->jurnalReversalTidakSeimbang()),
            $this->check('account_map_report_hilang', 'Account map report payroll belum lengkap', $this->reportAccountMapMissing()),
            $this->check('rekonsiliasi_payroll_selisih', 'Selisih rekonsiliasi payroll periode berjalan', $this->payrollReconciliationMismatch()),
            $this->check('hardcoded_limit_2jt', 'Hard-coded limit Rp2.000.000 masih aktif di kode', $this->hardcodedLimitTwoMillion(), false),
            $this->check('route_hard_delete_edit_keuangan', 'Route hard delete/edit transaksi keuangan masih tersedia', $this->routeHardDeleteEditTransaksi()),
            $this->check('transaksi_nonaktif', 'Karyawan/Anggota nonaktif yang memiliki transaksi baru', $this->transaksiSetelahNonaktif()),
            $this->check('mutasi_orphan', 'Referensi Mutasi Kas ambigu/orphan', $this->referenceIssues('mutasi_kas')),
            $this->check('jurnal_orphan', 'Referensi Jurnal Umum ambigu/orphan', $this->referenceIssues('jurnal_umum')),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight potong gaji bulanan');
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
            $this->error('Preflight menemukan konflik kritis. Migration/engine tidak boleh menebak mapping atau klasifikasi data.');

            return self::FAILURE;
        }

        $this->info('Preflight bersih: tidak ada konflik kritis yang terdeteksi.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count, bool $critical = true): array
    {
        return compact('code', 'label', 'count', 'critical');
    }

    private function penjualanTanpaPembayaran(): int
    {
        if (! $this->hasTables(['penjualan', 'pembayaran'])) {
            return 0;
        }

        return DB::table('penjualan as p')
            ->leftJoin('pembayaran as pb', 'pb.penjualan_id', '=', 'p.id')
            ->whereNull('pb.id')
            ->count('p.id');
    }

    private function pembayaranGanda(): int
    {
        if (! Schema::hasTable('pembayaran')) {
            return 0;
        }

        return DB::table('pembayaran')
            ->select('penjualan_id', DB::raw('COUNT(*) as total'))
            ->groupBy('penjualan_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function memberTableWithoutAnggota(string $table): int
    {
        if (! $this->hasTables([$table, 'anggota'])) {
            return 0;
        }

        if (Schema::hasColumn($table, 'anggota_id')) {
            return DB::table("{$table} as t")
                ->leftJoin('anggota as a', 'a.id', '=', 't.anggota_id')
                ->where(function ($query): void {
                    $query->whereNull('t.anggota_id')
                        ->orWhereNull('a.id');
                })
                ->count('t.id');
        }

        return DB::table("{$table} as t")
            ->leftJoin('anggota as a', 'a.karyawan_id', '=', 't.karyawan_id')
            ->whereNull('a.id')
            ->count('t.id');
    }

    private function pinjamanAktifGanda(): int
    {
        if (! $this->hasTables(['pinjaman', 'anggota'])) {
            return 0;
        }

        $query = DB::table('pinjaman as p')
            ->where('p.status', 'aktif');

        if (Schema::hasColumn('pinjaman', 'anggota_id')) {
            $query->whereNotNull('p.anggota_id')
                ->select('p.anggota_id as anggota_key', DB::raw('COUNT(*) as total'))
                ->groupBy('p.anggota_id');
        } else {
            $query->join('anggota as a', 'a.karyawan_id', '=', 'p.karyawan_id')
                ->select('a.id as anggota_key', DB::raw('COUNT(*) as total'))
                ->groupBy('a.id');
        }

        return $query->having('total', '>', 1)->get()->count();
    }

    private function pinjamanNonaktif(): int
    {
        if (! $this->hasTables(['pinjaman', 'anggota', 'karyawan'])) {
            return 0;
        }

        $query = DB::table('pinjaman as p')
            ->join('karyawan as k', 'k.id', '=', 'p.karyawan_id');

        if (Schema::hasColumn('pinjaman', 'anggota_id')) {
            $query->leftJoin('anggota as a', 'a.id', '=', 'p.anggota_id');
        } else {
            $query->leftJoin('anggota as a', 'a.karyawan_id', '=', 'p.karyawan_id');
        }

        return $query
            ->addSelect([
                'p.id',
                'p.tanggal_pinjaman as tanggal_transaksi',
                'a.status as status_anggota',
                'a.tanggal_nonaktif',
                'k.status_kerja',
                'k.tanggal_berhenti',
            ])
            ->get()
            ->filter(fn ($row) => $row->status_anggota === null || $this->isAfterInactiveDate($row))
            ->count();
    }

    private function pinjamanAktifTanpaSiklus(): int
    {
        if (! Schema::hasTable('pinjaman') || ! Schema::hasColumn('pinjaman', 'siklus_keanggotaan_id')) {
            return 0;
        }

        return DB::table('pinjaman')
            ->where('status', 'aktif')
            ->whereRaw('CAST(sisa_pinjaman AS DECIMAL(15,2)) > 0')
            ->whereNull('siklus_keanggotaan_id')
            ->count();
    }

    private function pinjamanSiklusAnggotaMismatch(): int
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

    private function cicilanGanda(): int
    {
        if (! Schema::hasTable('cicilan_pinjaman')) {
            return 0;
        }

        return DB::table('cicilan_pinjaman')
            ->select('pinjaman_id', 'periode', DB::raw('COUNT(*) as total'))
            ->groupBy('pinjaman_id', 'periode')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function pinjamanWhere(string $column, string $operator, int|float $value): int
    {
        if (! Schema::hasTable('pinjaman')) {
            return 0;
        }

        return DB::table('pinjaman')->where($column, $operator, $value)->count();
    }

    private function pinjamanTenorInvalid(): int
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

    private function pinjamanMelebihiPlafon(): int
    {
        if (! $this->hasTables(['pinjaman', 'anggota'])) {
            return 0;
        }

        $query = DB::table('pinjaman as p');

        if (Schema::hasColumn('pinjaman', 'anggota_id')) {
            $query->join('anggota as a', 'a.id', '=', 'p.anggota_id');
        } else {
            $query->join('anggota as a', 'a.karyawan_id', '=', 'p.karyawan_id');
        }

        return $query
            ->whereColumn('p.jumlah_pinjaman', '>', 'a.plafon_pinjaman')
            ->count('p.id');
    }

    private function pinjamanTanpaJadwal(): int
    {
        if (! $this->hasTables(['pinjaman', 'jadwal_cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->leftJoin('jadwal_cicilan_pinjaman as j', 'j.pinjaman_id', '=', 'p.id')
            ->whereIn('p.status', ['aktif', 'lunas'])
            ->whereNull('j.id')
            ->count('p.id');
    }

    private function jadwalCountMismatch(): int
    {
        if (! $this->hasTables(['pinjaman', 'jadwal_cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->leftJoin('jadwal_cicilan_pinjaman as j', 'j.pinjaman_id', '=', 'p.id')
            ->whereIn('p.status', ['aktif', 'lunas'])
            ->select('p.id', 'p.tenor_bulan', DB::raw('COUNT(j.id) as total_jadwal'))
            ->groupBy('p.id', 'p.tenor_bulan')
            ->get()
            ->filter(fn ($row) => (int) $row->total_jadwal !== (int) $row->tenor_bulan)
            ->count();
    }

    private function jadwalTotalMismatch(): int
    {
        if (! $this->hasTables(['pinjaman', 'jadwal_cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->leftJoin('jadwal_cicilan_pinjaman as j', 'j.pinjaman_id', '=', 'p.id')
            ->whereIn('p.status', ['aktif', 'lunas'])
            ->select('p.id', 'p.jumlah_pinjaman', DB::raw('COALESCE(SUM(j.nominal_pokok), 0) as total_jadwal'))
            ->groupBy('p.id', 'p.jumlah_pinjaman')
            ->get()
            ->filter(fn ($row) => number_format((float) $row->jumlah_pinjaman, 2, '.', '') !== number_format((float) $row->total_jadwal, 2, '.', ''))
            ->count();
    }

    private function jadwalDuplicate(string $column): int
    {
        if (! Schema::hasTable('jadwal_cicilan_pinjaman')) {
            return 0;
        }

        return DB::table('jadwal_cicilan_pinjaman')
            ->select('pinjaman_id', $column, DB::raw('COUNT(*) as total'))
            ->groupBy('pinjaman_id', $column)
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function jadwalPertamaSalah(): int
    {
        if (! $this->hasTables(['pinjaman', 'jadwal_cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->join('jadwal_cicilan_pinjaman as j', function ($join): void {
                $join->on('j.pinjaman_id', '=', 'p.id')
                    ->where('j.angsuran_ke', '=', 1);
            })
            ->whereIn('p.status', ['aktif', 'lunas'])
            ->get(['p.tanggal_pinjaman', 'j.periode'])
            ->filter(function ($row): bool {
                $expected = Carbon::parse($row->tanggal_pinjaman, config('app.timezone'))
                    ->startOfMonth()
                    ->addMonth()
                    ->toDateString();

                return Carbon::parse($row->periode)->toDateString() !== $expected;
            })
            ->count();
    }

    private function dompetTanpaCoa(): int
    {
        if (! $this->hasTables(['dompet_koperasi', 'akun']) || ! Schema::hasColumn('dompet_koperasi', 'akun_id')) {
            return 0;
        }

        return DB::table('dompet_koperasi as d')
            ->leftJoin('akun as a', 'a.id', '=', 'd.akun_id')
            ->where(function ($query): void {
                $query->whereNull('d.akun_id')
                    ->orWhereNull('a.id')
                    ->orWhere('a.is_aktif', false)
                    ->orWhere('a.kategori', '!=', 'aset')
                    ->orWhere('a.posisi_saldo', '!=', 'debit');
            })
            ->count('d.id');
    }

    private function bankDefaultPayrollMissing(): int
    {
        if (! $this->hasDompetPayrollColumns()) {
            return 0;
        }

        return DB::table('dompet_koperasi')
            ->where('jenis_dompet', 'bank')
            ->where('is_default_penerimaan_payroll', true)
            ->count() === 0 ? 1 : 0;
    }

    private function bankDefaultPayrollMultiple(): int
    {
        if (! $this->hasDompetPayrollColumns()) {
            return 0;
        }

        $count = DB::table('dompet_koperasi')
            ->where('jenis_dompet', 'bank')
            ->where('is_default_penerimaan_payroll', true)
            ->count();

        return $count > 1 ? $count : 0;
    }

    private function bankDefaultPayrollInvalidCoa(): int
    {
        if (! $this->hasDompetPayrollColumns() || ! $this->hasTables(['akun'])) {
            return 0;
        }

        return DB::table('dompet_koperasi as d')
            ->leftJoin('akun as a', 'a.id', '=', 'd.akun_id')
            ->where('d.is_default_penerimaan_payroll', true)
            ->where(function ($query): void {
                $query->where('d.jenis_dompet', '!=', 'bank')
                    ->orWhereNull('d.akun_id')
                    ->orWhereNull('a.id')
                    ->orWhere('a.is_aktif', false)
                    ->orWhere('a.kategori', '!=', 'aset')
                    ->orWhere('a.posisi_saldo', '!=', 'debit');
            })
            ->count('d.id');
    }

    private function reservasiTanpaLimit(): int
    {
        if (! $this->hasTables(['pemakaian_potong_gaji', 'limit_potong_gaji_anggota'])) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as p')
            ->leftJoin('limit_potong_gaji_anggota as l', 'l.id', '=', 'p.limit_potong_gaji_anggota_id')
            ->where('p.kategori', 'cicilan')
            ->where('p.status', 'reserved')
            ->whereNull('l.id')
            ->count('p.id');
    }

    private function reservasiNominalMismatch(): int
    {
        if (! $this->hasTables(['pemakaian_potong_gaji', 'jadwal_cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as p')
            ->join('jadwal_cicilan_pinjaman as j', 'j.id', '=', 'p.source_id')
            ->where('p.kategori', 'cicilan')
            ->where('p.source_type', 'App\\Models\\JadwalCicilanPinjaman')
            ->where('p.status', 'reserved')
            ->whereRaw('ABS(CAST(p.nominal AS DECIMAL(15,2)) - CAST(COALESCE(j.nominal_sisa, j.nominal_pokok) AS DECIMAL(15,2))) > 0.01')
            ->count('p.id');
    }

    private function cicilanDueTanpaLedgerPadaLimitActive(): int
    {
        if (! $this->hasTables(['limit_potong_gaji_anggota', 'periode_potong_gaji', 'pinjaman', 'jadwal_cicilan_pinjaman', 'siklus_keanggotaan', 'pemakaian_potong_gaji'])
            || ! Schema::hasColumn('pinjaman', 'siklus_keanggotaan_id')) {
            return 0;
        }

        return DB::table('limit_potong_gaji_anggota as l')
            ->join('periode_potong_gaji as pp', 'pp.id', '=', 'l.periode_potong_gaji_id')
            ->join('siklus_keanggotaan as s', function ($join): void {
                $join->on('s.anggota_id', '=', 'l.anggota_id')
                    ->where('s.status', '=', 'active');
            })
            ->join('pinjaman as p', function ($join): void {
                $join->on('p.anggota_id', '=', 'l.anggota_id')
                    ->on('p.siklus_keanggotaan_id', '=', 's.id')
                    ->where('p.status', '=', 'aktif');
            })
            ->join('jadwal_cicilan_pinjaman as j', 'j.pinjaman_id', '=', 'p.id')
            ->where('l.status', 'active')
            ->where('j.status', 'scheduled')
            ->whereColumn('j.periode', '<=', 'pp.periode')
            ->whereRaw('CAST(COALESCE(j.nominal_sisa, j.nominal_pokok) AS DECIMAL(15,2)) > 0')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('pemakaian_potong_gaji as pg')
                    ->whereColumn('pg.source_id', 'j.id')
                    ->where('pg.source_type', 'App\\Models\\JadwalCicilanPinjaman')
                    ->where('pg.kategori', 'cicilan')
                    ->whereIn('pg.status', ['reserved', 'settled']);
            })
            ->count('j.id');
    }

    private function posPayrollSaatCicilanUnreserved(): int
    {
        if (! $this->hasTables(['pemakaian_potong_gaji', 'limit_potong_gaji_anggota', 'periode_potong_gaji', 'pinjaman', 'jadwal_cicilan_pinjaman', 'siklus_keanggotaan'])
            || ! Schema::hasColumn('pinjaman', 'siklus_keanggotaan_id')) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as pos')
            ->join('limit_potong_gaji_anggota as l', 'l.id', '=', 'pos.limit_potong_gaji_anggota_id')
            ->join('periode_potong_gaji as pp', 'pp.id', '=', 'l.periode_potong_gaji_id')
            ->where('pos.kategori', 'pos')
            ->whereIn('pos.status', ['consumed', 'settled'])
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('siklus_keanggotaan as s')
                    ->join('pinjaman as p', function ($join): void {
                        $join->on('p.siklus_keanggotaan_id', '=', 's.id')
                            ->where('p.status', '=', 'aktif');
                    })
                    ->join('jadwal_cicilan_pinjaman as j', 'j.pinjaman_id', '=', 'p.id')
                    ->whereColumn('s.anggota_id', 'l.anggota_id')
                    ->where('s.status', 'active')
                    ->where('j.status', 'scheduled')
                    ->whereColumn('j.periode', '<=', 'pp.periode')
                    ->whereRaw('CAST(COALESCE(j.nominal_sisa, j.nominal_pokok) AS DECIMAL(15,2)) > 0')
                    ->whereNotExists(function ($nested): void {
                        $nested->selectRaw('1')
                            ->from('pemakaian_potong_gaji as pg')
                            ->whereColumn('pg.source_id', 'j.id')
                            ->where('pg.source_type', 'App\\Models\\JadwalCicilanPinjaman')
                            ->where('pg.kategori', 'cicilan')
                            ->whereIn('pg.status', ['reserved', 'settled']);
                    });
            })
            ->count('pos.id');
    }

    private function reservasiGanda(): int
    {
        if (! Schema::hasTable('pemakaian_potong_gaji')) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji')
            ->select('source_type', 'source_id', DB::raw('COUNT(*) as total'))
            ->where('kategori', 'cicilan')
            ->where('source_type', 'App\\Models\\JadwalCicilanPinjaman')
            ->where('status', 'reserved')
            ->groupBy('source_type', 'source_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function jadwalReservedTanpaLedger(): int
    {
        if (! $this->hasTables(['jadwal_cicilan_pinjaman', 'pemakaian_potong_gaji'])) {
            return 0;
        }

        return DB::table('jadwal_cicilan_pinjaman as j')
            ->leftJoin('pemakaian_potong_gaji as p', function ($join): void {
                $join->on('p.source_id', '=', 'j.id')
                    ->where('p.source_type', '=', 'App\\Models\\JadwalCicilanPinjaman')
                    ->where('p.kategori', '=', 'cicilan')
                    ->where('p.status', '=', 'reserved');
            })
            ->where('j.status', 'reserved')
            ->whereNull('p.id')
            ->count('j.id');
    }

    private function ledgerSettledTanpaPembayaran(): int
    {
        if (! $this->hasTables(['pemakaian_potong_gaji', 'cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as p')
            ->leftJoin('cicilan_pinjaman as c', 'c.jadwal_cicilan_pinjaman_id', '=', 'p.source_id')
            ->where('p.kategori', 'cicilan')
            ->where('p.source_type', 'App\\Models\\JadwalCicilanPinjaman')
            ->where('p.status', 'settled')
            ->whereNull('c.id')
            ->count('p.id');
    }

    private function ledgerSettledJadwalBelumPaid(): int
    {
        if (! $this->hasTables(['pemakaian_potong_gaji', 'jadwal_cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as p')
            ->join('jadwal_cicilan_pinjaman as j', 'j.id', '=', 'p.source_id')
            ->where('p.kategori', 'cicilan')
            ->where('p.source_type', 'App\\Models\\JadwalCicilanPinjaman')
            ->where('p.status', 'settled')
            ->where('j.status', '!=', 'paid')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('cicilan_pinjaman as c')
                    ->whereColumn('c.jadwal_cicilan_pinjaman_id', 'j.id')
                    ->where('c.status', 'reversed')
                    ->whereNotNull('c.reversal_transaksi_id');
            })
            ->count('p.id');
    }

    private function pembayaranTanpaJadwal(): int
    {
        if (! $this->hasTables(['cicilan_pinjaman', 'jadwal_cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('cicilan_pinjaman as c')
            ->leftJoin('jadwal_cicilan_pinjaman as j', 'j.id', '=', 'c.jadwal_cicilan_pinjaman_id')
            ->where(function ($query): void {
                $query->whereNull('c.jadwal_cicilan_pinjaman_id')
                    ->orWhereNull('j.id');
            })
            ->count('c.id');
    }

    private function pembayaranGandaJadwal(): int
    {
        if (! Schema::hasTable('cicilan_pinjaman') || ! Schema::hasColumn('cicilan_pinjaman', 'jadwal_cicilan_pinjaman_id')) {
            return 0;
        }

        return DB::table('cicilan_pinjaman')
            ->select('jadwal_cicilan_pinjaman_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('jadwal_cicilan_pinjaman_id')
            ->groupBy('jadwal_cicilan_pinjaman_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function jadwalPaidTanpaPembayaran(): int
    {
        if (! $this->hasTables(['jadwal_cicilan_pinjaman', 'cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('jadwal_cicilan_pinjaman as j')
            ->leftJoin('cicilan_pinjaman as c', 'c.jadwal_cicilan_pinjaman_id', '=', 'j.id')
            ->where('j.status', 'paid')
            ->where(function ($query): void {
                $query->whereNull('j.metode_penyelesaian')
                    ->orWhere('j.metode_penyelesaian', '!=', 'offset_simpanan_pokok');
            })
            ->whereNull('c.id')
            ->count('j.id');
    }

    private function jadwalPaidNominalSisaNonZero(): int
    {
        if (! Schema::hasTable('jadwal_cicilan_pinjaman') || ! Schema::hasColumn('jadwal_cicilan_pinjaman', 'nominal_sisa')) {
            return 0;
        }

        return DB::table('jadwal_cicilan_pinjaman')
            ->where('status', 'paid')
            ->whereRaw('CAST(COALESCE(nominal_sisa, 0) AS DECIMAL(15,2)) > 0.01')
            ->count();
    }

    private function jadwalUnpaidNominalSisaZero(): int
    {
        if (! Schema::hasTable('jadwal_cicilan_pinjaman') || ! Schema::hasColumn('jadwal_cicilan_pinjaman', 'nominal_sisa')) {
            return 0;
        }

        return DB::table('jadwal_cicilan_pinjaman')
            ->whereIn('status', ['scheduled', 'reserved'])
            ->whereRaw('CAST(COALESCE(nominal_sisa, nominal_pokok) AS DECIMAL(15,2)) <= 0')
            ->count();
    }

    private function sisaPinjamanMismatch(): int
    {
        if (! $this->hasTables(['pinjaman', 'jadwal_cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->leftJoin('jadwal_cicilan_pinjaman as j', function ($join): void {
                $join->on('j.pinjaman_id', '=', 'p.id')
                    ->where('j.status', '!=', 'paid');
            })
            ->whereIn('p.status', ['aktif', 'lunas'])
            ->select('p.id', 'p.sisa_pinjaman', DB::raw('COALESCE(SUM(COALESCE(j.nominal_sisa, j.nominal_pokok)), 0) as total_unpaid'))
            ->groupBy('p.id', 'p.sisa_pinjaman')
            ->get()
            ->filter(fn ($row) => number_format((float) $row->sisa_pinjaman, 2, '.', '') !== number_format((float) $row->total_unpaid, 2, '.', ''))
            ->count();
    }

    private function pinjamanLunasJadwalUnpaid(): int
    {
        if (! $this->hasTables(['pinjaman', 'jadwal_cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->join('jadwal_cicilan_pinjaman as j', 'j.pinjaman_id', '=', 'p.id')
            ->where('p.status', 'lunas')
            ->where('j.status', '!=', 'paid')
            ->distinct()
            ->count('p.id');
    }

    private function cicilanPostingIssues(string $table): int
    {
        if (! $this->hasTables(['cicilan_pinjaman', $table])) {
            return 0;
        }

        return DB::table('cicilan_pinjaman as c')
            ->leftJoin("{$table} as r", function ($join): void {
                $join->on('r.referensi_id', '=', 'c.id')
                    ->where('r.referensi_tipe', '=', 'App\\Models\\CicilanPinjaman');
            })
            ->select('c.id', DB::raw('COUNT(r.id) as total_posting'))
            ->groupBy('c.id')
            ->get()
            ->filter(fn ($row) => (int) $row->total_posting !== 1)
            ->count();
    }

    private function payrollDompetSalah(): int
    {
        if (! $this->hasTables(['cicilan_pinjaman', 'pemakaian_potong_gaji', 'limit_potong_gaji_anggota'])) {
            return 0;
        }

        if (! Schema::hasColumn('limit_potong_gaji_anggota', 'dompet_penerimaan_id') || ! Schema::hasColumn('cicilan_pinjaman', 'metode_pembayaran')) {
            return 0;
        }

        return DB::table('cicilan_pinjaman as c')
            ->leftJoin('pemakaian_potong_gaji as p', function ($join): void {
                $join->on('p.source_id', '=', 'c.jadwal_cicilan_pinjaman_id')
                    ->where('p.source_type', '=', 'App\\Models\\JadwalCicilanPinjaman')
                    ->where('p.kategori', '=', 'cicilan')
                    ->where('p.status', '=', 'settled');
            })
            ->leftJoin('limit_potong_gaji_anggota as l', 'l.id', '=', 'p.limit_potong_gaji_anggota_id')
            ->where('c.metode_pembayaran', 'potong_gaji')
            ->where(function ($query): void {
                $query->whereNull('l.id')
                    ->orWhereColumn('c.dompet_id', '!=', 'l.dompet_penerimaan_id');
            })
            ->count('c.id');
    }

    private function pembayaranTunaiKaryawanAktif(): int
    {
        if (! $this->hasTables(['cicilan_pinjaman', 'pinjaman', 'anggota', 'karyawan']) || ! Schema::hasColumn('cicilan_pinjaman', 'metode_pembayaran')) {
            return 0;
        }

        return DB::table('cicilan_pinjaman as c')
            ->join('pinjaman as p', 'p.id', '=', 'c.pinjaman_id')
            ->join('anggota as a', 'a.id', '=', 'p.anggota_id')
            ->join('karyawan as k', 'k.id', '=', 'a.karyawan_id')
            ->where('c.metode_pembayaran', 'tunai')
            ->where('a.status', 'aktif')
            ->where('k.status_kerja', 'aktif')
            ->count('c.id');
    }

    private function pembayaranTunaiDenganLedgerAktif(): int
    {
        if (! $this->hasTables(['cicilan_pinjaman', 'pemakaian_potong_gaji']) || ! Schema::hasColumn('cicilan_pinjaman', 'metode_pembayaran')) {
            return 0;
        }

        return DB::table('cicilan_pinjaman as c')
            ->join('pemakaian_potong_gaji as pg', function ($join): void {
                $join->on('pg.source_id', '=', 'c.jadwal_cicilan_pinjaman_id')
                    ->where('pg.source_type', '=', 'App\\Models\\JadwalCicilanPinjaman')
                    ->where('pg.kategori', '=', 'cicilan')
                    ->whereIn('pg.status', ['reserved', 'consumed']);
            })
            ->where('c.metode_pembayaran', 'tunai')
            ->count('c.id');
    }

    private function limitStatusMasihReserved(string $status): int
    {
        if (! $this->hasTables(['limit_potong_gaji_anggota', 'pemakaian_potong_gaji'])) {
            return 0;
        }

        return DB::table('limit_potong_gaji_anggota as l')
            ->join('pemakaian_potong_gaji as p', 'p.limit_potong_gaji_anggota_id', '=', 'l.id')
            ->where('l.status', $status)
            ->where('p.status', 'reserved')
            ->distinct()
            ->count('l.id');
    }

    private function limitStatusMasihConsumed(string $status): int
    {
        if (! $this->hasTables(['limit_potong_gaji_anggota', 'pemakaian_potong_gaji'])) {
            return 0;
        }

        return DB::table('limit_potong_gaji_anggota as l')
            ->join('pemakaian_potong_gaji as p', 'p.limit_potong_gaji_anggota_id', '=', 'l.id')
            ->where('l.status', $status)
            ->where('p.status', 'consumed')
            ->distinct()
            ->count('l.id');
    }

    private function posAnggotaMetodeInvalid(): int
    {
        if (! $this->hasTables(['penjualan', 'pembayaran', 'anggota', 'karyawan']) || ! Schema::hasColumn('penjualan', 'anggota_id')) {
            return 0;
        }

        return DB::table('penjualan as p')
            ->join('anggota as a', 'a.id', '=', 'p.anggota_id')
            ->join('karyawan as k', 'k.id', '=', 'a.karyawan_id')
            ->leftJoin('pembayaran as pb', 'pb.penjualan_id', '=', 'p.id')
            ->where('a.status', 'aktif')
            ->where('k.status_kerja', 'aktif')
            ->where(function ($query): void {
                $query->whereNull('pb.id')
                    ->orWhereNotIn('pb.metode_pembayaran', ['tunai', 'potong_gaji']);
            })
            ->count('p.id');
    }

    private function posPayrollTanpaLedger(): int
    {
        if (! $this->hasTables(['penjualan', 'pembayaran', 'pemakaian_potong_gaji'])) {
            return 0;
        }

        if (! Schema::hasColumn('pembayaran', 'pemakaian_potong_gaji_id')) {
            return 0;
        }

        return DB::table('pembayaran as pb')
            ->join('penjualan as p', 'p.id', '=', 'pb.penjualan_id')
            ->leftJoin('pemakaian_potong_gaji as pg', 'pg.id', '=', 'pb.pemakaian_potong_gaji_id')
            ->where('pb.metode_pembayaran', 'potong_gaji')
            ->where(function ($query): void {
                $query->whereNull('pb.pemakaian_potong_gaji_id')
                    ->orWhereNull('pg.id')
                    ->orWhere('pg.kategori', '!=', 'pos')
                    ->orWhere('pg.source_type', '!=', 'App\\Models\\Penjualan')
                    ->orWhereColumn('pg.source_id', '!=', 'p.id');
            })
            ->count('pb.id');
    }

    private function posLedgerNominalMismatch(): int
    {
        if (! $this->hasTables(['penjualan', 'pemakaian_potong_gaji'])) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as pg')
            ->join('penjualan as p', 'p.id', '=', 'pg.source_id')
            ->where('pg.kategori', 'pos')
            ->where('pg.source_type', 'App\\Models\\Penjualan')
            ->whereColumn('pg.nominal', '!=', 'p.grand_total')
            ->count('pg.id');
    }

    private function posPayrollSettlementMismatch(): int
    {
        if (! $this->hasTables(['pembayaran', 'pemakaian_potong_gaji'])) {
            return 0;
        }

        if (! Schema::hasColumn('pembayaran', 'pemakaian_potong_gaji_id') || ! Schema::hasColumn('pembayaran', 'status')) {
            return 0;
        }

        return DB::table('pembayaran as pb')
            ->join('pemakaian_potong_gaji as pg', 'pg.id', '=', 'pb.pemakaian_potong_gaji_id')
            ->where('pb.metode_pembayaran', 'potong_gaji')
            ->where(function ($query): void {
                $query->where(function ($subQuery): void {
                    $subQuery->where('pg.status', 'settled')
                        ->whereNotIn('pb.status', ['paid', 'refunded']);
                })->orWhere(function ($subQuery): void {
                    $subQuery->where('pg.status', 'consumed')
                        ->where('pb.status', '!=', 'pending_payroll');
                })->orWhere(function ($subQuery): void {
                    $subQuery->where('pg.status', 'released')
                        ->whereNotIn('pb.status', ['outstanding_cash', 'settled_cash']);
                });
            })
            ->count('pb.id');
    }

    private function simpananPokokGanda(): int
    {
        if (! Schema::hasTable('simpanan') || ! Schema::hasColumn('simpanan', 'kode_jenis_snapshot') || ! Schema::hasColumn('simpanan', 'anggota_id')) {
            return 0;
        }

        $groupColumn = Schema::hasColumn('simpanan', 'siklus_keanggotaan_id')
            ? 'siklus_keanggotaan_id'
            : 'anggota_id';

        return DB::table('simpanan')
            ->select($groupColumn, DB::raw('COUNT(*) as total'))
            ->where('kode_jenis_snapshot', 'SIMPANAN_POKOK')
            ->whereNotIn('status', ['reversed', 'reversed_due_to_exit'])
            ->whereNotNull($groupColumn)
            ->groupBy($groupColumn)
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function simpananPokokManual(): int
    {
        if (! Schema::hasTable('simpanan') || ! Schema::hasColumn('simpanan', 'kode_jenis_snapshot')) {
            return 0;
        }

        return DB::table('simpanan')
            ->where('kode_jenis_snapshot', 'SIMPANAN_POKOK')
            ->where(function ($query): void {
                if (Schema::hasColumn('simpanan', 'metode_pembayaran')) {
                    $query->where('metode_pembayaran', '!=', 'potong_gaji')
                        ->orWhereNull('metode_pembayaran');
                }

                if (Schema::hasColumn('simpanan', 'idempotency_key')) {
                    $query->orWhere(function ($subQuery): void {
                        $subQuery->where('idempotency_key', 'not like', 'simpanan-pokok:anggota:%')
                            ->where('idempotency_key', 'not like', 'simpanan-pokok:siklus:%');
                    });
                }

                if (Schema::hasColumn('simpanan', 'siklus_keanggotaan_id')) {
                    $query->orWhereNull('siklus_keanggotaan_id');
                }
            })
            ->count();
    }

    private function simpananPokokLedgerMismatch(): int
    {
        if (! $this->hasTables(['simpanan', 'pemakaian_potong_gaji']) || ! Schema::hasColumn('simpanan', 'pemakaian_potong_gaji_id')) {
            return 0;
        }

        return DB::table('simpanan as s')
            ->leftJoin('pemakaian_potong_gaji as pg', 'pg.id', '=', 's.pemakaian_potong_gaji_id')
            ->where('s.kode_jenis_snapshot', 'SIMPANAN_POKOK')
            ->where(function ($query): void {
                $query->where(function ($subQuery): void {
                    $subQuery->whereIn('s.status', ['allocated', 'settled'])
                        ->where(function ($ledgerQuery): void {
                            $ledgerQuery->whereNull('pg.id')
                                ->orWhere('pg.kategori', '!=', 'simpanan_pokok')
                                ->orWhere('pg.source_type', '!=', 'App\\Models\\Simpanan')
                                ->orWhereColumn('pg.source_id', '!=', 's.id')
                                ->orWhereColumn('pg.nominal', '!=', 's.nominal_snapshot');
                        });
                })->orWhere(function ($subQuery): void {
                    $subQuery->where('s.status', 'pending_payroll')
                        ->whereNotNull('s.pemakaian_potong_gaji_id');
                });
            })
            ->count('s.id');
    }

    private function jenisSimpananAktifGanda(): int
    {
        if (! Schema::hasTable('jenis_simpanan') || ! Schema::hasColumn('jenis_simpanan', 'kategori')) {
            return 0;
        }

        return DB::table('jenis_simpanan')
            ->select('kategori', DB::raw('COUNT(*) as total'))
            ->where('aktif', true)
            ->whereIn('kategori', $this->jenisSimpananKategoriResmi())
            ->groupBy('kategori')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function jenisSimpananKategoriInvalid(): int
    {
        if (! Schema::hasTable('jenis_simpanan') || ! Schema::hasColumn('jenis_simpanan', 'kategori')) {
            return 0;
        }

        return DB::table('jenis_simpanan')
            ->where(function ($query): void {
                $query->whereNull('kategori')
                    ->orWhereNotIn('kategori', $this->jenisSimpananKategoriResmi());
            })
            ->count();
    }

    private function jenisSimpananKodeMismatch(): int
    {
        if (! Schema::hasTable('jenis_simpanan') || ! Schema::hasColumn('jenis_simpanan', 'kategori') || ! Schema::hasColumn('jenis_simpanan', 'kode')) {
            return 0;
        }

        return DB::table('jenis_simpanan')
            ->whereIn('kategori', $this->jenisSimpananKategoriResmi())
            ->where(function ($query): void {
                $query->where(function ($subQuery): void {
                    $subQuery->where('kategori', JenisSimpanan::KATEGORI_POKOK)->where('kode', '!=', JenisSimpanan::KODE_SIMPANAN_POKOK);
                })->orWhere(function ($subQuery): void {
                    $subQuery->where('kategori', JenisSimpanan::KATEGORI_WAJIB)->where('kode', '!=', JenisSimpanan::KODE_SIMPANAN_WAJIB);
                })->orWhere(function ($subQuery): void {
                    $subQuery->where('kategori', JenisSimpanan::KATEGORI_MANASUKA)->where('kode', '!=', JenisSimpanan::KODE_SIMPANAN_MANASUKA);
                })->orWhereNull('kode');
            })
            ->count();
    }

    private function jenisSimpananIntervalInvalid(): int
    {
        if (! Schema::hasTable('jenis_simpanan') || ! Schema::hasColumn('jenis_simpanan', 'interval_bulan')) {
            return 0;
        }

        return DB::table('jenis_simpanan')
            ->where('kategori', JenisSimpanan::KATEGORI_WAJIB)
            ->where('aktif', true)
            ->whereNotNull('interval_bulan')
            ->count();
    }

    private function jenisSimpananIntervalTerlarang(): int
    {
        if (! Schema::hasTable('jenis_simpanan') || ! Schema::hasColumn('jenis_simpanan', 'interval_bulan')) {
            return 0;
        }

        return DB::table('jenis_simpanan')
            ->whereIn('kategori', [JenisSimpanan::KATEGORI_POKOK, JenisSimpanan::KATEGORI_MANASUKA])
            ->whereNotNull('interval_bulan')
            ->count();
    }

    private function jenisSimpananNominalInvalid(): int
    {
        if (! Schema::hasTable('jenis_simpanan')) {
            return 0;
        }

        $legacyInvalid = DB::table('jenis_simpanan')
            ->where('kategori', JenisSimpanan::KATEGORI_POKOK)
            ->where(function ($query): void {
                $query->whereNull('nominal_default')
                    ->orWhere('nominal_default', '<=', 0);
            })
            ->count();

        $finalWajibInvalid = DB::table('jenis_simpanan')
            ->where('kategori', JenisSimpanan::KATEGORI_WAJIB)
            ->where('aktif', true)
            ->where(function ($query): void {
                $query->whereNull('nominal_default')
                    ->orWhereRaw('ABS(nominal_default - 10000) > 0.01');
            })
            ->count();

        return $legacyInvalid + $finalWajibInvalid;
    }

    private function jenisSimpananCoaInvalid(): int
    {
        if (! $this->hasTables(['jenis_simpanan', 'akun']) || ! Schema::hasColumn('jenis_simpanan', 'akun_id')) {
            return 0;
        }

        return DB::table('jenis_simpanan as js')
            ->leftJoin('akun as a', 'a.id', '=', 'js.akun_id')
            ->where('js.aktif', true)
            ->whereIn('js.kategori', $this->jenisSimpananKategoriResmi())
            ->where(function ($query): void {
                $query->whereNull('a.id')
                    ->orWhere('a.is_aktif', false)
                    ->orWhere('a.posisi_saldo', '!=', 'kredit')
                    ->orWhere(function ($subQuery): void {
                        $subQuery->whereIn('js.kategori', [JenisSimpanan::KATEGORI_POKOK, JenisSimpanan::KATEGORI_WAJIB])
                            ->where('a.kategori', '!=', 'ekuitas');
                    })
                    ->orWhere(function ($subQuery): void {
                        $subQuery->where('js.kategori', JenisSimpanan::KATEGORI_MANASUKA)
                            ->where('a.kategori', '!=', 'kewajiban');
                    });
            })
            ->count('js.id');
    }

    private function jenisSimpananKategoriResmi(): array
    {
        return [
            JenisSimpanan::KATEGORI_POKOK,
            JenisSimpanan::KATEGORI_WAJIB,
            JenisSimpanan::KATEGORI_MANASUKA,
        ];
    }

    private function simpananReferenceInvalid(): int
    {
        if (! $this->hasTables(['simpanan', 'jenis_simpanan', 'anggota'])) {
            return 0;
        }

        $query = DB::table('simpanan as s')
            ->leftJoin('jenis_simpanan as js', 'js.id', '=', 's.jenis_simpanan_id')
            ->leftJoin('anggota as a', 'a.id', '=', 's.anggota_id')
            ->where(function ($query): void {
                $query->whereNull('js.id')
                    ->orWhereNull('s.anggota_id')
                    ->orWhereNull('a.id');
            });

        $invalid = $query->count('s.id');

        if ($this->hasTables(['mutasi_kas', 'dompet_koperasi'])) {
            $invalid += DB::table('simpanan as s')
                ->leftJoin('mutasi_kas as mk', function ($join): void {
                    $join->on('mk.referensi_id', '=', 's.id')
                        ->where('mk.referensi_tipe', '=', 'App\\Models\\Simpanan');
                })
                ->leftJoin('dompet_koperasi as d', 'd.id', '=', 'mk.dompet_id')
                ->where('s.metode_pembayaran', 'tunai')
                ->where('s.status', 'settled')
                ->where(function ($query): void {
                    $query->whereNull('mk.id')
                        ->orWhereNull('d.id');
                })
                ->count('s.id');
        }

        return $invalid;
    }

    private function simpananPostingDompetMismatch(): int
    {
        if (! $this->hasTables(['simpanan', 'mutasi_kas', 'dompet_koperasi', 'akun', 'jurnal_umum', 'jurnal_umum_detail'])) {
            return 0;
        }

        $query = DB::table('simpanan as s')
            ->join('mutasi_kas as mk', function ($join): void {
                $join->on('mk.referensi_id', '=', 's.id')
                    ->where('mk.referensi_tipe', '=', 'App\\Models\\Simpanan');
            })
            ->join('dompet_koperasi as d', 'd.id', '=', 'mk.dompet_id')
            ->leftJoin('jurnal_umum as ju', function ($join): void {
                $join->on('ju.referensi_id', '=', 's.id')
                    ->where('ju.referensi_tipe', '=', 'App\\Models\\Simpanan');
            })
            ->leftJoin('jurnal_umum_detail as debit', function ($join): void {
                $join->on('debit.jurnal_umum_id', '=', 'ju.id')
                    ->where('debit.debit', '>', 0);
            })
            ->leftJoin('jurnal_umum_detail as credit', function ($join): void {
                $join->on('credit.jurnal_umum_id', '=', 'ju.id')
                    ->where('credit.kredit', '>', 0);
            })
            ->whereIn('s.metode_pembayaran', ['tunai', 'transfer_bank'])
            ->where('s.status', 'settled');

        if (Schema::hasColumn('simpanan', 'jenis_transaksi')) {
            $query->where(function ($query): void {
                $query->where(function ($setoran): void {
                    $setoran->where(function ($jenis): void {
                        $jenis->whereNull('s.jenis_transaksi')
                            ->orWhere('s.jenis_transaksi', 'setoran');
                    })->where(function ($invalid): void {
                        $invalid->whereNull('ju.id')
                            ->orWhereNull('debit.id')
                            ->orWhere('mk.tipe', '!=', 'masuk')
                            ->orWhereColumn('debit.akun_id', '!=', 'd.akun_id')
                            ->orWhereColumn('debit.debit', '!=', 'mk.jumlah');
                    });
                })->orWhere(function ($penarikan): void {
                    $penarikan->where('s.jenis_transaksi', 'penarikan')
                        ->where(function ($invalid): void {
                            $invalid->whereNull('ju.id')
                                ->orWhereNull('credit.id')
                                ->orWhere('mk.tipe', '!=', 'keluar')
                                ->orWhereColumn('credit.akun_id', '!=', 'd.akun_id')
                                ->orWhereColumn('credit.kredit', '!=', 'mk.jumlah');
                        });
                });
            });
        } else {
            $query->where('s.metode_pembayaran', 'tunai')
                ->where(function ($query): void {
                $query->whereNull('ju.id')
                    ->orWhereNull('debit.id')
                    ->orWhereColumn('debit.akun_id', '!=', 'd.akun_id')
                    ->orWhereColumn('debit.debit', '!=', 'mk.jumlah');
                });
        }

        return $query->count('s.id');
    }

    private function duplicateIdempotency(array $tables): int
    {
        $total = 0;

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'idempotency_key')) {
                continue;
            }

            $total += DB::table($table)
                ->select('idempotency_key', DB::raw('COUNT(*) as total'))
                ->whereNotNull('idempotency_key')
                ->groupBy('idempotency_key')
                ->having('total', '>', 1)
                ->get()
                ->count();
        }

        return $total;
    }

    private function simpananPokokSnapshotHilang(): int
    {
        if (! Schema::hasTable('simpanan') || ! Schema::hasColumn('simpanan', 'kode_jenis_snapshot')) {
            return 0;
        }

        return DB::table('simpanan')
            ->where('kode_jenis_snapshot', 'SIMPANAN_POKOK')
            ->where(function ($query): void {
                $query->whereNull('nama_jenis_snapshot')
                    ->orWhereNull('nominal_snapshot')
                    ->orWhere('nominal_snapshot', '<=', 0);
            })
            ->count();
    }

    private function pinjamanPostingIssues(string $table): int
    {
        if (! $this->hasTables(['pinjaman', $table])) {
            return 0;
        }

        return DB::table('pinjaman as p')
            ->leftJoin("{$table} as r", function ($join): void {
                $join->on('r.referensi_id', '=', 'p.id')
                    ->where('r.referensi_tipe', '=', 'App\\Models\\Pinjaman');
            })
            ->whereIn('p.status', ['aktif', 'lunas'])
            ->select('p.id', DB::raw('COUNT(r.id) as total_posting'))
            ->groupBy('p.id')
            ->get()
            ->filter(fn ($row) => (int) $row->total_posting !== 1)
            ->count();
    }

    private function transaksiSetelahNonaktif(): int
    {
        return $this->inactiveTransactionCount('penjualan', 'created_at')
            + $this->inactiveTransactionCount('simpanan', 'tanggal')
            + $this->inactiveTransactionCount('pinjaman', 'tanggal_pinjaman')
            + $this->inactiveCicilanCount();
    }

    private function inactiveTransactionCount(string $table, string $dateColumn): int
    {
        if (! $this->hasTables([$table, 'karyawan', 'anggota'])) {
            return 0;
        }

        $query = DB::table("{$table} as t")
            ->join('karyawan as k', 'k.id', '=', 't.karyawan_id')
            ->select([
                't.id',
                "t.{$dateColumn} as tanggal_transaksi",
                'k.status_kerja',
                'k.tanggal_berhenti',
            ]);

        if (Schema::hasColumn($table, 'anggota_id')) {
            $query->leftJoin('anggota as a', 'a.id', '=', 't.anggota_id');
        } else {
            $query->leftJoin('anggota as a', 'a.karyawan_id', '=', 't.karyawan_id');
        }

        return $query
            ->addSelect(['a.status as status_anggota', 'a.tanggal_nonaktif'])
            ->get()
            ->filter(fn ($row) => $this->isAfterInactiveDate($row))
            ->count();
    }

    private function inactiveCicilanCount(): int
    {
        if (! $this->hasTables(['cicilan_pinjaman', 'pinjaman', 'karyawan', 'anggota'])) {
            return 0;
        }

        $query = DB::table('cicilan_pinjaman as c')
            ->join('pinjaman as p', 'p.id', '=', 'c.pinjaman_id')
            ->join('karyawan as k', 'k.id', '=', 'p.karyawan_id')
            ->select([
                'c.id',
                DB::raw('COALESCE(c.tanggal_bayar, c.created_at) as tanggal_transaksi'),
                'k.status_kerja',
                'k.tanggal_berhenti',
            ]);

        if (Schema::hasColumn('pinjaman', 'anggota_id')) {
            $query->leftJoin('anggota as a', 'a.id', '=', 'p.anggota_id');
        } else {
            $query->leftJoin('anggota as a', 'a.karyawan_id', '=', 'p.karyawan_id');
        }

        return $query
            ->addSelect(['a.status as status_anggota', 'a.tanggal_nonaktif'])
            ->get()
            ->filter(fn ($row) => $this->isAfterInactiveDate($row))
            ->count();
    }

    private function isAfterInactiveDate(object $row): bool
    {
        $transactionDate = $this->dateOnly($row->tanggal_transaksi ?? null);

        if ($transactionDate === null) {
            return false;
        }

        if (($row->status_kerja ?? 'aktif') !== 'aktif') {
            $stoppedAt = $this->dateOnly($row->tanggal_berhenti ?? null);

            if ($stoppedAt === null || $transactionDate->gt($stoppedAt)) {
                return true;
            }
        }

        if (($row->status_anggota ?? 'aktif') !== 'aktif') {
            $inactiveAt = $this->dateOnly($row->tanggal_nonaktif ?? null);

            if ($inactiveAt === null || $transactionDate->gt($inactiveAt)) {
                return true;
            }
        }

        return false;
    }

    private function dateOnly(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->startOfDay();
    }

    private function sourceReversedTanpaReversal(): int
    {
        $total = 0;

        if (Schema::hasColumn('penjualan', 'reversal_transaksi_id')) {
            $total += DB::table('penjualan')
                ->whereIn('status', ['cancelled', 'reversed', 'refunded'])
                ->whereNull('reversal_transaksi_id')
                ->count();
        }

        if (Schema::hasColumn('simpanan', 'reversal_transaksi_id')) {
            $total += DB::table('simpanan')
                ->where('status', 'reversed')
                ->whereNull('reversal_transaksi_id')
                ->count();
        }

        if (Schema::hasColumn('cicilan_pinjaman', 'reversal_transaksi_id')) {
            $total += DB::table('cicilan_pinjaman')
                ->where('status', 'reversed')
                ->whereNull('reversal_transaksi_id')
                ->count();
        }

        if (Schema::hasColumn('pemakaian_potong_gaji', 'reversal_transaksi_id')) {
            $total += DB::table('pemakaian_potong_gaji')
                ->where('status', 'reversed')
                ->whereNull('reversal_transaksi_id')
                ->whereNull('reversal_of_id')
                ->count();
        }

        return $total;
    }

    private function reversalTanpaSource(): int
    {
        if (! Schema::hasTable('reversal_transaksi')) {
            return 0;
        }

        return DB::table('reversal_transaksi')
            ->get(['source_type', 'source_id'])
            ->filter(fn ($row) => ! $this->referenceExists((string) $row->source_type, (int) $row->source_id))
            ->count();
    }

    private function reversalGanda(): int
    {
        if (! Schema::hasTable('reversal_transaksi')) {
            return 0;
        }

        return DB::table('reversal_transaksi')
            ->select('source_type', 'source_id', DB::raw('COUNT(*) as total'))
            ->where('status', '!=', 'cancelled')
            ->groupBy('source_type', 'source_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function nominalReversalMismatch(): int
    {
        if (! Schema::hasTable('reversal_transaksi')) {
            return 0;
        }

        return DB::table('reversal_transaksi')
            ->get()
            ->filter(function ($row): bool {
                $sourceNominal = match ((string) $row->source_type) {
                    'App\\Models\\Penjualan' => DB::table('penjualan')->where('id', $row->source_id)->value('grand_total'),
                    'App\\Models\\Simpanan' => DB::table('simpanan')->where('id', $row->source_id)->value('jumlah'),
                    'App\\Models\\CicilanPinjaman' => DB::table('cicilan_pinjaman')->where('id', $row->source_id)->value('jumlah_cicilan'),
                    default => null,
                };

                return $sourceNominal !== null && abs((float) $sourceNominal - (float) $row->nominal) > 0.01;
            })
            ->count();
    }

    private function ledgerReversedPaymentPaid(): int
    {
        if (! $this->hasTables(['pemakaian_potong_gaji', 'pembayaran'])) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji as pg')
            ->join('pembayaran as p', 'p.pemakaian_potong_gaji_id', '=', 'pg.id')
            ->where('pg.status', 'reversed')
            ->where('p.status', 'paid')
            ->count();
    }

    private function kreditTanpaReversal(): int
    {
        if (! $this->hasTables(['kredit_potong_gaji_anggota', 'reversal_transaksi'])) {
            return 0;
        }

        return DB::table('kredit_potong_gaji_anggota as k')
            ->leftJoin('reversal_transaksi as r', 'r.id', '=', 'k.reversal_transaksi_id')
            ->whereNull('r.id')
            ->count();
    }

    private function kreditNominalMismatch(): int
    {
        if (! Schema::hasTable('kredit_potong_gaji_anggota')) {
            return 0;
        }

        return DB::table('kredit_potong_gaji_anggota')
            ->where(function ($query): void {
                $query->whereRaw('nominal_terpakai > nominal_awal')
                    ->orWhereRaw('ABS((nominal_awal - nominal_terpakai) - nominal_sisa) > 0.01')
                    ->orWhere('nominal_sisa', '<', 0);
            })
            ->count();
    }

    private function kreditAnggotaLain(): int
    {
        if (! $this->hasTables(['kredit_potong_gaji_anggota', 'alokasi_kredit_potong_gaji', 'limit_potong_gaji_anggota'])) {
            return 0;
        }

        return DB::table('alokasi_kredit_potong_gaji as a')
            ->join('kredit_potong_gaji_anggota as k', 'k.id', '=', 'a.kredit_potong_gaji_anggota_id')
            ->join('limit_potong_gaji_anggota as l', 'l.id', '=', 'a.limit_potong_gaji_anggota_id')
            ->whereColumn('k.anggota_id', '!=', 'l.anggota_id')
            ->count();
    }

    private function netPayrollNegatif(): int
    {
        if (! $this->hasTables(['limit_potong_gaji_anggota', 'pemakaian_potong_gaji', 'alokasi_kredit_potong_gaji'])) {
            return 0;
        }

        $gross = DB::table('pemakaian_potong_gaji')
            ->select('limit_potong_gaji_anggota_id', DB::raw('SUM(nominal) as gross'))
            ->whereIn('status', ['reserved', 'consumed', 'settled'])
            ->groupBy('limit_potong_gaji_anggota_id');

        $credit = DB::table('alokasi_kredit_potong_gaji')
            ->select('limit_potong_gaji_anggota_id', DB::raw('SUM(nominal_diterapkan) as credit'))
            ->where('status', 'applied')
            ->groupBy('limit_potong_gaji_anggota_id');

        return DB::query()
            ->fromSub($gross, 'g')
            ->leftJoinSub($credit, 'c', 'c.limit_potong_gaji_anggota_id', '=', 'g.limit_potong_gaji_anggota_id')
            ->whereRaw('COALESCE(c.credit, 0) > g.gross')
            ->count();
    }

    private function outstandingSettledTanpaPembayaran(): int
    {
        if (! Schema::hasTable('pembayaran_outstanding_cash')) {
            return 0;
        }

        $pos = Schema::hasTable('pembayaran')
            ? DB::table('pembayaran as p')
                ->leftJoin('pembayaran_outstanding_cash as oc', function ($join): void {
                    $join->on('oc.source_id', '=', 'p.id')
                        ->where('oc.source_type', '=', 'App\\Models\\Pembayaran');
                })
                ->where('p.status', 'settled_cash')
                ->whereNull('oc.id')
                ->count()
            : 0;

        $simpanan = Schema::hasTable('simpanan')
            ? DB::table('simpanan as s')
                ->leftJoin('pembayaran_outstanding_cash as oc', function ($join): void {
                    $join->on('oc.source_id', '=', 's.id')
                        ->where('oc.source_type', '=', 'App\\Models\\Simpanan');
                })
                ->where('s.status', 'settled_cash')
                ->whereNull('oc.id')
                ->count()
            : 0;

        return $pos + $simpanan;
    }

    private function pembayaranOutstandingOrphan(): int
    {
        if (! Schema::hasTable('pembayaran_outstanding_cash')) {
            return 0;
        }

        return DB::table('pembayaran_outstanding_cash')
            ->get(['source_type', 'source_id'])
            ->filter(fn ($row) => ! $this->referenceExists((string) $row->source_type, (int) $row->source_id))
            ->count();
    }

    private function cicilanReversedJadwalPaid(): int
    {
        if (! $this->hasTables(['cicilan_pinjaman', 'jadwal_cicilan_pinjaman'])) {
            return 0;
        }

        return DB::table('cicilan_pinjaman as c')
            ->join('jadwal_cicilan_pinjaman as j', 'j.id', '=', 'c.jadwal_cicilan_pinjaman_id')
            ->where('c.status', 'reversed')
            ->where('j.status', 'paid')
            ->count();
    }

    private function mutasiRefundGanda(): int
    {
        if (! Schema::hasTable('mutasi_kas')) {
            return 0;
        }

        return DB::table('mutasi_kas')
            ->select('referensi_tipe', 'referensi_id', DB::raw('COUNT(*) as total'))
            ->where('referensi_tipe', 'App\\Models\\ReversalTransaksi')
            ->where('tipe', 'keluar')
            ->groupBy('referensi_tipe', 'referensi_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function jurnalReversalTidakSeimbang(): int
    {
        if (! $this->hasTables(['jurnal_umum', 'jurnal_umum_detail'])) {
            return 0;
        }

        return DB::table('jurnal_umum as j')
            ->select('j.id')
            ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
            ->where('j.referensi_tipe', 'App\\Models\\ReversalTransaksi')
            ->groupBy('j.id')
            ->havingRaw('ABS(SUM(d.debit) - SUM(d.kredit)) > 0.01')
            ->get()
            ->count();
    }

    private function hardcodedLimitTwoMillion(): int
    {
        $roots = [
            app_path(),
            config_path(),
            resource_path(),
            base_path('routes'),
        ];
        $count = 0;

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                $path = $file->getPathname();

                if (! $file->isFile() || str_ends_with($path, 'PreflightPotongGajiCommand.php')) {
                    continue;
                }

                if (! in_array($file->getExtension(), ['php', 'blade', 'js', 'ts', 'vue'], true)) {
                    continue;
                }

                $contents = @file_get_contents($path);
                if ($contents !== false && preg_match('/(?<!\d)2000000(?!\d)|2\.000\.000|2,000,000/', $contents)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function routeHardDeleteEditTransaksi(): int
    {
        $forbidden = [
            'penjualan.edit',
            'penjualan.destroy',
            'simpanan.edit',
            'simpanan.destroy',
            'pinjaman.destroy',
            'cicilan-pinjaman.edit',
            'cicilan-pinjaman.destroy',
            'mutasi-kas.destroy',
        ];

        return collect($forbidden)->filter(fn (string $name) => Route::has($name))->count();
    }

    private function reportAccountMapMissing(): int
    {
        $requiredAccounts = ['bank'];
        $requiredPostings = [
            'refund.piutang_potong_gaji',
            'refund.piutang_pinjaman',
        ];

        $missing = 0;

        foreach ($requiredAccounts as $key) {
            $definition = config("account_map.accounts.{$key}");
            if (! is_array($definition) || empty($definition['kode_akun'])) {
                $missing++;
            }
        }

        foreach ($requiredPostings as $path) {
            $accountKey = config("account_map.postings.{$path}");
            $definition = is_string($accountKey) ? config("account_map.accounts.{$accountKey}") : null;

            if (! is_string($accountKey) || ! is_array($definition) || empty($definition['kode_akun'])) {
                $missing++;
            }
        }

        return $missing;
    }

    private function payrollReconciliationMismatch(): int
    {
        if (! $this->hasTables([
            'periode_potong_gaji',
            'limit_potong_gaji_anggota',
            'pemakaian_potong_gaji',
            'mutasi_kas',
            'jurnal_umum',
            'jurnal_umum_detail',
        ])) {
            return 0;
        }

        try {
            $periode = now(config('app.timezone'))->format('Y-m');
            $report = app(PotongGajiReportService::class)->reconciliation($periode);

            return collect($report['differences'] ?? [])
                ->filter(fn ($diff) => abs((float) $diff) >= 0.01)
                ->count();
        } catch (\Throwable) {
            return 1;
        }
    }

    private function referenceIssues(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $nullMismatch = DB::table($table)
            ->where(function ($query): void {
                $query->whereNull('referensi_tipe')->whereNotNull('referensi_id');
            })
            ->orWhere(function ($query): void {
                $query->whereNotNull('referensi_tipe')->whereNull('referensi_id');
            })
            ->count();

        $duplicates = DB::table($table)
            ->select('referensi_tipe', 'referensi_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('referensi_tipe')
            ->whereNotNull('referensi_id')
            ->whereNotIn('referensi_tipe', $this->referenceTypesWithMultipleValidPostings())
            ->groupBy('referensi_tipe', 'referensi_id')
            ->having('total', '>', 1)
            ->get()
            ->count();

        $orphans = DB::table($table)
            ->whereNotNull('referensi_tipe')
            ->whereNotNull('referensi_id')
            ->get(['referensi_tipe', 'referensi_id'])
            ->filter(fn ($row) => ! $this->referenceExists((string) $row->referensi_tipe, (int) $row->referensi_id))
            ->count();

        return $nullMismatch + $duplicates + $orphans;
    }

    /**
     * Sebagian sumber non-payroll memiliki beberapa posting sah untuk lifecycle berbeda.
     * Orphan tetap dicek lewat referenceExists(), tetapi duplikasi referensi tidak otomatis
     * berarti double-posting untuk sumber-sumber ini.
     *
     * @return array<int, class-string<Model>>
     */
    private function referenceTypesWithMultipleValidPostings(): array
    {
        return [
            PembayaranSewaMobil::class,
            SewaMobil::class,
            PembayaranSewaHardware::class,
            SewaHardware::class,
            ReversalTransaksi::class,
            Simpanan::class,
        ];
    }

    private function referenceExists(string $type, int $id): bool
    {
        if (! class_exists($type) || ! is_subclass_of($type, Model::class)) {
            return false;
        }

        /** @var Model $model */
        $model = new $type();

        if (! Schema::hasTable($model->getTable())) {
            return false;
        }

        return $type::query()->whereKey($id)->exists();
    }

    private function kebijakanLimitUmumMissing(): int
    {
        if (! Schema::hasTable('kebijakan_limit_potong_gaji')) {
            return 1;
        }

        return DB::table('kebijakan_limit_potong_gaji')
            ->where('status', 'active')
            ->count() === 0 ? 1 : 0;
    }

    private function kebijakanLimitUmumMultiple(): int
    {
        if (! Schema::hasTable('kebijakan_limit_potong_gaji')) {
            return 0;
        }

        return max(0, DB::table('kebijakan_limit_potong_gaji')
            ->where('status', 'active')
            ->whereNull('berlaku_sampai_periode')
            ->count() - 1);
    }

    private function anggotaAktifTanpaLimitPeriodeTerbuka(): int
    {
        if (! $this->hasTables(['anggota', 'karyawan', 'periode_potong_gaji', 'limit_potong_gaji_anggota'])) {
            return 0;
        }

        return DB::table('periode_potong_gaji as p')
            ->join('anggota as a', function ($join): void {
                $join->where('a.status', 'aktif');
            })
            ->join('karyawan as k', function ($join): void {
                $join->on('k.id', '=', 'a.karyawan_id')
                    ->where('k.status_kerja', 'aktif');
            })
            ->leftJoin('limit_potong_gaji_anggota as l', function ($join): void {
                $join->on('l.periode_potong_gaji_id', '=', 'p.id')
                    ->on('l.anggota_id', '=', 'a.id');
            })
            ->whereIn('p.status', ['draft', 'active'])
            ->whereNull('l.id')
            ->count();
    }

    private function duplicateLimitAnggotaPeriode(): int
    {
        if (! Schema::hasTable('limit_potong_gaji_anggota')) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table('limit_potong_gaji_anggota')
                    ->select('periode_potong_gaji_id', 'anggota_id', DB::raw('COUNT(*) as total'))
                    ->groupBy('periode_potong_gaji_id', 'anggota_id')
                    ->havingRaw('COUNT(*) > 1'),
                'dupes'
            )
            ->count();
    }

    private function karyawanAktifTanpaPerusahaan(): int
    {
        if (! Schema::hasTable('karyawan') || ! Schema::hasColumn('karyawan', 'perusahaan_id')) {
            return 0;
        }

        return DB::table('karyawan')
            ->where('status_kerja', 'aktif')
            ->whereNull('perusahaan_id')
            ->count();
    }

    private function overrideLimitAktifGanda(): int
    {
        if (! Schema::hasTable('override_limit_potong_gaji_anggota')) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table('override_limit_potong_gaji_anggota')
                    ->select('anggota_id', DB::raw('COUNT(*) as total'))
                    ->where('status', 'active')
                    ->groupBy('anggota_id')
                    ->havingRaw('COUNT(*) > 1'),
                'dupes'
            )
            ->count();
    }

    private function limitKhususTanpaAudit(): int
    {
        if (! Schema::hasTable('override_limit_potong_gaji_anggota')) {
            return 0;
        }

        return DB::table('override_limit_potong_gaji_anggota')
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('nominal_override')
                    ->orWhereNull('berlaku_mulai_periode')
                    ->orWhereNull('override_updated_by')
                    ->orWhereNull('override_updated_at')
                    ->orWhereNull('alasan_limit_override')
                    ->orWhere('alasan_limit_override', '');
            })
            ->count();
    }

    private function kreditWaserbaNonaktifTanpaAlasan(): int
    {
        if (! Schema::hasTable('override_limit_potong_gaji_anggota')) {
            return 0;
        }

        return DB::table('override_limit_potong_gaji_anggota')
            ->where('kredit_waserba_enabled', false)
            ->where(function ($query): void {
                $query->whereNull('kredit_waserba_disabled_by')
                    ->orWhereNull('kredit_waserba_disabled_at')
                    ->orWhereNull('kredit_waserba_disabled_reason')
                    ->orWhere('kredit_waserba_disabled_reason', '');
            })
            ->count();
    }

    private function limitTerpakaiBerubah(): int
    {
        if (! $this->hasTables(['limit_potong_gaji_anggota', 'pemakaian_potong_gaji', 'riwayat_limit_potong_gaji'])) {
            return 0;
        }

        $firstUsage = DB::table('pemakaian_potong_gaji')
            ->select('limit_potong_gaji_anggota_id', DB::raw('MIN(created_at) as first_used_at'))
            ->groupBy('limit_potong_gaji_anggota_id');

        return DB::table('riwayat_limit_potong_gaji as r')
            ->joinSub($firstUsage, 'u', 'u.limit_potong_gaji_anggota_id', '=', 'r.limit_potong_gaji_anggota_id')
            ->whereColumn('r.changed_at', '>', 'u.first_used_at')
            ->count();
    }

    private function manasukaMasukPayroll(): int
    {
        if (! $this->hasTables(['simpanan', 'jenis_simpanan'])) {
            return 0;
        }

        $query = DB::table('simpanan as s')
            ->join('jenis_simpanan as j', 'j.id', '=', 's.jenis_simpanan_id')
            ->where('j.kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
            ->where(function ($query): void {
                if (Schema::hasColumn('simpanan', 'pemakaian_potong_gaji_id')) {
                    $query->whereNotNull('s.pemakaian_potong_gaji_id');
                }

                $query->orWhere('s.metode_pembayaran', 'potong_gaji')
                    ->orWhere('s.status', 'pending_payroll');
            });

        return $query->count();
    }

    private function payrollPriorityInvalid(): int
    {
        return $this->posPayrollSaatCicilanUnreserved();
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

    private function hasDompetPayrollColumns(): bool
    {
        return Schema::hasTable('dompet_koperasi')
            && Schema::hasColumn('dompet_koperasi', 'jenis_dompet')
            && Schema::hasColumn('dompet_koperasi', 'is_default_penerimaan_payroll');
    }
}
