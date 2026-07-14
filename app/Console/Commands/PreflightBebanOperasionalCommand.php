<?php

namespace App\Console\Commands;

use App\Models\AsetKoperasi;
use App\Models\BebanOperasional;
use App\Models\BebanOperasionalDetail;
use App\Models\ReversalTransaksi;
use App\Services\AsetKoperasiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class PreflightBebanOperasionalCommand extends Command
{
    protected $signature = 'koperasi:preflight-beban-operasional';

    protected $description = 'Audit read-only kesiapan data Beban Operasional Koperasi.';

    public function handle(AsetKoperasiService $asetService): int
    {
        $checks = [
            $this->check('header_tanpa_detail', 'Beban Operasional tanpa detail', $this->headerWithoutDetails()),
            $this->check('detail_orphan', 'Detail Beban Operasional orphan', $this->detailOrphan()),
            $this->check('akun_bukan_beban', 'Detail memakai akun bukan Beban debit', $this->invalidExpenseAccount()),
            $this->check('hpp_eligible', 'Akun HPP ditandai eligible Beban Operasional', $this->hppMarkedEligible()),
            $this->check('eligible_invalid', 'Akun eligible Beban Operasional tidak aktif/beban/debit', $this->invalidEligibleAccount()),
            $this->check('draft_akun_noneligible', 'Draft memakai akun yang tidak eligible Beban Operasional', $this->draftUsingNonEligibleAccount()),
            $this->check('posted_akun_noneligible', 'Posted/Reversed memakai akun yang kini tidak eligible (info)', $this->postedUsingNonEligibleAccount(), false),
            $this->check('eligibility_tanpa_audit', 'Perubahan eligibility Beban Operasional tanpa audit', $this->eligibilityWithoutAudit()),
            $this->check('nominal_invalid', 'Nominal detail nol/negatif', $this->invalidNominal()),
            $this->check('keterangan_detail_kosong', 'Keterangan detail kosong', $this->emptyDetailDescription()),
            $this->check('total_header_salah', 'Total header tidak sama jumlah detail', $this->headerTotalMismatch()),
            $this->check('snapshot_kosong_posted', 'Posted/Reversed tanpa snapshot akun', $this->postedWithoutSnapshot()),
            $this->check('draft_punya_posting', 'Draft sudah mempunyai Mutasi/Jurnal', $this->draftWithPosting()),
            $this->check('posted_tanpa_dompet', 'Posted/Reversed tanpa Dompet', $this->postedWithoutDompet()),
            $this->check('dompet_coa_invalid', 'Dompet tidak punya COA Aset aktif Debit', $this->invalidDompetCoa()),
            $this->check('metode_dompet_mismatch', 'Metode pembayaran dan jenis Dompet tidak cocok', $this->methodDompetMismatch()),
            $this->check('posted_tanpa_mutasi', 'Posted/Reversed tanpa Mutasi Kas keluar asli', $this->postedWithoutMutasi()),
            $this->check('posted_tanpa_jurnal', 'Posted/Reversed tanpa Jurnal asli', $this->postedWithoutJurnal()),
            $this->check('mutasi_ganda', 'Mutasi Kas posting Beban Operasional ganda', $this->duplicatePostingMutasi()),
            $this->check('jurnal_ganda', 'Jurnal posting Beban Operasional ganda', $this->duplicatePostingJurnal()),
            $this->check('jurnal_tidak_seimbang', 'Jurnal Beban Operasional/Reversal tidak balance', $this->unbalancedJournals()),
            $this->check('jurnal_posting_salah', 'Jurnal posting tidak cocok detail Beban dan Dompet', $this->postingJournalMismatch()),
            $this->check('reversed_tanpa_reversal', 'Status reversed tanpa record reversal', $this->reversedWithoutReversal()),
            $this->check('reversal_tanpa_source', 'Reversal Beban tanpa source asli', $this->reversalWithoutSource()),
            $this->check('reversal_ganda', 'Reversal Beban Operasional ganda', $this->duplicateReversal()),
            $this->check('nominal_reversal_salah', 'Nominal reversal berbeda dari source', $this->reversalNominalMismatch()),
            $this->check('reversal_tanpa_posting', 'Reversal tanpa Mutasi/Jurnal balik', $this->reversalWithoutPosting()),
            $this->check('ledger_payroll_beban', 'Beban Operasional masuk ledger potong gaji', $this->payrollLedgerForBeban()),
            $this->check('route_delete_tersedia', 'Route hard delete Beban Operasional masih tersedia', $this->deleteRoutesAvailable()),
            $this->check('aset_histori_masih_deletable', 'Aset dengan histori Beban Operasional masih dapat dihapus', $this->assetWithBebanStillDeletable($asetService)),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight Beban Operasional');
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
            $this->error('Preflight Beban Operasional menemukan konflik kritis. Command ini hanya membaca database.');

            return self::FAILURE;
        }

        $this->info('Preflight Beban Operasional bersih: tidak ada konflik kritis yang terdeteksi.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count, bool $critical = true): array
    {
        return compact('code', 'label', 'count', 'critical');
    }

    private function headerWithoutDetails(): int
    {
        if (! $this->hasTables(['beban_operasional', 'beban_operasional_detail'])) {
            return 0;
        }

        return DB::table('beban_operasional as b')
            ->leftJoin('beban_operasional_detail as d', 'd.beban_operasional_id', '=', 'b.id')
            ->whereNull('d.id')
            ->count('b.id');
    }

    private function detailOrphan(): int
    {
        if (! $this->hasTables(['beban_operasional_detail', 'beban_operasional', 'akun', 'aset_koperasi'])) {
            return 0;
        }

        return DB::table('beban_operasional_detail as d')
            ->leftJoin('beban_operasional as b', 'b.id', '=', 'd.beban_operasional_id')
            ->leftJoin('akun as a', 'a.id', '=', 'd.akun_id')
            ->leftJoin('aset_koperasi as aset', 'aset.id', '=', 'd.aset_koperasi_id')
            ->where(function ($query): void {
                $query->whereNull('b.id')
                    ->orWhereNull('a.id')
                    ->orWhere(fn ($q) => $q->whereNotNull('d.aset_koperasi_id')->whereNull('aset.id'));
            })
            ->count('d.id');
    }

    private function invalidExpenseAccount(): int
    {
        if (! $this->hasTables(['beban_operasional_detail', 'akun'])) {
            return 0;
        }

        return DB::table('beban_operasional_detail as d')
            ->join('akun as a', 'a.id', '=', 'd.akun_id')
            ->where(function ($query): void {
                $query->where('a.kategori', '!=', 'beban')
                    ->orWhere('a.posisi_saldo', '!=', 'debit');
            })
            ->count('d.id');
    }

    private function hppMarkedEligible(): int
    {
        if (! Schema::hasColumn('akun', 'is_beban_operasional')) {
            return 0;
        }

        return DB::table('akun')
            ->where('kode_akun', config('account_map.accounts.hpp.kode_akun', '501'))
            ->where('is_beban_operasional', true)
            ->count();
    }

    private function invalidEligibleAccount(): int
    {
        if (! Schema::hasColumn('akun', 'is_beban_operasional')) {
            return 0;
        }

        return DB::table('akun')
            ->where('is_beban_operasional', true)
            ->where(function ($query): void {
                $query->where('is_aktif', false)
                    ->orWhere('kategori', '!=', 'beban')
                    ->orWhere('posisi_saldo', '!=', 'debit');
            })
            ->count();
    }

    private function draftUsingNonEligibleAccount(): int
    {
        if (! $this->hasTables(['beban_operasional', 'beban_operasional_detail', 'akun']) || ! Schema::hasColumn('akun', 'is_beban_operasional')) {
            return 0;
        }

        return DB::table('beban_operasional_detail as d')
            ->join('beban_operasional as b', 'b.id', '=', 'd.beban_operasional_id')
            ->join('akun as a', 'a.id', '=', 'd.akun_id')
            ->where('b.status', BebanOperasional::STATUS_DRAFT)
            ->where(function ($query): void {
                $query->where('a.is_beban_operasional', false)
                    ->orWhere('a.is_aktif', false)
                    ->orWhere('a.kategori', '!=', 'beban')
                    ->orWhere('a.posisi_saldo', '!=', 'debit');
            })
            ->count('d.id');
    }

    private function postedUsingNonEligibleAccount(): int
    {
        if (! $this->hasTables(['beban_operasional', 'beban_operasional_detail', 'akun']) || ! Schema::hasColumn('akun', 'is_beban_operasional')) {
            return 0;
        }

        return DB::table('beban_operasional_detail as d')
            ->join('beban_operasional as b', 'b.id', '=', 'd.beban_operasional_id')
            ->join('akun as a', 'a.id', '=', 'd.akun_id')
            ->whereIn('b.status', [BebanOperasional::STATUS_POSTED, BebanOperasional::STATUS_REVERSED])
            ->where('a.is_beban_operasional', false)
            ->count('d.id');
    }

    private function eligibilityWithoutAudit(): int
    {
        if (! $this->hasTables(['akun', 'riwayat_akun_beban_operasional']) || ! Schema::hasColumn('akun', 'is_beban_operasional')) {
            return 0;
        }

        return DB::table('akun as a')
            ->where('a.is_beban_operasional', true)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('riwayat_akun_beban_operasional as r')
                    ->whereColumn('r.akun_id', 'a.id')
                    ->where('r.nilai_sesudah', true);
            })
            ->count('a.id');
    }

    private function invalidNominal(): int
    {
        return Schema::hasTable('beban_operasional_detail')
            ? DB::table('beban_operasional_detail')->where('nominal', '<=', 0)->count()
            : 0;
    }

    private function emptyDetailDescription(): int
    {
        return Schema::hasTable('beban_operasional_detail')
            ? DB::table('beban_operasional_detail')
                ->where(fn ($query) => $query->whereNull('keterangan')->orWhere('keterangan', ''))
                ->count()
            : 0;
    }

    private function headerTotalMismatch(): int
    {
        if (! $this->hasTables(['beban_operasional', 'beban_operasional_detail'])) {
            return 0;
        }

        return DB::table('beban_operasional as b')
            ->leftJoin('beban_operasional_detail as d', 'd.beban_operasional_id', '=', 'b.id')
            ->select('b.id', 'b.total_beban', DB::raw('COALESCE(SUM(d.nominal),0) as total_detail'))
            ->groupBy('b.id', 'b.total_beban')
            ->get()
            ->filter(fn ($row) => $this->rupiahInt($row->total_beban) !== $this->rupiahInt($row->total_detail))
            ->count();
    }

    private function postedWithoutSnapshot(): int
    {
        if (! $this->hasTables(['beban_operasional', 'beban_operasional_detail'])) {
            return 0;
        }

        return DB::table('beban_operasional_detail as d')
            ->join('beban_operasional as b', 'b.id', '=', 'd.beban_operasional_id')
            ->whereIn('b.status', [BebanOperasional::STATUS_POSTED, BebanOperasional::STATUS_REVERSED])
            ->where(function ($query): void {
                $query->whereNull('d.kode_akun_snapshot')
                    ->orWhere('d.kode_akun_snapshot', '')
                    ->orWhereNull('d.nama_akun_snapshot')
                    ->orWhere('d.nama_akun_snapshot', '');
            })
            ->count('d.id');
    }

    private function draftWithPosting(): int
    {
        if (! $this->hasTables(['beban_operasional', 'mutasi_kas', 'jurnal_umum'])) {
            return 0;
        }

        return DB::table('beban_operasional as b')
            ->leftJoin('mutasi_kas as m', function ($join): void {
                $join->on('m.referensi_id', '=', 'b.id')->where('m.referensi_tipe', BebanOperasional::class);
            })
            ->leftJoin('jurnal_umum as j', function ($join): void {
                $join->on('j.referensi_id', '=', 'b.id')->where('j.referensi_tipe', BebanOperasional::class);
            })
            ->where('b.status', BebanOperasional::STATUS_DRAFT)
            ->where(fn ($query) => $query->whereNotNull('m.id')->orWhereNotNull('j.id'))
            ->distinct()
            ->count('b.id');
    }

    private function postedWithoutDompet(): int
    {
        return Schema::hasTable('beban_operasional')
            ? DB::table('beban_operasional')
                ->whereIn('status', [BebanOperasional::STATUS_POSTED, BebanOperasional::STATUS_REVERSED])
                ->whereNull('dompet_id')
                ->count()
            : 0;
    }

    private function invalidDompetCoa(): int
    {
        if (! $this->hasTables(['beban_operasional', 'dompet_koperasi', 'akun'])) {
            return 0;
        }

        return DB::table('beban_operasional as b')
            ->join('dompet_koperasi as d', 'd.id', '=', 'b.dompet_id')
            ->leftJoin('akun as a', 'a.id', '=', 'd.akun_id')
            ->whereIn('b.status', [BebanOperasional::STATUS_POSTED, BebanOperasional::STATUS_REVERSED])
            ->where(function ($query): void {
                $query->whereNull('a.id')
                    ->orWhere('a.is_aktif', false)
                    ->orWhere('a.kategori', '!=', 'aset')
                    ->orWhere('a.posisi_saldo', '!=', 'debit');
            })
            ->count('b.id');
    }

    private function methodDompetMismatch(): int
    {
        if (! $this->hasTables(['beban_operasional', 'dompet_koperasi'])) {
            return 0;
        }

        return DB::table('beban_operasional as b')
            ->join('dompet_koperasi as d', 'd.id', '=', 'b.dompet_id')
            ->whereIn('b.status', [BebanOperasional::STATUS_POSTED, BebanOperasional::STATUS_REVERSED])
            ->where(function ($query): void {
                $query->where(fn ($q) => $q->where('b.metode_pembayaran', BebanOperasional::METODE_TUNAI)->where('d.jenis_dompet', '!=', 'kas'))
                    ->orWhere(fn ($q) => $q->where('b.metode_pembayaran', BebanOperasional::METODE_TRANSFER_BANK)->where('d.jenis_dompet', '!=', 'bank'))
                    ->orWhereNull('b.metode_pembayaran');
            })
            ->count('b.id');
    }

    private function postedWithoutMutasi(): int
    {
        if (! $this->hasTables(['beban_operasional', 'mutasi_kas'])) {
            return 0;
        }

        return DB::table('beban_operasional as b')
            ->leftJoin('mutasi_kas as m', function ($join): void {
                $join->on('m.referensi_id', '=', 'b.id')
                    ->where('m.referensi_tipe', BebanOperasional::class)
                    ->where('m.tipe', 'keluar')
                    ->where('m.idempotency_key', 'like', 'beban-operasional:posting:mutasi:%');
            })
            ->whereIn('b.status', [BebanOperasional::STATUS_POSTED, BebanOperasional::STATUS_REVERSED])
            ->whereNull('m.id')
            ->count('b.id');
    }

    private function postedWithoutJurnal(): int
    {
        if (! $this->hasTables(['beban_operasional', 'jurnal_umum'])) {
            return 0;
        }

        return DB::table('beban_operasional as b')
            ->leftJoin('jurnal_umum as j', function ($join): void {
                $join->on('j.referensi_id', '=', 'b.id')
                    ->where('j.referensi_tipe', BebanOperasional::class)
                    ->where('j.idempotency_key', 'like', 'beban-operasional:posting:jurnal:%');
            })
            ->whereIn('b.status', [BebanOperasional::STATUS_POSTED, BebanOperasional::STATUS_REVERSED])
            ->whereNull('j.id')
            ->count('b.id');
    }

    private function duplicatePostingMutasi(): int
    {
        if (! Schema::hasTable('mutasi_kas')) {
            return 0;
        }

        return DB::table('mutasi_kas')
            ->select('referensi_id', DB::raw('COUNT(*) as total'))
            ->where('referensi_tipe', BebanOperasional::class)
            ->where('idempotency_key', 'like', 'beban-operasional:posting:mutasi:%')
            ->groupBy('referensi_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function duplicatePostingJurnal(): int
    {
        if (! Schema::hasTable('jurnal_umum')) {
            return 0;
        }

        return DB::table('jurnal_umum')
            ->select('referensi_id', DB::raw('COUNT(*) as total'))
            ->where('referensi_tipe', BebanOperasional::class)
            ->where('idempotency_key', 'like', 'beban-operasional:posting:jurnal:%')
            ->groupBy('referensi_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function unbalancedJournals(): int
    {
        if (! $this->hasTables(['jurnal_umum', 'jurnal_umum_detail'])) {
            return 0;
        }

        return DB::table('jurnal_umum as j')
            ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
            ->leftJoin('reversal_transaksi as r', function ($join): void {
                $join->on('r.id', '=', 'j.referensi_id')
                    ->where('j.referensi_tipe', ReversalTransaksi::class)
                    ->where('r.source_type', BebanOperasional::class);
            })
            ->where(function ($query): void {
                $query->where('j.referensi_tipe', BebanOperasional::class)
                    ->orWhereNotNull('r.id');
            })
            ->select('j.id', DB::raw('COALESCE(SUM(d.debit),0) as debit'), DB::raw('COALESCE(SUM(d.kredit),0) as kredit'))
            ->groupBy('j.id')
            ->get()
            ->filter(fn ($row) => $this->rupiahInt($row->debit) !== $this->rupiahInt($row->kredit) || $this->rupiahInt($row->debit) <= 0)
            ->count();
    }

    private function postingJournalMismatch(): int
    {
        if (! $this->hasTables(['beban_operasional', 'beban_operasional_detail', 'dompet_koperasi', 'akun', 'jurnal_umum', 'jurnal_umum_detail'])) {
            return 0;
        }

        return BebanOperasional::query()
            ->with(['details.akun', 'dompet.akun'])
            ->whereIn('status', [BebanOperasional::STATUS_POSTED, BebanOperasional::STATUS_REVERSED])
            ->get()
            ->filter(function (BebanOperasional $beban): bool {
                $jurnal = DB::table('jurnal_umum')
                    ->where('referensi_tipe', BebanOperasional::class)
                    ->where('referensi_id', $beban->id)
                    ->where('idempotency_key', 'beban-operasional:posting:jurnal:' . $beban->id)
                    ->first();

                if (! $jurnal || ! $beban->dompet?->akun) {
                    return true;
                }

                $details = DB::table('jurnal_umum_detail')
                    ->where('jurnal_umum_id', $jurnal->id)
                    ->get();

                $total = $this->rupiahInt($beban->total_beban);
                $debit = $this->rupiahInt($details->sum('debit'));
                $kredit = $this->rupiahInt($details->sum('kredit'));
                $creditDompet = $this->rupiahInt($details->where('akun_kode', $beban->dompet->akun->kode_akun)->sum('kredit'));

                $expectedDebitByCode = $beban->details
                    ->groupBy(fn (BebanOperasionalDetail $detail) => $detail->kode_akun_snapshot ?: $detail->akun?->kode_akun)
                    ->map(fn ($group) => $this->rupiahInt($group->sum('nominal')));

                $actualDebitByCode = $details
                    ->where('debit', '>', 0)
                    ->groupBy('akun_kode')
                    ->map(fn ($group) => $this->rupiahInt($group->sum('debit')));

                $detailMismatch = $expectedDebitByCode->contains(
                    fn (int $expected, ?string $kode) => $kode === null || $actualDebitByCode->get($kode, 0) !== $expected
                );

                return $debit !== $total || $kredit !== $total || $creditDompet !== $total || $detailMismatch;
            })
            ->count();
    }

    private function reversedWithoutReversal(): int
    {
        if (! $this->hasTables(['beban_operasional', 'reversal_transaksi'])) {
            return 0;
        }

        return DB::table('beban_operasional as b')
            ->leftJoin('reversal_transaksi as r', 'r.id', '=', 'b.reversal_transaksi_id')
            ->where('b.status', BebanOperasional::STATUS_REVERSED)
            ->where(function ($query): void {
                $query->whereNull('b.reversal_transaksi_id')
                    ->orWhereNull('r.id')
                    ->orWhere('r.source_type', '!=', BebanOperasional::class)
                    ->orWhereColumn('r.source_id', '!=', 'b.id');
            })
            ->count('b.id');
    }

    private function reversalWithoutSource(): int
    {
        if (! $this->hasTables(['reversal_transaksi', 'beban_operasional'])) {
            return 0;
        }

        return DB::table('reversal_transaksi as r')
            ->leftJoin('beban_operasional as b', 'b.id', '=', 'r.source_id')
            ->where('r.source_type', BebanOperasional::class)
            ->whereNull('b.id')
            ->count('r.id');
    }

    private function duplicateReversal(): int
    {
        if (! Schema::hasTable('reversal_transaksi')) {
            return 0;
        }

        return DB::table('reversal_transaksi')
            ->select('source_id', DB::raw('COUNT(*) as total'))
            ->where('source_type', BebanOperasional::class)
            ->groupBy('source_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function reversalNominalMismatch(): int
    {
        if (! $this->hasTables(['reversal_transaksi', 'beban_operasional'])) {
            return 0;
        }

        return DB::table('reversal_transaksi as r')
            ->join('beban_operasional as b', 'b.id', '=', 'r.source_id')
            ->where('r.source_type', BebanOperasional::class)
            ->get(['r.nominal', 'b.total_beban'])
            ->filter(fn ($row) => $this->rupiahInt($row->nominal) !== $this->rupiahInt($row->total_beban))
            ->count();
    }

    private function reversalWithoutPosting(): int
    {
        if (! $this->hasTables(['reversal_transaksi', 'mutasi_kas', 'jurnal_umum'])) {
            return 0;
        }

        return DB::table('reversal_transaksi as r')
            ->leftJoin('mutasi_kas as m', function ($join): void {
                $join->on('m.referensi_id', '=', 'r.id')
                    ->where('m.referensi_tipe', ReversalTransaksi::class)
                    ->where('m.idempotency_key', 'like', 'beban-operasional:reversal:mutasi:%')
                    ->where('m.tipe', 'masuk');
            })
            ->leftJoin('jurnal_umum as j', function ($join): void {
                $join->on('j.referensi_id', '=', 'r.id')
                    ->where('j.referensi_tipe', ReversalTransaksi::class)
                    ->where('j.idempotency_key', 'like', 'beban-operasional:reversal:jurnal:%');
            })
            ->where('r.source_type', BebanOperasional::class)
            ->where(fn ($query) => $query->whereNull('m.id')->orWhereNull('j.id'))
            ->distinct()
            ->count('r.id');
    }

    private function payrollLedgerForBeban(): int
    {
        if (! Schema::hasTable('pemakaian_potong_gaji')) {
            return 0;
        }

        return DB::table('pemakaian_potong_gaji')
            ->whereIn('source_type', [BebanOperasional::class, BebanOperasionalDetail::class])
            ->count();
    }

    private function deleteRoutesAvailable(): int
    {
        return collect(Route::getRoutes())
            ->filter(fn ($route) => in_array('DELETE', $route->methods(), true) && str_contains($route->uri(), 'beban-operasional'))
            ->count();
    }

    private function assetWithBebanStillDeletable(AsetKoperasiService $service): int
    {
        if (! $this->hasTables(['aset_koperasi', 'beban_operasional_detail'])) {
            return 0;
        }

        return AsetKoperasi::query()
            ->whereHas('sewaPrinterDetails')
            ->orWhereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('beban_operasional_detail')
                    ->whereColumn('beban_operasional_detail.aset_koperasi_id', 'aset_koperasi.id');
            })
            ->get()
            ->filter(function (AsetKoperasi $aset) use ($service): bool {
                $dependencies = $service->dependencyCounts($aset);
                $guard = $service->canDelete($aset);

                return (($dependencies['Beban Operasional Aset'] ?? 0) > 0) && $guard['allowed'];
            })
            ->count();
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
