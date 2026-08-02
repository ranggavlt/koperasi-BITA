<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightKeanggotaanCommand extends Command
{
    protected $signature = 'koperasi:preflight-keanggotaan';

    protected $description = 'Read-only preflight lifecycle karyawan keluar, siklus keanggotaan, hak simpanan, offset, dan refund.';

    /** @var array<int, array{key:string,label:string,count:int,critical:bool}> */
    private array $results = [];

    public function handle(): int
    {
        $this->info('Preflight Keanggotaan KBSM (read-only)');

        if (! $this->hasTables(['anggota', 'karyawan', 'siklus_keanggotaan', 'penyelesaian_keanggotaan', 'penyelesaian_keanggotaan_detail'])) {
            $this->warn('Schema lifecycle keanggotaan belum lengkap.');

            return self::FAILURE;
        }

        $this->check('anggota_aktif_tanpa_siklus', 'Anggota aktif tanpa siklus aktif', $this->activeAnggotaWithoutActiveCycle());
        $this->check('anggota_nonaktif_siklus_aktif', 'Anggota nonaktif masih memiliki siklus aktif', $this->inactiveAnggotaWithActiveCycle());
        $this->check('siklus_aktif_ganda', 'Lebih dari satu siklus aktif per Anggota', $this->duplicateActiveCycles());
        $this->check('siklus_closed_tanpa_penyelesaian', 'Siklus closed tanpa penyelesaian aktif/final', $this->closedCycleWithoutSettlement());
        $this->check('anggota_nonaktif_tanpa_penyelesaian', 'Anggota nonaktif tanpa settlement pada siklus closed', $this->inactiveAnggotaWithoutSettlement());
        $this->check('penyelesaian_ganda', 'Lebih dari satu penyelesaian aktif/final per siklus', $this->duplicateSettlementPerCycle());
        $this->check('simpanan_pokok_ganda_siklus', 'Lebih dari satu Simpanan Pokok valid per siklus', $this->duplicatePokokPerCycle());
        $this->check('simpanan_pokok_tanpa_siklus', 'Simpanan Pokok valid tanpa siklus', $this->validPokokWithoutCycle());
        $this->check('settlement_detail_mismatch', 'Total detail penyelesaian tidak sesuai total kewajiban snapshot', $this->settlementDetailMismatch());
        $this->check('settlement_hak_mismatch', 'Total hak settlement tidak sesuai detail hak', $this->settlementRightsMismatch());
        $this->check('hak_source_ganda', 'Sumber hak settlement dipakai lebih dari satu kali', $this->duplicateRightSource());
        $this->check('hak_alokasi_berlebih', 'Alokasi hak melebihi nominal hak sumber', $this->rightAllocationExceedsSource());
        $this->check('kewajiban_alokasi_berlebih', 'Alokasi kewajiban melebihi nominal kewajiban', $this->obligationAllocationExceedsSource());
        $this->check('settlement_completed_sisa', 'Penyelesaian completed masih punya sisa kewajiban', $this->completedWithRemainingObligation());
        $this->check('settlement_completed_hak_sisa', 'Penyelesaian completed masih punya hak belum dialokasikan/refund', $this->completedWithRemainingRights());
        $this->check('refund_tanpa_mutasi', 'Penyelesaian completed dengan refund tanpa Mutasi Kas', $this->completedRefundWithoutMutasi());
        $this->check('refund_mutasi_tanpa_jurnal', 'Mutasi refund penyelesaian tanpa Jurnal refund', $this->refundMutasiWithoutJurnal());
        $this->check('jurnal_tidak_balance', 'Jurnal Umum tidak balance', $this->unbalancedJournals());
        $this->check('pinjaman_sisa_jadwal_mismatch', 'Sisa Pinjaman tidak sesuai jadwal unpaid/partial', $this->pinjamanScheduleMismatch());
        $this->check('offset_jadwal_tanpa_settlement', 'Jadwal cicilan offset tanpa detail penyelesaian Pinjaman', $this->scheduleOffsetWithoutSettlement());
        $this->check('reversal_simpanan_exit_tanpa_record', 'Simpanan Pokok reversed_due_to_exit tanpa reversal', $this->exitReversedPokokWithoutReversal());
        $this->check('wajib_outstanding_setelah_keluar', 'Jadwal Wajib outstanding/reserved pada siklus closed', $this->wajibOutstandingAfterExit());
        $this->check('wajib_batal_tanpa_jurnal', 'Tagihan Wajib dibatalkan tanpa Jurnal pembalik', $this->wajibCancelledWithoutJournal());
        $this->check('wajib_settled_salah_batal', 'Tagihan Wajib settled memiliki marker pembatalan keluar', $this->settledWajibWronglyCancelled());
        $this->check('wajib_paid_tidak_masuk_hak', 'Simpanan Wajib paid belum masuk detail hak settlement', $this->paidWajibMissingRightDetail());
        $this->check('manasuka_tidak_masuk_hak', 'Saldo Manasuka lama belum masuk detail hak settlement', $this->manasukaMissingRightDetail());
        $this->check('saldo_manasuka_completed_sisa', 'Saldo Manasuka siklus lama masih tersisa setelah settlement completed', $this->completedOldManasukaStillPositive());
        $this->check('reaktivasi_sebelum_completed', 'Anggota punya siklus baru sebelum settlement sebelumnya completed', $this->reactivationBeforeCompleted());
        $this->check('penonaktifan_batal_material', 'Penonaktifan dibatalkan padahal sudah ada refund/offset/material process', $this->cancelledDeactivationWithMaterialProcess());
        $this->check('penonaktifan_batal_manasuka_frozen', 'Penonaktifan dibatalkan tetapi saldo Manasuka masih frozen', $this->cancelledDeactivationFrozenManasuka());
        $this->check('penonaktifan_batal_wajib_cancelled', 'Penonaktifan dibatalkan tetapi tagihan Wajib exit masih cancelled', $this->cancelledDeactivationWajibStillCancelled());
        $this->check('daftar_ulang_siklus_lama', 'Pendaftaran kembali memakai ulang siklus lama', $this->reRegistrationUsesOldCycle());
        $this->check('daftar_ulang_pinjaman_lama_aktif', 'Pendaftaran kembali terjadi saat Pinjaman siklus lama belum lunas', $this->reRegisteredWithOldActiveLoan());
        $this->check('daftar_ulang_manasuka_tidak_nol', 'Saldo Manasuka siklus daftar ulang tidak nol saat dibuat', $this->reRegisteredManasukaNotZero());
        $this->check('detail_source_orphan', 'Detail settlement mengarah ke source umum yang hilang', $this->orphanSettlementDetails());
        $this->check('idempotency_ganda', 'Idempotency key duplicate pada tabel lifecycle/akuntansi utama', $this->duplicateIdempotencyKeys());

        $this->newLine();
        $this->table(['Kode', 'Temuan', 'Count', 'Severity'], array_map(
            fn (array $item): array => [$item['key'], $item['label'], $item['count'], $item['critical'] ? 'critical' : 'warning'],
            $this->results
        ));

        $critical = collect($this->results)->where('critical', true)->sum('count');
        if ($critical > 0) {
            $this->error("Preflight menemukan {$critical} konflik kritis.");

            return self::FAILURE;
        }

        $this->info('Preflight keanggotaan selesai tanpa konflik kritis.');

        return self::SUCCESS;
    }

    private function check(string $key, string $label, int $count, bool $critical = true): void
    {
        $this->results[] = compact('key', 'label', 'count', 'critical');
    }

    /**
     * @param  array<int, string>  $tables
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

    private function activeAnggotaWithoutActiveCycle(): int
    {
        return DB::table('anggota')
            ->where('status', 'aktif')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('siklus_keanggotaan')
                    ->whereColumn('siklus_keanggotaan.anggota_id', 'anggota.id')
                    ->where('siklus_keanggotaan.status', 'active');
            })
            ->count();
    }

    private function inactiveAnggotaWithActiveCycle(): int
    {
        return DB::table('anggota')
            ->join('siklus_keanggotaan', 'siklus_keanggotaan.anggota_id', '=', 'anggota.id')
            ->where('anggota.status', '!=', 'aktif')
            ->where('siklus_keanggotaan.status', 'active')
            ->count();
    }

    private function duplicateActiveCycles(): int
    {
        return DB::query()
            ->fromSub(
                DB::table('siklus_keanggotaan')
                    ->select('anggota_id', DB::raw('COUNT(*) as total'))
                    ->where('status', 'active')
                    ->groupBy('anggota_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicates'
            )
            ->count();
    }

    private function closedCycleWithoutSettlement(): int
    {
        return DB::table('siklus_keanggotaan')
            ->where('status', 'closed')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('penyelesaian_keanggotaan')
                    ->whereColumn('penyelesaian_keanggotaan.siklus_keanggotaan_id', 'siklus_keanggotaan.id')
                    ->whereNotIn('penyelesaian_keanggotaan.status', ['cancelled', 'dibatalkan_penonaktifan']);
            })
            ->count();
    }

    private function duplicateSettlementPerCycle(): int
    {
        return DB::query()
            ->fromSub(
                DB::table('penyelesaian_keanggotaan')
                    ->select('siklus_keanggotaan_id', DB::raw('COUNT(*) as total'))
                    ->whereNotIn('status', ['cancelled', 'dibatalkan_penonaktifan'])
                    ->groupBy('siklus_keanggotaan_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicates'
            )
            ->count();
    }

    private function inactiveAnggotaWithoutSettlement(): int
    {
        return DB::table('anggota')
            ->where('status', '!=', 'aktif')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('siklus_keanggotaan')
                    ->join('penyelesaian_keanggotaan', 'penyelesaian_keanggotaan.siklus_keanggotaan_id', '=', 'siklus_keanggotaan.id')
                    ->whereColumn('siklus_keanggotaan.anggota_id', 'anggota.id')
                    ->where('siklus_keanggotaan.status', 'closed')
                    ->whereNotIn('penyelesaian_keanggotaan.status', ['cancelled', 'dibatalkan_penonaktifan']);
            })
            ->count();
    }

    private function duplicatePokokPerCycle(): int
    {
        if (! Schema::hasColumn('simpanan', 'siklus_keanggotaan_id')) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table('simpanan')
                    ->select('siklus_keanggotaan_id', DB::raw('COUNT(*) as total'))
                    ->where('kode_jenis_snapshot', 'SIMPANAN_POKOK')
                    ->whereNotIn('status', ['reversed', 'reversed_due_to_exit'])
                    ->whereNotNull('siklus_keanggotaan_id')
                    ->groupBy('siklus_keanggotaan_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicates'
            )
            ->count();
    }

    private function validPokokWithoutCycle(): int
    {
        if (! Schema::hasColumn('simpanan', 'siklus_keanggotaan_id')) {
            return 0;
        }

        return DB::table('simpanan')
            ->where('kode_jenis_snapshot', 'SIMPANAN_POKOK')
            ->whereNotIn('status', ['reversed', 'reversed_due_to_exit'])
            ->whereNull('siklus_keanggotaan_id')
            ->count();
    }

    private function settlementDetailMismatch(): int
    {
        return DB::query()
            ->fromSub(
                DB::table('penyelesaian_keanggotaan')
                    ->leftJoin('penyelesaian_keanggotaan_detail', 'penyelesaian_keanggotaan_detail.penyelesaian_keanggotaan_id', '=', 'penyelesaian_keanggotaan.id')
                    ->select('penyelesaian_keanggotaan.id', 'penyelesaian_keanggotaan.total_kewajiban_awal', DB::raw('COALESCE(SUM(penyelesaian_keanggotaan_detail.nominal_kewajiban_awal), 0) as total_detail'))
                    ->groupBy('penyelesaian_keanggotaan.id', 'penyelesaian_keanggotaan.total_kewajiban_awal'),
                'snapshot'
            )
            ->whereRaw('ABS(CAST(total_kewajiban_awal AS DECIMAL(15,2)) - CAST(total_detail AS DECIMAL(15,2))) > 0.01')
            ->count();
    }

    private function settlementRightsMismatch(): int
    {
        if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'tipe_detail')) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table('penyelesaian_keanggotaan')
                    ->leftJoin('penyelesaian_keanggotaan_detail', function ($join): void {
                        $join->on('penyelesaian_keanggotaan_detail.penyelesaian_keanggotaan_id', '=', 'penyelesaian_keanggotaan.id')
                            ->where('penyelesaian_keanggotaan_detail.tipe_detail', 'hak');
                    })
                    ->select('penyelesaian_keanggotaan.id', 'penyelesaian_keanggotaan.total_hak_anggota', DB::raw('COALESCE(SUM(penyelesaian_keanggotaan_detail.nominal_hak_awal), 0) as total_detail'))
                    ->groupBy('penyelesaian_keanggotaan.id', 'penyelesaian_keanggotaan.total_hak_anggota'),
                'snapshot'
            )
            ->whereRaw('ABS(CAST(total_hak_anggota AS DECIMAL(15,2)) - CAST(total_detail AS DECIMAL(15,2))) > 0.01')
            ->count();
    }

    private function duplicateRightSource(): int
    {
        if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'tipe_detail')) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table('penyelesaian_keanggotaan_detail')
                    ->select('source_type', 'source_id', DB::raw('COUNT(*) as total'))
                    ->where('tipe_detail', 'hak')
                    ->whereNotNull('source_type')
                    ->whereNotNull('source_id')
                    ->groupBy('source_type', 'source_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicates'
            )
            ->count();
    }

    private function rightAllocationExceedsSource(): int
    {
        if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'nominal_hak_awal')) {
            return 0;
        }

        return DB::table('penyelesaian_keanggotaan_detail')
            ->where('tipe_detail', 'hak')
            ->whereRaw('CAST(nominal_dipakai_offset AS DECIMAL(15,2)) + CAST(nominal_direfund AS DECIMAL(15,2)) - CAST(nominal_hak_awal AS DECIMAL(15,2)) > 0.01')
            ->count();
    }

    private function obligationAllocationExceedsSource(): int
    {
        return DB::table('penyelesaian_keanggotaan_detail')
            ->where(function ($query): void {
                if (Schema::hasColumn('penyelesaian_keanggotaan_detail', 'tipe_detail')) {
                    $query->where('tipe_detail', 'kewajiban');
                }
            })
            ->whereRaw('CAST(nominal_offset AS DECIMAL(15,2)) + CAST(nominal_dibayar_tunai AS DECIMAL(15,2)) - CAST(nominal_kewajiban_awal AS DECIMAL(15,2)) > 0.01')
            ->count();
    }

    private function completedWithRemainingObligation(): int
    {
        return DB::table('penyelesaian_keanggotaan')
            ->where('status', 'completed')
            ->whereRaw('CAST(sisa_kewajiban AS DECIMAL(15,2)) > 0')
            ->count();
    }

    private function completedWithRemainingRights(): int
    {
        if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'nominal_hak_awal')) {
            return 0;
        }

        return DB::table('penyelesaian_keanggotaan')
            ->join('penyelesaian_keanggotaan_detail', 'penyelesaian_keanggotaan_detail.penyelesaian_keanggotaan_id', '=', 'penyelesaian_keanggotaan.id')
            ->where('penyelesaian_keanggotaan.status', 'completed')
            ->where('penyelesaian_keanggotaan_detail.tipe_detail', 'hak')
            ->whereRaw('CAST(nominal_hak_awal AS DECIMAL(15,2)) - CAST(nominal_dipakai_offset AS DECIMAL(15,2)) - CAST(nominal_direfund AS DECIMAL(15,2)) > 0.01')
            ->count();
    }

    private function completedRefundWithoutMutasi(): int
    {
        return DB::table('penyelesaian_keanggotaan')
            ->where('status', 'completed')
            ->whereRaw('CAST(total_refund AS DECIMAL(15,2)) > 0')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('mutasi_kas')
                    ->whereColumn('mutasi_kas.referensi_id', 'penyelesaian_keanggotaan.id')
                    ->where('mutasi_kas.referensi_tipe', 'App\\Models\\PenyelesaianKeanggotaan')
                    ->where('mutasi_kas.tipe', 'keluar');
            })
            ->count();
    }

    private function refundMutasiWithoutJurnal(): int
    {
        if (! $this->hasTables(['mutasi_kas', 'jurnal_umum'])) {
            return 0;
        }

        return DB::table('mutasi_kas')
            ->where('referensi_tipe', 'App\\Models\\PenyelesaianKeanggotaan')
            ->where('tipe', 'keluar')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('jurnal_umum')
                    ->whereColumn('jurnal_umum.referensi_id', 'mutasi_kas.referensi_id')
                    ->where('jurnal_umum.referensi_tipe', 'App\\Models\\PenyelesaianKeanggotaan')
                    ->where('jurnal_umum.idempotency_key', 'like', 'keanggotaan:refund:jurnal:%');
            })
            ->count();
    }

    private function unbalancedJournals(): int
    {
        if (! $this->hasTables(['jurnal_umum', 'jurnal_umum_detail'])) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table('jurnal_umum')
                    ->join('jurnal_umum_detail', 'jurnal_umum_detail.jurnal_umum_id', '=', 'jurnal_umum.id')
                    ->select('jurnal_umum.id', DB::raw('COALESCE(SUM(jurnal_umum_detail.debit), 0) as debit'), DB::raw('COALESCE(SUM(jurnal_umum_detail.kredit), 0) as kredit'))
                    ->groupBy('jurnal_umum.id'),
                'balance'
            )
            ->whereRaw('ABS(CAST(debit AS DECIMAL(15,2)) - CAST(kredit AS DECIMAL(15,2))) > 0.01')
            ->count();
    }

    private function pinjamanScheduleMismatch(): int
    {
        if (! Schema::hasColumn('jadwal_cicilan_pinjaman', 'nominal_sisa')) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table('pinjaman')
                    ->leftJoin('jadwal_cicilan_pinjaman', function ($join): void {
                        $join->on('jadwal_cicilan_pinjaman.pinjaman_id', '=', 'pinjaman.id')
                            ->whereIn('jadwal_cicilan_pinjaman.status', ['scheduled', 'reserved']);
                    })
                    ->select('pinjaman.id', 'pinjaman.sisa_pinjaman', DB::raw('COALESCE(SUM(COALESCE(jadwal_cicilan_pinjaman.nominal_sisa, jadwal_cicilan_pinjaman.nominal_pokok)), 0) as total_sisa_jadwal'))
                    ->where('pinjaman.status', 'aktif')
                    ->groupBy('pinjaman.id', 'pinjaman.sisa_pinjaman'),
                'snapshot'
            )
            ->whereRaw('ABS(CAST(sisa_pinjaman AS DECIMAL(15,2)) - CAST(total_sisa_jadwal AS DECIMAL(15,2))) > 0.01')
            ->count();
    }

    private function scheduleOffsetWithoutSettlement(): int
    {
        if (! Schema::hasColumn('jadwal_cicilan_pinjaman', 'nominal_offset')) {
            return 0;
        }

        return DB::table('jadwal_cicilan_pinjaman')
            ->join('pinjaman', 'pinjaman.id', '=', 'jadwal_cicilan_pinjaman.pinjaman_id')
            ->whereRaw('CAST(jadwal_cicilan_pinjaman.nominal_offset AS DECIMAL(15,2)) > 0')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('penyelesaian_keanggotaan_detail')
                    ->whereColumn('penyelesaian_keanggotaan_detail.source_id', 'pinjaman.id')
                    ->where('penyelesaian_keanggotaan_detail.source_type', 'App\\Models\\Pinjaman')
                    ->whereRaw('CAST(penyelesaian_keanggotaan_detail.nominal_offset AS DECIMAL(15,2)) > 0');
            })
            ->count();
    }

    private function exitReversedPokokWithoutReversal(): int
    {
        return DB::table('simpanan')
            ->where('status', 'reversed_due_to_exit')
            ->whereNull('reversal_transaksi_id')
            ->count();
    }

    private function wajibOutstandingAfterExit(): int
    {
        if (! $this->hasTables(['jadwal_simpanan_wajib', 'siklus_keanggotaan'])) {
            return 0;
        }

        return DB::table('jadwal_simpanan_wajib')
            ->join('siklus_keanggotaan', 'siklus_keanggotaan.id', '=', 'jadwal_simpanan_wajib.siklus_keanggotaan_id')
            ->where('siklus_keanggotaan.status', 'closed')
            ->whereIn('jadwal_simpanan_wajib.status', ['outstanding', 'reserved'])
            ->count();
    }

    private function wajibCancelledWithoutJournal(): int
    {
        if (! $this->hasTables(['jadwal_simpanan_wajib', 'reversal_transaksi', 'jurnal_umum'])) {
            return 0;
        }

        return DB::table('jadwal_simpanan_wajib')
            ->leftJoin('reversal_transaksi', 'reversal_transaksi.id', '=', 'jadwal_simpanan_wajib.cancellation_reversal_id')
            ->where('jadwal_simpanan_wajib.status', 'cancelled_exit')
            ->where(function ($query): void {
                $query->whereNull('jadwal_simpanan_wajib.cancellation_reversal_id')
                    ->orWhereNotExists(function ($exists): void {
                        $exists->selectRaw('1')
                            ->from('jurnal_umum')
                            ->whereColumn('jurnal_umum.referensi_id', 'reversal_transaksi.id')
                            ->where('jurnal_umum.referensi_tipe', 'App\\Models\\ReversalTransaksi')
                            ->where('jurnal_umum.idempotency_key', 'like', 'reversal:jurnal:%');
                    });
            })
            ->count();
    }

    private function settledWajibWronglyCancelled(): int
    {
        if (! $this->hasTables(['jadwal_simpanan_wajib', 'simpanan'])) {
            return 0;
        }

        return DB::table('jadwal_simpanan_wajib')
            ->leftJoin('simpanan', 'simpanan.jadwal_simpanan_wajib_id', '=', 'jadwal_simpanan_wajib.id')
            ->where(function ($query): void {
                $query->where(function ($nested): void {
                    $nested->where('jadwal_simpanan_wajib.status', 'settled')
                        ->whereNotNull('jadwal_simpanan_wajib.cancellation_reversal_id');
                })->orWhere(function ($nested): void {
                    $nested->where('jadwal_simpanan_wajib.status', 'cancelled_exit')
                        ->where('simpanan.status', 'settled');
                });
            })
            ->count();
    }

    private function paidWajibMissingRightDetail(): int
    {
        if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'tipe_detail')) {
            return 0;
        }

        return DB::table('simpanan')
            ->join('penyelesaian_keanggotaan', 'penyelesaian_keanggotaan.siklus_keanggotaan_id', '=', 'simpanan.siklus_keanggotaan_id')
            ->where('simpanan.kode_jenis_snapshot', 'SIMPANAN_WAJIB')
            ->whereIn('simpanan.status', ['settled', 'settled_cash'])
            ->where('penyelesaian_keanggotaan.status', '!=', 'cancelled')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('penyelesaian_keanggotaan_detail')
                    ->whereColumn('penyelesaian_keanggotaan_detail.penyelesaian_keanggotaan_id', 'penyelesaian_keanggotaan.id')
                    ->whereColumn('penyelesaian_keanggotaan_detail.source_id', 'simpanan.id')
                    ->where('penyelesaian_keanggotaan_detail.source_type', 'App\\Models\\Simpanan')
                    ->where('penyelesaian_keanggotaan_detail.tipe_detail', 'hak');
            })
            ->count();
    }

    private function manasukaMissingRightDetail(): int
    {
        if (! $this->hasTables(['saldo_simpanan_manasuka', 'penyelesaian_keanggotaan_detail']) || ! Schema::hasColumn('saldo_simpanan_manasuka', 'penyelesaian_keanggotaan_id')) {
            return 0;
        }

        return DB::table('saldo_simpanan_manasuka')
            ->join('penyelesaian_keanggotaan', 'penyelesaian_keanggotaan.siklus_keanggotaan_id', '=', 'saldo_simpanan_manasuka.siklus_keanggotaan_id')
            ->where('penyelesaian_keanggotaan.status', '!=', 'cancelled')
            ->whereRaw('CAST(saldo_simpanan_manasuka.saldo AS DECIMAL(15,2)) > 0')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('penyelesaian_keanggotaan_detail')
                    ->whereColumn('penyelesaian_keanggotaan_detail.penyelesaian_keanggotaan_id', 'penyelesaian_keanggotaan.id')
                    ->whereColumn('penyelesaian_keanggotaan_detail.source_id', 'saldo_simpanan_manasuka.id')
                    ->where('penyelesaian_keanggotaan_detail.source_type', 'App\\Models\\SaldoSimpananManasuka')
                    ->where('penyelesaian_keanggotaan_detail.tipe_detail', 'hak');
            })
            ->count();
    }

    private function completedOldManasukaStillPositive(): int
    {
        if (! $this->hasTables(['saldo_simpanan_manasuka', 'penyelesaian_keanggotaan'])) {
            return 0;
        }

        return DB::table('penyelesaian_keanggotaan')
            ->join('saldo_simpanan_manasuka', function ($join): void {
                $join->on('saldo_simpanan_manasuka.anggota_id', '=', 'penyelesaian_keanggotaan.anggota_id')
                    ->on('saldo_simpanan_manasuka.siklus_keanggotaan_id', '=', 'penyelesaian_keanggotaan.siklus_keanggotaan_id');
            })
            ->where('penyelesaian_keanggotaan.status', 'completed')
            ->whereRaw('CAST(saldo_simpanan_manasuka.saldo AS DECIMAL(15,2)) > 0')
            ->count();
    }

    private function reactivationBeforeCompleted(): int
    {
        return DB::table('siklus_keanggotaan as active_cycle')
            ->where('active_cycle.status', 'active')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('siklus_keanggotaan as closed_cycle')
                    ->join('penyelesaian_keanggotaan', 'penyelesaian_keanggotaan.siklus_keanggotaan_id', '=', 'closed_cycle.id')
                    ->whereColumn('closed_cycle.anggota_id', 'active_cycle.anggota_id')
                    ->where('closed_cycle.status', 'closed')
                    ->where('penyelesaian_keanggotaan.status', '!=', 'completed');
            })
            ->count();
    }

    private function cancelledDeactivationWithMaterialProcess(): int
    {
        return DB::table('penyelesaian_keanggotaan')
            ->where('status', 'dibatalkan_penonaktifan')
            ->where(function ($query): void {
                $query->whereExists(function ($exists): void {
                    $exists->selectRaw('1')
                        ->from('mutasi_kas')
                        ->whereColumn('mutasi_kas.referensi_id', 'penyelesaian_keanggotaan.id')
                        ->where('mutasi_kas.referensi_tipe', 'App\\Models\\PenyelesaianKeanggotaan');
                })
                    ->orWhereExists(function ($exists): void {
                        $exists->selectRaw('1')
                            ->from('penyelesaian_keanggotaan_detail')
                            ->whereColumn('penyelesaian_keanggotaan_detail.penyelesaian_keanggotaan_id', 'penyelesaian_keanggotaan.id')
                            ->where(function ($nested): void {
                                $nested->whereRaw('CAST(nominal_dipakai_offset AS DECIMAL(15,2)) > 0')
                                    ->orWhereRaw('CAST(nominal_direfund AS DECIMAL(15,2)) > 0')
                                    ->orWhereRaw('CAST(nominal_offset AS DECIMAL(15,2)) > 0')
                                    ->orWhereRaw('CAST(nominal_dibayar_tunai AS DECIMAL(15,2)) > 0');
                            });
                    })
                    ->orWhereExists(function ($exists): void {
                        $exists->selectRaw('1')
                            ->from('simpanan')
                            ->whereColumn('simpanan.anggota_id', 'penyelesaian_keanggotaan.anggota_id')
                            ->whereColumn('simpanan.siklus_keanggotaan_id', 'penyelesaian_keanggotaan.siklus_keanggotaan_id')
                            ->where('simpanan.kode_jenis_snapshot', 'SIMPANAN_POKOK')
                            ->where('simpanan.status', 'reversed_due_to_exit');
                    });
            })
            ->count();
    }

    private function cancelledDeactivationFrozenManasuka(): int
    {
        if (! $this->hasTables(['saldo_simpanan_manasuka', 'penyelesaian_keanggotaan'])) {
            return 0;
        }

        return DB::table('penyelesaian_keanggotaan')
            ->join('saldo_simpanan_manasuka', 'saldo_simpanan_manasuka.penyelesaian_keanggotaan_id', '=', 'penyelesaian_keanggotaan.id')
            ->where('penyelesaian_keanggotaan.status', 'dibatalkan_penonaktifan')
            ->whereNotNull('saldo_simpanan_manasuka.frozen_at')
            ->count();
    }

    private function cancelledDeactivationWajibStillCancelled(): int
    {
        if (! $this->hasTables(['jadwal_simpanan_wajib', 'penyelesaian_keanggotaan'])) {
            return 0;
        }

        return DB::table('penyelesaian_keanggotaan')
            ->join('jadwal_simpanan_wajib', 'jadwal_simpanan_wajib.penyelesaian_keanggotaan_id', '=', 'penyelesaian_keanggotaan.id')
            ->where('penyelesaian_keanggotaan.status', 'dibatalkan_penonaktifan')
            ->where('jadwal_simpanan_wajib.status', 'cancelled_exit')
            ->count();
    }

    private function reRegistrationUsesOldCycle(): int
    {
        if (! Schema::hasColumn('penyelesaian_keanggotaan', 're_registered_cycle_id')) {
            return 0;
        }

        return DB::table('penyelesaian_keanggotaan')
            ->whereNotNull('re_registered_cycle_id')
            ->whereColumn('re_registered_cycle_id', 'siklus_keanggotaan_id')
            ->count();
    }

    private function reRegisteredWithOldActiveLoan(): int
    {
        if (! $this->hasTables(['penyelesaian_keanggotaan', 'pinjaman'])
            || ! Schema::hasColumn('penyelesaian_keanggotaan', 're_registered_cycle_id')
            || ! Schema::hasColumn('pinjaman', 'siklus_keanggotaan_id')) {
            return 0;
        }

        return DB::table('penyelesaian_keanggotaan as p')
            ->join('pinjaman as loan', function ($join): void {
                $join->on('loan.anggota_id', '=', 'p.anggota_id')
                    ->on('loan.siklus_keanggotaan_id', '=', 'p.siklus_keanggotaan_id');
            })
            ->whereNotNull('p.re_registered_cycle_id')
            ->where('loan.status', 'aktif')
            ->whereRaw('CAST(loan.sisa_pinjaman AS DECIMAL(15,2)) > 0')
            ->count('loan.id');
    }

    private function reRegisteredManasukaNotZero(): int
    {
        if (! $this->hasTables(['penyelesaian_keanggotaan', 'saldo_simpanan_manasuka'])
            || ! Schema::hasColumn('penyelesaian_keanggotaan', 're_registered_cycle_id')) {
            return 0;
        }

        return DB::table('penyelesaian_keanggotaan')
            ->join('saldo_simpanan_manasuka', 'saldo_simpanan_manasuka.siklus_keanggotaan_id', '=', 'penyelesaian_keanggotaan.re_registered_cycle_id')
            ->whereNotNull('penyelesaian_keanggotaan.re_registered_cycle_id')
            ->whereRaw('CAST(saldo_simpanan_manasuka.saldo AS DECIMAL(15,2)) <> 0')
            ->count();
    }

    private function orphanSettlementDetails(): int
    {
        if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'tipe_detail')) {
            return 0;
        }

        $checks = [
            'App\\Models\\Simpanan' => 'simpanan',
            'App\\Models\\Pinjaman' => 'pinjaman',
            'App\\Models\\Pembayaran' => 'pembayaran',
            'App\\Models\\KreditPotongGajiAnggota' => 'kredit_potong_gaji_anggota',
            'App\\Models\\SaldoSimpananManasuka' => 'saldo_simpanan_manasuka',
            'App\\Models\\JadwalSimpananWajib' => 'jadwal_simpanan_wajib',
        ];

        $total = 0;
        foreach ($checks as $sourceType => $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $total += DB::table('penyelesaian_keanggotaan_detail')
                ->where('source_type', $sourceType)
                ->whereNotExists(function ($query) use ($table): void {
                    $query->selectRaw('1')
                        ->from($table)
                        ->whereColumn($table . '.id', 'penyelesaian_keanggotaan_detail.source_id');
                })
                ->count();
        }

        return $total;
    }

    private function duplicateIdempotencyKeys(): int
    {
        $tables = [
            'penyelesaian_keanggotaan',
            'penyelesaian_keanggotaan_detail',
            'reversal_transaksi',
            'mutasi_kas',
            'jurnal_umum',
        ];

        $total = 0;
        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'idempotency_key')) {
                continue;
            }

            $total += DB::query()
                ->fromSub(
                    DB::table($table)
                        ->select('idempotency_key', DB::raw('COUNT(*) as total'))
                        ->whereNotNull('idempotency_key')
                        ->groupBy('idempotency_key')
                        ->havingRaw('COUNT(*) > 1'),
                    'duplicates'
                )
                ->count();
        }

        return $total;
    }
}
