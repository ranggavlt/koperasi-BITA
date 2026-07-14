<?php

namespace App\Console\Commands;

use App\Models\AsetKoperasi;
use App\Models\PembayaranSewaMobil;
use App\Models\SewaMobil;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightSewaMobilCommand extends Command
{
    protected $signature = 'koperasi:preflight-sewa-mobil';

    protected $description = 'Audit read-only kesiapan data transaksi Sewa Mobil Koperasi.';

    public function handle(): int
    {
        $checks = [
            $this->check('user_karyawan_tanpa_karyawan', 'User role Karyawan tanpa karyawan_id', $this->userKaryawanTanpaKaryawan()),
            $this->check('user_karyawan_ganda', 'Lebih dari satu user untuk satu Karyawan', $this->duplicateUsersForKaryawan()),
            $this->check('akun_aktif_karyawan_berhenti', 'Akun aktif milik Karyawan berhenti', $this->activeStoppedEmployeeAccount()),
            $this->check('sewa_orphan', 'Sewa tanpa Mobil/Karyawan/User valid', $this->sewaOrphan()),
            $this->check('aset_bukan_mobil', 'Sewa memakai aset bukan Mobil', $this->assetNotMobil()),
            $this->check('snapshot_perusahaan_kosong', 'Snapshot perusahaan penyewa kosong', $this->emptyCompanySnapshot()),
            $this->check('waktu_invalid', 'Waktu mulai >= selesai', $this->invalidTime()),
            $this->check('jadwal_overlap', 'Jadwal approved/berjalan overlap', $this->overlap()),
            $this->check('approved_pengurus_invalid', 'Approved tanpa Pengurus aktif/snapshot', $this->approvedInvalidPengurus()),
            $this->check('approval_recorder_kosong', 'Approval recorded_by kosong', $this->approvedWithoutRecorder()),
            $this->check('tarif_approved_nol', 'Tarif nol pada approved/berjalan/selesai', $this->approvedZeroTariff()),
            $this->check('pembayaran_sebagian', 'Pembayaran sebagian atau tidak sama tarif', $this->partialPayment()),
            $this->check('metode_dompet_mismatch', 'Metode pembayaran dan jenis Dompet tidak cocok', $this->methodDompetMismatch()),
            $this->check('pembayaran_tanpa_posting', 'Pembayaran tanpa Mutasi/Jurnal', $this->paymentWithoutPosting()),
            $this->check('jurnal_pembayaran_salah', 'Jurnal pembayaran tidak memakai Pendapatan Diterima Dimuka', $this->paymentJournalWithoutDeferredRevenue()),
            $this->check('berjalan_belum_paid', 'Sewa berjalan belum paid', $this->runningNotPaid()),
            $this->check('aset_berjalan_status_salah', 'Aset berjalan tetapi status bukan digunakan/disewa', $this->runningAssetWrongStatus()),
            $this->check('selesai_aset_masih_used', 'Sewa selesai tetapi aset masih digunakan/disewa tanpa rental lain', $this->completedAssetStillUsedWithoutRunning()),
            $this->check('selesai_tanpa_jurnal_pendapatan', 'Sewa selesai tanpa Jurnal Pendapatan', $this->completedWithoutRevenueJournal()),
            $this->check('pendapatan_sebelum_selesai', 'Pendapatan diakui sebelum selesai', $this->revenueBeforeCompleted()),
            $this->check('refund_ganda', 'Refund ganda', $this->duplicateRefund()),
            $this->check('orphan_posting', 'Transaksi hard deleted/orphan', $this->orphanPosting()),
            $this->check('ledger_payroll_sewa', 'Sewa Mobil membuat ledger payroll', $this->payrollLedgerForSewaMobil()),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight Sewa Mobil');
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
            $this->error('Preflight Sewa Mobil menemukan konflik kritis. Lakukan rekonsiliasi manual; command ini tidak menulis database.');

            return self::FAILURE;
        }

        $this->info('Preflight Sewa Mobil bersih: tidak ada konflik kritis yang terdeteksi.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count, bool $critical = true): array
    {
        return compact('code', 'label', 'count', 'critical');
    }

    private function userKaryawanTanpaKaryawan(): int
    {
        return $this->hasTables(['users']) && $this->hasColumns('users', ['role', 'karyawan_id'])
            ? DB::table('users')->where('role', 'karyawan')->whereNull('karyawan_id')->count()
            : 0;
    }

    private function duplicateUsersForKaryawan(): int
    {
        if (! $this->hasTables(['users']) || ! $this->hasColumns('users', ['karyawan_id'])) {
            return 0;
        }

        return DB::table('users')
            ->select('karyawan_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('karyawan_id')
            ->groupBy('karyawan_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function activeStoppedEmployeeAccount(): int
    {
        if (
            ! $this->hasTables(['users', 'karyawan'])
            || ! $this->hasColumns('users', ['role', 'karyawan_id', 'is_active'])
            || ! $this->hasColumns('karyawan', ['status_kerja'])
        ) {
            return 0;
        }

        return DB::table('users as u')
            ->join('karyawan as k', 'k.id', '=', 'u.karyawan_id')
            ->where('u.role', 'karyawan')
            ->where('u.is_active', true)
            ->where('k.status_kerja', 'berhenti')
            ->count('u.id');
    }

    private function sewaOrphan(): int
    {
        if (! $this->hasTables(['sewa_mobil', 'aset_koperasi', 'karyawan', 'users'])) {
            return 0;
        }

        return DB::table('sewa_mobil as s')
            ->leftJoin('aset_koperasi as a', 'a.id', '=', 's.aset_koperasi_id')
            ->leftJoin('karyawan as k', 'k.id', '=', 's.karyawan_id')
            ->leftJoin('users as u', 'u.id', '=', 's.pemohon_user_id')
            ->whereNull('a.id')
            ->orWhereNull('k.id')
            ->orWhereNull('u.id')
            ->count('s.id');
    }

    private function assetNotMobil(): int
    {
        if (! $this->hasTables(['sewa_mobil', 'aset_koperasi', 'aset_mobil'])) {
            return 0;
        }

        return DB::table('sewa_mobil as s')
            ->join('aset_koperasi as a', 'a.id', '=', 's.aset_koperasi_id')
            ->leftJoin('aset_mobil as m', 'm.aset_koperasi_id', '=', 'a.id')
            ->where(function ($query): void {
                $query->where('a.jenis_aset', '!=', AsetKoperasi::JENIS_MOBIL)
                    ->orWhereNull('m.id');
            })
            ->count('s.id');
    }

    private function emptyCompanySnapshot(): int
    {
        return Schema::hasTable('sewa_mobil')
            ? DB::table('sewa_mobil')->where(function ($query): void {
                $query->whereNull('nama_perusahaan_snapshot')->orWhere('nama_perusahaan_snapshot', '');
            })->count()
            : 0;
    }

    private function invalidTime(): int
    {
        return Schema::hasTable('sewa_mobil')
            ? DB::table('sewa_mobil')->whereColumn('mulai_at', '>=', 'selesai_at')->count()
            : 0;
    }

    private function overlap(): int
    {
        if (! Schema::hasTable('sewa_mobil')) {
            return 0;
        }

        $statuses = [SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN];

        return DB::table('sewa_mobil as a')
            ->join('sewa_mobil as b', function ($join) use ($statuses): void {
                $join->on('a.aset_koperasi_id', '=', 'b.aset_koperasi_id')
                    ->whereColumn('a.id', '<', 'b.id')
                    ->whereIn('a.status', $statuses)
                    ->whereIn('b.status', $statuses)
                    ->whereColumn('a.mulai_at', '<', 'b.selesai_at')
                    ->whereColumn('a.selesai_at', '>', 'b.mulai_at');
            })
            ->count('a.id');
    }

    private function approvedInvalidPengurus(): int
    {
        if (! $this->hasTables(['sewa_mobil', 'pengurus_koperasi', 'anggota', 'karyawan'])) {
            return 0;
        }

        return DB::table('sewa_mobil as s')
            ->leftJoin('pengurus_koperasi as p', 'p.id', '=', 's.pengurus_penyetuju_id')
            ->leftJoin('anggota as a', 'a.id', '=', 'p.anggota_id')
            ->leftJoin('karyawan as k', 'k.id', '=', 'a.karyawan_id')
            ->whereIn('s.status', [SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN, SewaMobil::STATUS_SELESAI])
            ->where(function ($query): void {
                $query->whereNull('p.id')
                    ->orWhere('p.status', '!=', 'aktif')
                    ->orWhere('a.status', '!=', 'aktif')
                    ->orWhere('k.status_kerja', '!=', 'aktif')
                    ->orWhereNull('s.nama_pengurus_snapshot')
                    ->orWhereNull('s.jabatan_pengurus_snapshot');
            })
            ->count('s.id');
    }

    private function approvedWithoutRecorder(): int
    {
        return Schema::hasTable('sewa_mobil')
            ? DB::table('sewa_mobil')
                ->whereIn('status', [SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN, SewaMobil::STATUS_SELESAI])
                ->whereNull('approval_recorded_by')
                ->count()
            : 0;
    }

    private function approvedZeroTariff(): int
    {
        return Schema::hasTable('sewa_mobil')
            ? DB::table('sewa_mobil')
                ->whereIn('status', [SewaMobil::STATUS_DISETUJUI, SewaMobil::STATUS_BERJALAN, SewaMobil::STATUS_SELESAI])
                ->where('tarif_total', '<=', 0)
                ->count()
            : 0;
    }

    private function partialPayment(): int
    {
        if (! $this->hasTables(['pembayaran_sewa_mobil', 'sewa_mobil'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_mobil as p')
            ->join('sewa_mobil as s', 's.id', '=', 'p.sewa_mobil_id')
            ->whereColumn('p.jumlah_bayar', '!=', 's.tarif_total')
            ->count('p.id');
    }

    private function methodDompetMismatch(): int
    {
        if (! $this->hasTables(['pembayaran_sewa_mobil', 'dompet_koperasi'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_mobil as p')
            ->join('dompet_koperasi as d', 'd.id', '=', 'p.dompet_id')
            ->where(function ($query): void {
                $query->where(fn ($q) => $q->where('p.metode_pembayaran', PembayaranSewaMobil::METODE_TUNAI)->where('d.jenis_dompet', '!=', 'kas'))
                    ->orWhere(fn ($q) => $q->where('p.metode_pembayaran', PembayaranSewaMobil::METODE_TRANSFER_BANK)->where('d.jenis_dompet', '!=', 'bank'));
            })
            ->count('p.id');
    }

    private function paymentWithoutPosting(): int
    {
        if (! $this->hasTables(['pembayaran_sewa_mobil', 'mutasi_kas', 'jurnal_umum'])) {
            return 0;
        }

        return DB::table('pembayaran_sewa_mobil as p')
            ->leftJoin('mutasi_kas as m', function ($join): void {
                $join->on('m.referensi_id', '=', 'p.id')
                    ->where('m.referensi_tipe', PembayaranSewaMobil::class)
                    ->where('m.tipe', 'masuk');
            })
            ->leftJoin('jurnal_umum as j', function ($join): void {
                $join->on('j.referensi_id', '=', 'p.id')
                    ->where('j.referensi_tipe', PembayaranSewaMobil::class)
                    ->where('j.idempotency_key', 'like', 'sewa-mobil:pembayaran-dimuka:jurnal:%');
            })
            ->where('p.status', PembayaranSewaMobil::STATUS_PAID)
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

        $kode = (string) config('account_map.accounts.pendapatan_diterima_dimuka_sewa_mobil.kode_akun');

        return DB::table('jurnal_umum as j')
            ->leftJoin('jurnal_umum_detail as d', function ($join) use ($kode): void {
                $join->on('d.jurnal_umum_id', '=', 'j.id')
                    ->where('d.akun_kode', $kode)
                    ->where('d.kredit', '>', 0);
            })
            ->where('j.referensi_tipe', PembayaranSewaMobil::class)
            ->where('j.idempotency_key', 'like', 'sewa-mobil:pembayaran-dimuka:jurnal:%')
            ->whereNull('d.id')
            ->count('j.id');
    }

    private function runningNotPaid(): int
    {
        return Schema::hasTable('sewa_mobil')
            ? DB::table('sewa_mobil')
                ->where('status', SewaMobil::STATUS_BERJALAN)
                ->where('status_pembayaran', '!=', SewaMobil::PEMBAYARAN_PAID)
                ->count()
            : 0;
    }

    private function runningAssetWrongStatus(): int
    {
        if (! $this->hasTables(['sewa_mobil', 'aset_koperasi'])) {
            return 0;
        }

        return DB::table('sewa_mobil as s')
            ->join('aset_koperasi as a', 'a.id', '=', 's.aset_koperasi_id')
            ->where('s.status', SewaMobil::STATUS_BERJALAN)
            ->where('a.status', '!=', AsetKoperasi::STATUS_DIGUNAKAN_DISEWA)
            ->count('s.id');
    }

    private function completedAssetStillUsedWithoutRunning(): int
    {
        if (! $this->hasTables(['sewa_mobil', 'aset_koperasi'])) {
            return 0;
        }

        return DB::table('sewa_mobil as s')
            ->join('aset_koperasi as a', 'a.id', '=', 's.aset_koperasi_id')
            ->leftJoin('sewa_mobil as running', function ($join): void {
                $join->on('running.aset_koperasi_id', '=', 'a.id')
                    ->where('running.status', SewaMobil::STATUS_BERJALAN);
            })
            ->where('s.status', SewaMobil::STATUS_SELESAI)
            ->where('a.status', AsetKoperasi::STATUS_DIGUNAKAN_DISEWA)
            ->whereNull('running.id')
            ->count('s.id');
    }

    private function completedWithoutRevenueJournal(): int
    {
        if (! $this->hasTables(['sewa_mobil', 'jurnal_umum'])) {
            return 0;
        }

        return DB::table('sewa_mobil as s')
            ->leftJoin('jurnal_umum as j', function ($join): void {
                $join->on('j.referensi_id', '=', 's.id')
                    ->where('j.referensi_tipe', SewaMobil::class)
                    ->where('j.idempotency_key', 'like', 'sewa-mobil:pengakuan-pendapatan:jurnal:%');
            })
            ->where('s.status', SewaMobil::STATUS_SELESAI)
            ->whereNull('j.id')
            ->count('s.id');
    }

    private function revenueBeforeCompleted(): int
    {
        if (! $this->hasTables(['sewa_mobil', 'jurnal_umum'])) {
            return 0;
        }

        return DB::table('jurnal_umum as j')
            ->join('sewa_mobil as s', 's.id', '=', 'j.referensi_id')
            ->where('j.referensi_tipe', SewaMobil::class)
            ->where('j.idempotency_key', 'like', 'sewa-mobil:pengakuan-pendapatan:jurnal:%')
            ->where('s.status', '!=', SewaMobil::STATUS_SELESAI)
            ->count('j.id');
    }

    private function duplicateRefund(): int
    {
        if (! $this->hasTables(['pembayaran_sewa_mobil', 'mutasi_kas', 'jurnal_umum'])) {
            return 0;
        }

        $mutasi = DB::table('mutasi_kas')
            ->select('referensi_id', DB::raw('COUNT(*) as total'))
            ->where('referensi_tipe', PembayaranSewaMobil::class)
            ->where('idempotency_key', 'like', 'sewa-mobil:refund:mutasi:%')
            ->groupBy('referensi_id')
            ->having('total', '>', 1)
            ->get()
            ->count();

        $jurnal = DB::table('jurnal_umum')
            ->select('referensi_id', DB::raw('COUNT(*) as total'))
            ->where('referensi_tipe', PembayaranSewaMobil::class)
            ->where('idempotency_key', 'like', 'sewa-mobil:refund:jurnal:%')
            ->groupBy('referensi_id')
            ->having('total', '>', 1)
            ->get()
            ->count();

        return $mutasi + $jurnal;
    }

    private function orphanPosting(): int
    {
        $issues = 0;

        if ($this->hasTables(['pembayaran_sewa_mobil', 'sewa_mobil'])) {
            $issues += DB::table('pembayaran_sewa_mobil as p')
                ->leftJoin('sewa_mobil as s', 's.id', '=', 'p.sewa_mobil_id')
                ->whereNull('s.id')
                ->count('p.id');
        }

        foreach (['mutasi_kas', 'jurnal_umum'] as $table) {
            if (! $this->hasTables([$table, 'pembayaran_sewa_mobil'])) {
                continue;
            }

            $issues += DB::table("{$table} as t")
                ->leftJoin('pembayaran_sewa_mobil as p', 'p.id', '=', 't.referensi_id')
                ->where('t.referensi_tipe', PembayaranSewaMobil::class)
                ->whereNull('p.id')
                ->count('t.id');
        }

        if ($this->hasTables(['jurnal_umum', 'sewa_mobil'])) {
            $issues += DB::table('jurnal_umum as j')
                ->leftJoin('sewa_mobil as s', 's.id', '=', 'j.referensi_id')
                ->where('j.referensi_tipe', SewaMobil::class)
                ->whereNull('s.id')
                ->count('j.id');
        }

        return $issues;
    }

    private function payrollLedgerForSewaMobil(): int
    {
        if (! Schema::hasTable('pemakaian_potong_gaji')) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji')
            ->whereIn('source_type', [SewaMobil::class, PembayaranSewaMobil::class])
            ->count();
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

    private function hasColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
}
